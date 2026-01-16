<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use App\Http\Controllers\TourRadarController;
use App\Http\Controllers\newPackageController;
use App\Http\Controllers\DuffelApiController;
use App\Http\Controllers\StripeController;
use App\Http\Controllers\TourController;
use App\Models\Order;
use App\Models\Attempt;

class HoldProcessPendingAttempts extends Command
{
    protected $signature = 'process:pending-attempts';
    protected $description = 'Process pending attempts and confirm bookings.';

    public function __construct()
    {
        parent::__construct();
    }

    public function handle()
    {
        $this->processPendingAttempts();
    }

    public function processPendingAttempts()
    {
        $pendingAttempts = Attempt::query()
            ->where('status', 'pending')
            ->where('expiration', '>=', now())
            ->get();

        foreach ($pendingAttempts as $attempt) {
            $duffelId = $attempt->order_id; 
            $bookingId = $attempt->booking_id;  
            $RequestPassengers = $attempt->passengers;  
            $paymentIntent = $attempt->payment_id;
            Log::info('automatic Processing attempt ID: ' . $attempt->id. 'expiration: ' . $attempt->expiration );
            $ResponseTour = json_decode($attempt->tourradar_res, true);
            Log::info('automatic Processing $ResponseTour: ' . json_encode($ResponseTour));
            $tBookingId = $ResponseTour ? $ResponseTour['id'] : null;

            if(!$tBookingId){
                Log::error('No tBookingId found in response');
                continue;
            }
            Log::info('automatic Processing booking ID: ' . $tBookingId);

            try {
                // Check if the order exists in the database
                $order = Order::where('tourradar_id', $tBookingId)->first();
            } catch (\Exception $e) {
                Log::error('Database error checking order for tourradar booking ID ' . $tBookingId . ': ' . $e->getMessage());
                return; // Stop execution if a database error occurs
            }

            if ($order) {
                $statusResponse = strval($order->tourradar_status);
                Log::info("Automatic Order found in the database. TourRadar Status: " . $statusResponse);
            } else {
                try {
                    // If the order is not found, make the API call
                    $tourradarResponse = TourRadarController::checkBooking($tBookingId);
                    $statusResponse = $tourradarResponse ->status;
                    Log::info("Automatic API call made for tourradar booking ID: " . $tBookingId . " - Response: " . $statusResponse);
                } catch (\Exception $e) {
                    Log::error('API error checking tourradar booking ID ' . $tBookingId . ': ' . $e->getMessage());
                    return; // Stop execution if API fails
                }
            }
            Log::info("Final statusResponse before checking condition: '" . $statusResponse . "'");

            // Ensure the status check is executed correctly
            if (trim(strtolower($statusResponse)) === "confirmed") {
                // Retrieve flight data from the current attempt
                $flight = json_decode($attempt->duffel_res, true);
                Log::info('automatic Flight Data: ' . json_encode($flight));

                if (isset($flight['data']['payment_status']['paid_at'])) {
                    
                        
                        Log::info('automatic Duffel booking successful for duffel order ID ' . $bookingId);
                        $stripeResponse = StripeController::capturePayment($paymentIntent);
                        Log::info('automatic Stripe payment for payment ID ' . $paymentIntent . ': ' . json_encode($stripeResponse));

                        // Execute get paymentIntent
                        $stripePiResponse = StripeController::getPaymentIntent($paymentIntent);

                        // Extract the data from the JsonResponse
                        $stripePi = $stripePiResponse->getData(true); // Convert the JSON response to an associative array

                        // Check if the response has 'balance_transaction' details
                        $stripeFee = $stripePi['data']['balance_transaction']['fee'] ?? null;

                        // Log the Stripe fee (before returning any response)
                        \Log::info('Stripe Fee: ' . ($stripeFee ?? 'Not Found'));

                        if ($stripeFee !== null) {
                            // Process the fee if it exists
                            Order::where('booking_id', $attempt->booking_id)->update(['stripe_fee' => $stripeFee]);
                        }
                        Attempt::where('id', $attempt->id)->update(['status' => 'confirmed']);
                        $mailResponse = TourController::emailBConfirmation($bookingId, $duffelId, $paymentIntent, $RequestPassengers);                   
                        Log::info('automatic mail sent ' . $mailResponse . ' confirmed.');
                } 
            }                 
            else {
                Log::warning('tourradar booking has not been confirmed ' . $attempt->booking_id);
            }
        }

        $expiredAttempts = Attempt::query()
            ->where('status', 'pending')
            ->where('expiration', '<', now())
            ->get();

        foreach ($expiredAttempts as $attempt) {
            $paymentIntent = $attempt->payment_id;
            $bookingId = $attempt->booking_id;
            Log::info('automatic Processing expired attempt ID: ' . $attempt->id . 'expiration: ' . $attempt->expiration);
            if(!$paymentIntent){
                Log::error('No payment intent found in attempt');
                continue;
            }
            try {
                $stripePayment = StripeController::getPaymentIntent($paymentIntent);
                Log::info('automatic Stripe payment retrieved for payment ID ' . $paymentIntent . ': ' . json_encode($stripePayment));
                $stripePaymentData = $stripePayment->getData(true); // Convert to array

                if ($stripePaymentData['data']['payment_intent']['canceled_at'] == null) {
                    $stripeResponse = StripeController::cancelPayment($paymentIntent);
                    Log::info('automatic Stripe cancel payment for payment ID ' . $paymentIntent . ': ' . json_encode($stripeResponse));
                    Attempt::where('id', $attempt->id)->update(['status' => 'failed']);
                    Log::info('automatic attempt failed (expired): ' . $attempt->id );
                    $mailResponse = TourController::bookingCancellation($bookingId);
                    Order::where('booking_id', $attempt->booking_id)->update(['booking_status' => 'failed']);
                    Log::info('automatic mail sent ' . $mailResponse . ' cancelled.');
                } else {
                    Attempt::where('id', $attempt->id)->update(['status' => 'failed']);
                    Order::where('booking_id', $attempt->booking_id)->update(['booking_status' => 'failed']);
                    Log::info('automatic attempt already canceled in stripe: ' . $attempt->id . 'data' . $stripePaymentData['data']['payment_intent']);
                }
            } catch (\Exception $e) {
                Log::error('automatic Error cancelling payment ID ' . $paymentIntent . ': ' . $e->getMessage());
            }
        }
        $failedAttempts = Attempt::query()
            ->where('status', 'failed')
            ->get();

        foreach ($failedAttempts as $attempt) {
            $paymentIntent = $attempt->payment_id;
            $bookingId = $attempt->booking_id;
            Log::info('automatic Processing failed attempt ID: ' . $attempt->id);
            if(!$paymentIntent){
                Log::error('No payment intent found in attempt');
                continue;
            }
            try {
                $stripePayment = StripeController::getPaymentIntent($paymentIntent);
                Log::info('automatic Stripe payment retrieved for payment ID ' . $paymentIntent . ': ' . json_encode($stripePayment));
                $stripePaymentData = $stripePayment->getData(true); // Convert to array

                if ($stripePaymentData['data']['payment_intent']['canceled_at'] == null) {
                    $stripeResponse = StripeController::cancelPayment($paymentIntent);
                    Log::info('automatic Stripe cancel payment for payment ID ' . $paymentIntent . ': ' . json_encode($stripeResponse));
                    $mailResponse = TourController::bookingCancellation($bookingId);
                    Log::info('automatic mail sent ' . $mailResponse . ' cancelled.');
                    Order::where('booking_id', $attempt->booking_id)->update(['booking_status' => 'failed']);
                    Log::info('automatic attempt failed (expired): ' . $attempt->id );
                } else {
                    Log::info('automatic attempt already canceled in stripe: ' . $attempt->id . 'data' . $stripePaymentData['data']['payment_intent']);
                }
            } catch (\Exception $e) {
                Log::error('automatic Error cancelling payment ID ' . $paymentIntent . ': ' . $e->getMessage());
            }
        }
    }
    
}
