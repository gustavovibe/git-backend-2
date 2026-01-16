<?php

namespace App\Http\Controllers;
use Illuminate\Support\Arr;
use App\Filters\ToursFilters;
use App\Models\Tour;
use Illuminate\Http\Request;
use App\Helpers\ApiResponse;
use App\Http\Controllers\TourRadarController;
use App\Mail\BookingMail;
use App\Mail\SendSummary;
use App\Mail\TourDetails;
use App\Mail\AbandonedCartMail;
use App\Mail\BookAtach;
use App\Mail\BookEmail;
use App\Models\BookingSummary;
use App\Models\Order;
use App\Models\Type;
use App\Models\User;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Carbon\Carbon;
use Barryvdh\DomPDF\Facade\Pdf;
use DateInterval;
use Illuminate\Support\Facades\Storage;
use GuzzleHttp\Client;
use App\Models\ActionLog;
use Illuminate\Support\Facades\Log;

use Exception;

class TourController extends Controller
{

    /**
     * Display a listing of tours.
     *
     * Updated at 10/12/2024 (user)
     *
     * @param Request $request Request object
     * @return array
     */
    public function index(Request $request)
    {
        $query = Tour::query();

        if ($request->has('country')) {
            $countries = $this->extractArrayFromQueryParam($request->input('country'));
            $query->orWhereHas('countries', function ($q) use ($countries) {
                $q->whereIn('t_country_id', $countries);
            });
            $query->with(['cities', 'natural_destination', 'type', 'countries']);
        }

        if ($request->has('city')) {
            $cities = $this->extractArrayFromQueryParam($request->input('city'));
            $query->orWhereHas('cities', function ($q) use ($cities) {
                $q->whereIn('t_city_id', $cities);
            });
            $query->with(['cities', 'natural_destination', 'type', 'countries']);
        }

        if ($request->has('natural_destination')) {
            $naturalDestinations = $this->extractArrayFromQueryParam($request->input('natural_destination'));
            $query->orWhereHas('natural_destination', function ($q) use ($naturalDestinations) {
                $q->whereIn('t_natural_id', $naturalDestinations);
            });
            $query->with(['cities', 'natural_destination', 'type', 'countries']);
        }

        if ($request->has('tour_type')) {
            $tourType = $this->extractArrayFromQueryParam($request->input('tour_type'));
            $query->orWhereHas('type', function ($q) use ($tourType) {
                $q->whereIn('tour_type_id', $tourType);
            });
            $query->with(['cities', 'natural_destination', 'type', 'countries']);
        }

        if ($request->has('day_price')) {
            $dayPrice = $request->input('day_price');
            $query->whereRaw('price_total / tour_length_days <= ?', [$dayPrice]);
        }

        if ($request->has('sort_by') && $request->has('sort_order')) {
            $sortBy = $request->input('sort_by');
            $sortOrder = $request->input('sort_order');

            $validSortFields = ['price_total', 'tour_length_days', 'reviews_count', 'ratings_overall', 'price_day'];

            if (in_array($sortBy, $validSortFields)) {
                if ($sortBy == 'price_day') {
                    $query->orderByRaw('price_total / tour_length_days ' . $sortOrder);
                } else {
                    $query->orderBy($sortBy, $sortOrder);
                }
            }
        }
        
        if ($request->has('tour_ids')) {
            $tourIds = $this->extractArrayFromQueryParam($request->input('tour_ids'));
            $query->whereIn('tour_id', $tourIds);
            $query->with(['cities', 'natural_destination', 'type', 'countries']);
        }

        !$request->list?:$query->select('tour_name','tour_id');

        $results = $query->get();

        return ApiResponse::success($results);
    }

    /**
     * Extract array from query param.
     *
     * Updated at 10/12/2024 (user)
     *
     * @param string $param Param
     * @return array
     */
    protected function extractArrayFromQueryParam($param)
    {
        $param = trim($param, '[]');
        $values = explode(',', $param);
        return array_map('trim', $values);
    }

