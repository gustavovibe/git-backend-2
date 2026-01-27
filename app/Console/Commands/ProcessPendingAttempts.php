<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Http\Request;
use App\Http\Controllers\StripeController;
use App\Http\Controllers\TourController;
use App\Http\Controllers\TourradarController;
use App\Models\Attempt;
use App\Models\Order;

class ProcessPendingAttempts extends Command
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
            $tBookingId = data_get($attempt->tourradar_res, 'id');
            Log::info('automatic Processing attempt ID: ' . $attempt->id. 'expiration: ' . $attempt->expiration );
            $ResponseTour = $attempt->tourradar_res;
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
                    Log::info("Automatic API call made for tourradar booking ID: " . $tBookingId . " - Response: " . $tourradarResponse);
                    if($tourradarResponse->status){
                        $statusResponse = $tourradarResponse->status;
                        Log::info("Status of tourradar booking ID: " . $tBookingId . " - Response: " . $statusResponse);
        
                    }elseif($tourradarResponse->error){
                        Log::info("Error: " . $tBookingId . " - Response: " . $statusResponse->error);
                    }
                } catch (\Exception $e) {
                    Log::error('API error checking tourradar booking ID ' . $tBookingId . ': ' . $e->getMessage());
                    return; // Stop execution if API fails
                }
            }
            Log::info("Final statusResponse before checking condition: '" . $statusResponse . "'");
            $flight = $attempt->duffel_res;
            $orderId = json_decode($attempt->order_id, true);
            Log::info('automatic Flight Data: ' . json_encode($flight));
            if (isset($flightResponse['errors']) && $flightResponse['errors']) {
                Log::error('automatic Duffel booking failed for duffel order ID ' . $orderId);
            } else {
                        Log::info('automatic Duffel booking successful for duffel order ID ' . $orderId);
                        $stripeResponse = StripeController::capturePayment($paymentIntent);
                        Log::info('automatic Stripe payment for payment ID ' . $paymentIntent . ': ' . json_encode($stripeResponse));
                        
                        // Execute get paymentIntent
                        $stripePiResponse = StripeController::getPaymentIntent($paymentId);

                        // Extract the data from the JsonResponse
                        $stripePi = $stripePiResponse->getData(true); // Convert the JSON response to an associative array

                        // Check if the response has 'balance_transaction' details
                        $stripeFee = $stripePi['data']['balance_transaction']['fee'] ?? null;

                        // Log the Stripe fee (before returning any response)
                        \Log::info('Stripe Fee: ' . ($stripeFee ?? 'Not Found'));

                        if ($stripeFee !== null) {
                            // Process the fee if it exists
                            return response()->json([
                                'message' => 'Stripe fee retrieved successfully.',
                                'stripe_fee' => $stripeFee,
                            ]);
                            DB::table('orders')->where('booking_id', $attempt->booking_id)->update(['stripe_fee' => $stripeFee]);
                        } else {
                            // Handle cases where the fee is not available
                            return response()->json([
                                'message' => 'Stripe fee not found in the response.',
                            ], 404);
                        }

                // Update the attempt status to confirmed
                $attempt->update(['status' => 'confirmed']);
                $mailResponse = TourController::emailBConfirmation($bookingId, $duffelId, $paymentId, $RequestPassengers);
                Log::info('automatic mail sent ' . $mailResponse . ' confirmed.');
                Log::info('automatic duffel order ID ' . $orderId . ' confirmed.');
            }
        }


        $expiredAttempts = Attempt::query()
            ->where('status', 'pending')
            ->where('expiration', '<', now())
            ->get();
  
        foreach ($expiredAttempts as $attempt) {
            Log::info('automatic Processing expired attempt ID: ' . $attempt->id . 'expiration: ' . $attempt->expiration);
            $bookingId = $attempt->booking_id;
            $paymentIntent = $attempt->payment_id;
            if(!$paymentIntent){
                Log::error('No payment intent found in attempt');
                continue;
            }
            try {
                $stripePayment = StripeController::getPaymentIntent($paymentIntent);
                Log::info('automatic Stripe payment retrieved for payment ID ' . $paymentIntent . ': ' . json_encode($stripePayment));
                $stripePaymentData = $stripePayment->getData(true); // Convert to array

                if ($stripePaymentData['data']['payment_intent']['canceled_at'] == null) {
                    $stripeResponse = StripeController::cancellPayment($paymentIntent);
                    
                    Log::info('automatic Stripe cancell payment for payment ID ' . $paymentIntent . ': ' . json_encode($stripeResponse));
                    $attempt->update(['status' => 'failed']);
                    $order = Order::find($bookingId);
                    $email = $order?->user?->email;
                    if (!$email) {
                        Log::error('No email found for booking ID: ' . $bookingId);
                        continue;
                    }
                    $mailRequest = Request::create('/booking-cancellation', 'GET', [
                        'booking_id' => $bookingId,
                        'email' => $email,
                    ]);
                    $mailResponse = app(TourController::class)->bookingCancellation($mailRequest);
                    Log::info('automatic mail sent ' . $mailResponse . ' cancelled.');
                    Log::info('automatic attempt failed (expired): ' . $attempt->id );
                } else {
                    $attempt->update(['status' => 'failed']);
                    Log::info('automatic attempt already cancelled in stripe: ' . $attempt->id );
                    $order = Order::find($bookingId);
                    $email = $order?->user?->email;
                    if (!$email) {
                        Log::error('No email found for booking ID: ' . $bookingId);
                        continue;
                    }
                    $mailRequest = Request::create('/booking-cancellation', 'GET', [
                        'booking_id' => $bookingId,
                        'email' => $email,
                    ]);
                    $mailResponse = app(TourController::class)->bookingCancellation($mailRequest);
                    Log::info('automatic mail sent ' . $mailResponse . ' cancelled.');
                }
            } catch (\Exception $e) {
                Log::error('automatic Error cancelling payment ID ' . $paymentIntent . ': ' . $e->getMessage());
            }
        }  
    }
}