    /**
     * Get text.
     *
     * Updated at 10/12/2024 (user)
     *
     * @param Request $r Request object
     * @return array
     */
    public static function getText(Request $r)
    {
        $scope = "com.tourradar.bookings/read";
        $accessToken = TourRadarController::getAccessToken($scope);
        $url = "https://api.sandbox.b2b.tourradar.com/v1/operators/{$r->operatorId}";
        $headers = [
            'Accept' => 'application/json',
            'Authorization' => 'Bearer ' . $accessToken,
        ];
        try {
            $response = Http::withHeaders($headers)->get($url);
            return response()->json(['status'=>true,'response'=>$response->json()]);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Show.
     *
     * Updated at 10/12/2024 (user)
     *
     * @param Request $r Request object
     * @return array
     */
    public function show(Request $r){
        try{
            $tour=ToursFilters::ToursP($r);
            return response()->json(['status'=>true,'count'=>count($tour), 'response'=>$tour]);
        }catch(Exception $e){
            return response()->json(['status'=>false, 'response'=>$e->getMessage()]);
        }
    }



    public function bookEmail(Request $r){
      /*   Mail::to('adam.g.e@outlook.com')->send(new  BookAtach());
        return 'entro'; */
        Mail::raw('Este es un correo de prueba', function ($message) {
            $message->to('adam.g.e@outlook.com')->subject('Prueba de correo');
        });
    }

    /**
     * Show type.
     *
     * Updated at 10/12/2024 (user)
     *
     * @param Request $r Request object
     * @return array
     */
    public function show_type(Request $r){
        try{
            $travel=ToursFilters::travel_styles($r);
        /*     return $travel; */
            return response()->json(['status'=>true,'count'=>Type::count(),'response'=>$travel]);
        }catch(Exception $e){
            return response()->json(['status'=>false,'response'=>$e->getMessage()]);
        }
    }

    /**
     * Email t details.
     *
     * Updated at 10/12/2024 (user)
     *
     * @param Request $r Request object
     * @return array
     */
    public function emailTDetails(Request $r){
        Mail::to($r->email)->send(new TourDetails());
        return 'mail template';
    }

    /**
     * Email b confirmation.
     *
     * Updated at 10/12/2024 (user)
     *
     * @param int $booking_id Booking ID
     * @return array
     */
    public static function emailBConfirmation($bookingId, $duffelId, $paymentId, $RequestPassengers){
        $passengers = Arr::wrap($RequestPassengers);

        Log::info('Passengers normalized:', ['passengers' => $passengers]);

        try{
            Log::info("emailBConfirmation triggered", [
                'booking_id'   => $bookingId,
                'orderId'   => $duffelId,
                'payment_id'=> $paymentId,
                'passengers'=> $passengers
            ]);
            

            $data = [
                'booking_id' => $bookingId,
                'orderId' => $duffelId,
                'q'=> $paymentId,
            ];

            $r = Request::create('/', 'GET', $data);

            Log::info("Creating Stripe request...");

            $stripeData = Self::ticketStructure($r);
            /*   Log::info("Stripe data received: ", $stripeData); */
            /*     return $stripeData; */
            //aqui se usa tour_id

            $orders=ToursFilters::OrdersPrint($r);
            /* return $stripeData; */
            if (!$orders) {
                Log::error("OrdersPrint returned null");
                return ApiResponse::error("OrdersPrint returned null");
            }
            Log::info("Order found: ", ['order' => $orders->booking_id ?? 'N/A']);

            if (!isset($orders->user) || empty($orders->user->email)) {
                Log::error("User email not found in order data");
                return ApiResponse::error("User email not found");
            }

                $invoice = [
                    'adults'         => 0,
                    'children'       => 0,
                    'infants'        => 0,
                    'total_adults'   => 0,
                    'total_children' => 0,
                    'total_infants'  => 0,
                    'subtotal'       => 0,
                    'tax'            => 0, // As specified, tax is 0
                    'total'          => 0,
                ];
                Log::info('Request passengers inside emailBConfirmation:', $passengers);
                if (!is_array($passengers)) {
                    Log::error('Passengers is not an array or is null', ['passengers' => $passengers]);
                    return ApiResponse::error("Invalid passenger data");
                }
                
                // Iterate over each passenger entry
                foreach ($passengers as $p) {
                    if (($p['passengerType'] ?? '') === 'adult') {
                        $invoice['adults']       += $p['passengers'];
                        $invoice['total_adults'] += $p['unitPrice'] * $p['passengers'];
                    } elseif (($p['passengerType'] ?? '') === 'child') {
                        $invoice['children']       += $p['passengers'];
                        $invoice['total_children'] += $p['unitPrice'] * $p['passengers'];
                    }
                }
                
                // Calculate the subtotal (both adults and children)
                $invoice['subtotal'] = $invoice['total_adults'] + $invoice['total_children'];
                
                // tax is set to 0 as per your specification
                $invoice['tax'] = 0;
                
                // Calculate the total (subtotal + tax)
                $invoice['total'] = $invoice['subtotal'] + $invoice['tax'];

              $stripe= StripeController::getPaymentIntent($paymentId);
              $stripeData_invoice = json_decode($stripe->getContent(), true);
              $invoice_content=['data'=>$stripeData_invoice['data'],'orders'=>$orders,'values'=>$invoice];


            if (empty($orderId)) {
                $booking_data = [];
            } else {
                $booking_data = (new DuffelApiController)->getOrderById($r);
                if ($booking_data instanceof \Illuminate\Http\JsonResponse) {
                    $booking_data = json_decode($booking_data->getContent(), true);
                } else {
                    $booking_data = [];
                }

                // Ahora puedes acceder a ['data'] sin error
                if (!isset($booking_data['data'])) {
                    $booking_data = [];
                }
            }



           $tourResponse = (new  ProxyTourRadarController)->show($orders->tour_id);
           $tourData = $tourResponse->getData(true);
           $tour=$tourData['data'];
           //Log::info('tour data inside emailBConfirmation:', $tour);
           foreach($tour['destinations']['countries'] as $co){
               $countries[]=$co['country_name'];
           }

           foreach($tour['tour_types'] as $to){
               $tour_types[]=$to['type_name'];
           }

           foreach($tour['guide_languages'] as $text){
               $guide_types[]=$text['name'];
           }
           $countries_d=[
               'countries_text'=>implode(', ',$countries),
               'tour_text'=>implode(', ',$tour_types),
               'guide_text'=>implode(', ',$guide_types),
           ];

           $values=['tour'=>$tour,'countries_d'=>$countries_d,'services'=>$tour['services']['included']];

           $email = $orders->user->email;
           Log::info('address emailBConfirmation: ' . $email);

           $flag=0;
           if((isset($stripeData_invoice['data']['charge_details']['balance_transaction']) && $stripeData_invoice['data']['charge_details']['balance_transaction'] !== null) ){
            $flag=1;
           }
           /* return $invoice_content; 

           $currencies = json_decode(Storage::get('currencies.json'),true);
           $currencyInfo = null;
           $currencyCode = strtoupper($invoice_content['data']['payment_intent']['currency']);
           foreach ($currencies as $currency) {
            if (strtoupper($currency['code']) === $currencyCode) {
                $currencyInfo = $currency['currency'];
                break;
            }
        }

           */
            $invoice_content['data']['payment_intent']['currency'] = 'USD';
    
        /*    return $invoice_content['data']['payment_intent']['currency'];
           return $currencies; */
        /*    return view('emails.invoice')->with( $invoice_content); */

          /*   return view('emails.booking_confirmation_2')->with(['orders'=>$orders] ); */
          //Log::info('stripe data BookEmail: ', $stripeData);
          Log::info('invoice BookEmail:', $invoice);
          //Log::info('invoice content BookEmail:', $invoice_content);
           Mail::to($email)->send(new BookEmail($orders, $stripeData, $values, $invoice, $invoice_content, $flag));
           Mail::to(['info@vibeadventures.com', 'marketing@vibeadventure.com'])->send(new BookEmail($orders, $stripeData, $values, $invoice, $invoice_content, $flag, 'admin.booking_details'));
       
           return ApiResponse::success('Email sent successfully');
        } catch (Exception $e) {
            Log::error("Error in emailBConfirmation: " . $e->getMessage());
            return ApiResponse::error($e->getMessage());
        }
    }

    public function bookingCancellation(Request $request)
    {
        $request->validate([
            'booking_id' => 'required|integer|exists:orders,booking_id',
            'email'      => 'required|email',
        ]);
        $order = Order::findOrFail($request->query('booking_id'));
        $email = $order->user->email;
        try {
            Mail::to($email)->send(new CancelMail($orders));
            return ApiResponse::success('Email sent successfully');
         } catch (Exception $e) {
             Log::error("Error in emailBConfirmation: " . $e->getMessage());
             return ApiResponse::error($e->getMessage());
         }
    }

    public function emailBookTest(Request $r){
        return $this->emailBConfirmation($r->tour_id,$r->orderId,$r->orderId,);
    }
    /**
     * Pdf order.
     *
     * Updated at 10/12/2024 (user)
     *
     * @param Request $r Request object
     * @return array
    */
    public function pdfOrder(Request $r){
        try{
            
					$orders=ToursFilters::OrdersPrint($r);

					$url_payment='';
					if(!$orders){
						return response()->json(['success'=>false,'data'=>[] , 'message'=>'Order not found']);
					}
					if($orders->payment_id){
							$client = new Client();
							$url = 'https://vibeadventures.be/api/stripe?q=' . urlencode($orders->payment_id);
							$response = $client->request('GET', $url);

							$responseBody =json_decode( $response->getBody()->getContents());
							if($responseBody->data->charge_details->receipt_url){
							$url_payment=  $responseBody->data->charge_details->receipt_url;
							}
					}

					
					$logo = 'https://vibeadventures.com/images/logo_flight.svg';
					$imageContent = Http::get($logo)->body();
					$logo = 'images/logo_flight.svg';

					Storage::disk('public')->put($logo, $imageContent);

					$logo = asset('storage/'.$logo);
					$pdf = Pdf::loadView('emails.booking_confirmation_2', ['order' => $orders,'logo'=>$logo]);
					return $pdf->stream('booking_confirmation.pdf');
        }catch(Exception $e){
            return response()->json(['success'=>false,'data'=>$e->getMessage()]);
        }
    }


    public function bookingTickets(Request $r) {
        try {

           /*  return $r->all(); */
           $tickets = $this->ticketStructure($r);

            $pdf = Pdf::loadView('emails.tickets_booking', [
                'data' => $tickets['data'],
                'passengers_data' => $tickets['passengers_data']
            ])->set_option('isRemoteEnabled', true);

            /* $pdf->getDomPDF()->getCanvas()->get_font('public/fonts/Roboto-Regular.ttf'); */
            return $pdf->stream('tickets_booking.pdf');

        } catch (Exception $e) {
            return ApiResponse::error($e->getMessage());
        }
    }




    public static  function  ticketStructure(Request $r){
        //return $r->all();
        $booking_data_response = (new DuffelApiController)->getOrderById($r);

        if ($booking_data_response instanceof \Illuminate\Http\JsonResponse) {
            $booking_data = $booking_data_response->getData(true);
        } elseif (is_array($booking_data_response)) {
            $booking_data = $booking_data_response;
        } else {
            throw new \Exception("Unexpected response type from DuffelApiController::getOrderById()");
        }

        if (!isset($booking_data['data'])) {
            throw new \Exception('Invalid booking data structure');
        }

        logger()->info('Booking data inside ticketStructure:', $booking_data);

            $passengersData = [];

            // Recorrer las "slices" y "segments" para recopilar los datos
            foreach ($booking_data['data']['slices'] as &$slice) {
                foreach ($slice['segments'] as &$segment) {

                    // Formateamos la duración y los horarios de salida y llegada
                    $duration = $segment['duration'];
                    $interval = new DateInterval($duration);
                    $segment['formatted_duration'] = $interval->h . 'h ' . str_pad($interval->i, 2, '0', STR_PAD_LEFT) . 'm';
                    $segment['formatted_departing_at'] = Carbon::parse($segment['departing_at'])->format('D, d M Y');
                    $segment['formatted_departing_hour'] = Carbon::parse($segment['departing_at'])->format('H:i');
                    $segment['formatted_arriving_at'] = Carbon::parse($segment['arriving_at'])->format('D, d M Y');
                    $segment['formatted_arriving_hour'] = Carbon::parse($segment['arriving_at'])->format('H:i');

                    // Recorrer los pasajeros y agregar la información de equipaje
                    foreach ($segment['passengers'] as &$passenger) {

                        // Verificar si los datos del pasajero están presentes
                        if (isset($passenger['title'], $passenger['given_name'], $passenger['family_name'], $passenger['born_on'], $passenger['cabin_class'])) {

                            // Crear un array de equipajes formateados
                            $baggageDetails = [];
                            foreach ($passenger['baggages'] as $baggage) {
                                $baggageDetails[] = $baggage['quantity'] . 'x ' . ucfirst($baggage['type']) . ' bag (' .
                                                ($baggage['dimensions']['length'] ?? 'N/A') . ' + ' .
                                                ($baggage['dimensions']['width'] ?? 'N/A') . ' + ' .
                                                ($baggage['dimensions']['height'] ?? 'N/A') . ' cm, ' .
                                                ($baggage['weight'] ?? 'N/A') . ' kg)';
                            }

                            // Asignamos los detalles del equipaje al pasajero
                            $passenger['baggage_details'] = implode(', ', $baggageDetails);

                            // Recopilamos la información del pasajero y el vuelo
                            $passengerData = [
                                'passenger' => [
                                    'title' => $passenger['title'] ?? 'N/A',
                                    'given_name' => $passenger['given_name'] ?? 'N/A',
                                    'family_name' => $passenger['family_name'] ?? 'N/A',
                                    'born_on' => isset($passenger['born_on']) ? Carbon::parse($passenger['born_on'])->format('d M Y') : 'N/A',
                                    'cabin_class' => $passenger['cabin_class'] ?? 'N/A',
                                ],
                                'baggage_details' => $passenger['baggage_details'] ?? 'N/A',
                                'flight_info' => [
                                    'departing_at' => $segment['formatted_departing_at'] ?? 'N/A',
                                    'arriving_at' => $segment['formatted_arriving_at'] ?? 'N/A',
                                    'flight_number' => $segment['operating_carrier_flight_number'] ?? 'N/A',
                                    'carrier' => $segment['operating_carrier']['name'] ?? 'N/A',
                                ]
                            ];

                            // Guardamos los datos del pasajero con su equipaje en el arreglo
                            $passengersData[] = $passengerData;
                        } else {
                            // Si faltan los datos del pasajero, agregamos valores por defecto
                            $passengersData[] = [
                                'passenger' => [
                                    'title' => 'N/A',
                                    'given_name' => 'N/A',
                                    'family_name' => 'N/A',
                                    'born_on' => 'N/A',
                                    'cabin_class' => 'economy',
                                ],
                                'baggage_details' => 'N/A',
                                'flight_info' => [
                                    'departing_at' => 'N/A',
                                    'arriving_at' => 'N/A',
                                    'flight_number' => 'N/A',
                                    'carrier' => 'N/A',
                                ]
                            ];
                        }
                    }
                }
            }

            return  [
                'data' => $booking_data['data'],
                'passengers_data' => $passengersData
            ];
    }

    /**
     * Booking summary send.
     *
     * Updated at 10/12/2024 (user)
     *
     * @param Request $r Request object
     * @return array
     */
    public function bookingSummarySend(Request $r){
        try{
            Mail::to($r->email)->send(new SendSummary(['tour_id'=>$r->tour_id]));
            $summary= new BookingSummary();
            $summary->fill([
                'tour_id'=>$r->tour_id,
                'email'=>$r->email
            ])->save();
            return response()->json(['success'=>true,'data'=>$summary]);
        }catch(Exception $e){
            return response()->json(['success'=>false,'data'=>$e->getMessage()]);
        }
    }



    /**
     * Booking summary pdf.
     *
     * Updated at 10/12/2024 (user)
     *
     * @param Request $r Request object
     * @return array
     */
    
    public function bookingSummaryPdf(Request $r){
        $tourResponse = (new  ProxyTourRadarController)->show($r->tour_id);
        $tourData = $tourResponse->getData(true);
        $tour=$tourData['data'];

        foreach($tour['destinations']['countries'] as $co){
            $countries[]=$co['country_name'];
        }

        foreach($tour['tour_types'] as $to){
            $tour_types[]=$to['type_name'];
        }

        foreach($tour['guide_languages'] as $text){
            $guide_types[]=$text['name'];
        }
        $countries_d=[
            'countries_text'=>implode(', ',$countries),
            'tour_text'=>implode(', ',$tour_types),
            'guide_text'=>implode(', ',$guide_types),
        ];

        /* return $tour['services']['included'] ; */
        $pdf = Pdf::loadView('emails.send_summary',['tour'=>$tour,'countries_d'=>$countries_d,'services'=>$tour['services']['included'] ])->set_option('isRemoteEnabled', true);
        return $pdf->stream('booking_summary_tour.pdf');
    }

    
    /**
     * Carrier list.
     *
     * Updated at 10/12/2024 (user)
     *
     * @return array
     */
    public function carrierList(){
        try{
            $carriers= Order::select('carrier')->distinct()->get();
            return response()->json(['success' => true, 'data' => $carriers]);
        }catch(Exception $e){
            return response()->json(['success'=>false,'data'=>$e->getMessage()]);
        }
    }

    /**
     * Abandoned cart notification.
     *
     * Updated at 10/12/2024 (user)
     *
     * @param Request $request Request object
     * @return array
     */
    public function abandonedCartNotification(Request $request){

        $user_id = $request->has('userId') ? $request->userId : 0;
        $tour_id = $request->has('tourId') ? $request->tourId : 0;

        if (!$user_id) {
            ApiResponse::error('User Id query parameter is required.');
        }
        if (!$tour_id) {
            ApiResponse::error('Tour Id query parameter is required.');
        }

        $user = User::where('id', $id)->first();
        $tour = Tour::where('tour_id', $tour_id)->first();

        if(!$tour){
            ApiResponse::error('Tour not found.');
        }
        $tour_link = rtrim(config('frontend.url'), '/') . '/tour?tourId=' . $tour_id;
        $emailData = [
            'userName' => $user->name,
            'userEmail' => $user->email,
            'name' => $tour->tour_name,
            'link' => $user->country,
        ];

        Mail::to($user->email)->send(new AbandonedCartMail($emailData));

        return ApiResponse::success([], 'Email notification sent');

    }
}
