<?php

use App\Http\Controllers\ActionLogController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\DestinationController;
use App\Http\Controllers\StripeController;
use App\Http\Controllers\SyncController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Citycontroller;
use App\Http\Controllers\CountryController;
use App\Http\Controllers\NaturalDestinationController;
use App\Http\Controllers\ReverseProxyController;
use App\Http\Controllers\TourCitiesController;
use App\Http\Controllers\TourController;
use App\Http\Controllers\TourCountriesController;
use App\Http\Controllers\TourNaturalDestinationController;
use App\Http\Controllers\ProxyTourRadarController;
use App\Http\Controllers\TourRadarController;
use App\Http\Controllers\ProxyKiwiController;
use App\Http\Controllers\DuffelApiController;
use App\Http\Controllers\EnquiryController;
use App\Http\Controllers\VerificationController;
use App\Http\Controllers\GustavoDuffelController;
use App\Http\Controllers\JobsController;
use App\Http\Controllers\OperatorsController;
use App\Http\Controllers\PackageController;
use App\Http\Controllers\NewPackageController;
use App\Http\Controllers\TourIdController;
use App\Http\Controllers\OrderController;
use App\Http\Controllers\TravelersController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\WishlistController;
use App\Http\Controllers\SystemUserController;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use App\Http\Controllers\AirportController;
use App\Http\Controllers\Controller;
use App\Http\Controllers\PushNotificationsController;
use App\Http\Controllers\ValidatorController;
use App\Http\Controllers\NezasaController;
use Dedoc\Scramble\Scramble;
use App\Http\Controllers\PreviewMailController;
use App\Http\Controllers\PreviewInvoiceController;
use App\Http\Controllers\SnapshotController;

Route::get('snapshots', [SnapshotController::class, 'index']);
Route::get('duffel-cancel-v2',[DuffelApiController::class, 'flightCancelV2']);
Route::get('/preview/invoice', PreviewInvoiceController::class);

Route::middleware(['auth:sanctum'])->group(function () {
    Route::resource('admin-destinations', DestinationController::class);
    Route::get('test', [AuthController::class, 'test']);
});
Route::get('/sanitizedfetch', [GustavoDuffelController::class, 'fetchSanitized']);
Route::get('/proxy/fetch', [GustavoDuffelController::class, 'fetch']);
Route::get('airlines', [DuffelApiController::class, 'getAirline']);
Route::post('search_youtube', [DestinationController::class, 'searchYTApi']);
Route::post('login', [AuthController::class, 'login']);
Route::post('import-cities', [Citycontroller::class, 'import']);
Route::post('email-verification/code', [VerificationController::class, 'store']);
Route::post('email-verification/verified', [VerificationController::class, 'verified']);
Route::post('/validate-email-reoon', [VerificationController::class, 'validateEmailReoon']);
Route::post('import-countries', [CountryController::class, 'import']);
Route::post('import-natural_destinations', [NaturalDestinationController::class, 'import']);
Route::resource('cities', Citycontroller::class);
Route::get('selection', [Citycontroller::class, 'selectiontable']);
Route::resource('countries', CountryController::class);
Route::get('get-destinations', [Citycontroller::class, 'destinations']);
Route::get('get_destination_guide', [DestinationController::class, 'getDestinationGuide']);
Route::get('get_unsplash_gallery', [DestinationController::class, 'getUnsplashGallery']);
Route::resource('natural_destinations', NaturalDestinationController::class);
Route::post('register', [AuthController::class, 'register']);
Route::post('test', [Controller::class, 'Test']);
Route::get('location-proxy', [ReverseProxyController::class, 'proxyLocation']);
Route::get('tour/{id}', [ProxyTourRadarController::class, 'show']);
Route::get('destinations', [Citycontroller::class, 'DestinatioCityCountryNaturalDestination']);
Route::get('codes', [Citycontroller::class, 'codes']);
Route::get('departures', [ProxyTourRadarController::class, 'departures']);
Route::get('departure', [ProxyTourRadarController::class, 'departure']);
Route::get('departuredb', [ProxyTourRadarController::class, 'departuredb']);
Route::get('prices', [ProxyTourRadarController::class, 'prices']);
Route::get('operator-booking-fields', [ProxyTourRadarController::class, 'bookingFields']);
Route::get('bookings-list', [ProxyTourRadarController::class, 'bookingsList']);
Route::post('bookings-create', [ProxyTourRadarController::class, 'bookingsStore']);
Route::get('tour-radar-destinations', [ProxyTourRadarController::class, 'destinations']);
Route::get('search-flights', [ProxyKiwiController::class, 'searchFlights']);
Route::get('check-flights', [ProxyKiwiController::class, 'checkFlights']);
Route::get('save-booking', [ProxyKiwiController::class, 'saveBooking']);
Route::get('confirm-payment', [ProxyKiwiController::class, 'confirmPayment']);
Route::get('confirm-payment-zooz', [ProxyKiwiController::class, 'confirmPaymentZooz']);
Route::resource('tour_cities', TourCitiesController::class);
Route::resource('tours', TourController::class);
Route::get('show-tours', [TourController::class, 'show']);
Route::get('carrier-list', [TourController::class, 'carrierList']);
Route::get('tours-text', [TourController::class, 'getText']);
Route::get('show-type', [TourController::class, 'show_type']);
Route::resource('tour_countries', TourCountriesController::class);
Route::resource('tour-natural-destinations', TourNaturalDestinationController::class);
Route::get('tour-type-list', [TourNaturalDestinationController::class,'Type']);
Route::get('duffel/create-request-get-offers', [DuffelApiController::class, 'createRequestGetOffers']);

Route::get('duffel/get-offer-by-id', [DuffelApiController::class, 'getOfferById']);
Route::get('duffel/sort-offer', [DuffelApiController::class, 'getOffer']);
Route::get('duffel/get-order-by-id', [DuffelApiController::class, 'getOrderById']);
Route::get('duffel/get-request-by-id', [DuffelApiController::class, 'getRequestById']);

Route::get('/duffel-api/offer-requests', [GustavoDuffelController::class, 'offerRequests']);


// Route::post('/book-package', [PackageController::class, 'createCheckoutSession']);
Route::post('/book-package-v2', [NewPackageController::class, 'createCheckoutSession']);
Route::get('filterdepartures', [TourRadarController::class, 'getMultipleDeparturesByTours']);
Route::get('filterdeparturesdb', [TourRadarController::class, 'getMultipleDeparturesOnlyDb']);
Route::get('/tour-ids', [TourIdController::class, 'index']);

Route::get('/travelers', [TravelersController::class, 'getTravelers']);
Route::get('/showtravelers', [TravelersController::class, 'show']);
Route::post('/write-travelers', [TravelersController::class, 'writeTravelers']);

Route::post('travelers/update-mail-preferences/{user}', [TravelersController::class, 'updateMailPreferences']);

Route::post('/write-orders', [OrderController::class, 'store']);
Route::get('/orders', [OrderController::class, 'getOrders']);
Route::get('/admin-orders', [OrderController::class, 'adminOrders']);
Route::get('/orders/{booking_id}', [OrderController::class, 'getOrderWithTravelers']);

Route::get('/users', [UserController::class, 'getUserById']);
Route::get('/users-history', [UserController::class, 'UserHistory']);
Route::post('/users-travelers', [UserController::class, 'editTraveler']);
Route::post('/users-pass', [UserController::class, 'changePassword']);
Route::post('/change-password', [UserController::class, 'changePasswordValidation']);
Route::delete('/user/{user_id}', [UserController::class, 'deleteUser']);
Route::get('/users-wishlist', [UserController::class, 'getWishlist']);
Route::post('/push_notifications_register', [PushNotificationsController::class, 'registerGravitecSub']);
Route::post('/send_push_notification', [PushNotificationsController::class, 'sendPushNotification']);
Route::post('/send_ac_notification', [TourController::class, 'abandonedCartNotification']);

Route::post('/contact', [UserController::class, 'Contac']);
Route::get('/show-contact', [UserController::class, 'showContac']);

Route::get('/wishlist', [WishlistController::class, 'indexByUser']);
Route::get('/wishlists-check-traveler', [WishlistController::class, 'travelerID']);
Route::get('/wishlists', [WishlistController::class, 'show']);
Route::post('/wishlists-add', [WishlistController::class, 'store']);
Route::delete('/wishlists/{wishlist_id}', [WishlistController::class, 'delete']);
Route::post('/wishlist-delete-by-tour', [WishlistController::class, 'deleteByTourId']);
Route::get('/get-all-countries', [CountryController::class, 'getAllCountries']);

Route::get('/orders-all', [OrderController::class, 'index']);
Route::get('/orders-csv', [OrderController::class, 'ordersCsv']);
Route::get('users-orders-csv', [UserController::class, 'getUsersOrdersCsv']);
Route::get('/order/{id}', [OrderController::class, 'getOrder']);
Route::post('/admin-reports', [OrderController::class, 'adminReports']);

Route::post('/add-users', [SystemUserController::class, 'createUser']);
Route::get('/get-users', [SystemUserController::class, 'getUsers']);
Route::get('/validate-email', [SystemUserController::class, 'validateEmail']);
Route::delete('/delete-users', [SystemUserController::class, 'deleteUsers']);
Route::patch('/active-desactive-user', [SystemUserController::class, 'activeDesactiveUsers']);


Route::resource('jobs', JobsController::class);
/* Route::resource('roles', RolesController::class); */

Route::get('/traveler-data', [TravelersController::class, 'getTravelerData']);

Route::put('/travelers/{id}', [TravelersController::class, 'update']);
Route::delete('/travelers/{id}', [TravelersController::class, 'destroy']);

Route::get('/users-with-orders', [UserController::class, 'getUsersWithOrders']);
Route::resource('operators', OperatorsController::class);
Route::get('operators-list', [OperatorsController::class,'operatorsList']);
Route::get('operators-import', [OperatorsController::class, 'import']);
Route::get('tours-text', [OperatorsController::class, 'text']);

Route::get('cities-c', [Citycontroller::class, 'cities']);
Route::get('countries-filter', [CountryController::class, 'getCountries']);

Route::get('/email-tour-details', [TourController::class, 'emailTDetails']);
Route::get('/email-booking-confirmation', [TourController::class, 'emailTDetails']);

Route::get('/bookingconfirmation', [PreviewMailController::class, 'bookingConfirmation']);

Route::get('/bookingcancellation', [PreviewMailController::class, 'cancelConfirmation']);

Route::get('destinationsV2', [Citycontroller::class, 'destinationsV2']);

Route::get('duffel/get-seats', [DuffelApiController::class, 'getSeats']);

Route::post('/stripe/webhook', [StripeController::class, 'handleWebhook']);

Route::get('/stripe', [StripeController::class, 'getPaymentIntentFromQuery'])->name('stripe.query');

Route::resource('action-logs', ActionLogController::class);

Route::get('boooking-email',[TourController::class,'emailBookTest']);
/* Route::get('boooking-email',[TourController::class,'bookEmail']); */

Route::post('pass-email',[UserController::class,'sendEmailPass']);

Route::get('boooking-pdf',[TourController::class,'pdfOrder']);

Route::post('/add-enquiry', [EnquiryController::class, 'create']);

Route::post('/add-enquiery-test', [EnquiryController::class, 'createTestLisboa']);

Route::match( ['get','post'],'boooking-summary',[TourController::class,'bookingSummarySend']);

Route::get('boooking-summary-pdf',[TourController::class,'bookingSummaryPdf']);

;

Route::post('google-register', [AuthController::class, 'googleRegister']);

Route::get('duffel-cancel-check',[DuffelApiController::class, 'flightCancel']);
Route::post('duffel-cancel-confirm',[DuffelApiController::class, 'confirmCancel']);

Route::post('/checkout', [NewPackageController::class, 'checkoutWebhook']);

Route::get('/sync/page/{page}', [SyncController::class, 'syncPage']);
Route::get('/sync/pages', [SyncController::class, 'syncPages']);

Route::get('/logs', function () {
    // Path to the Laravel log file
    $path = storage_path('logs/laravel.log');

    // Check if the file exists
    if (!File::exists($path)) {
        abort(404, 'Log file not found');
    }

    // Get the contents of the log file
    $logs = File::get($path);

    // Optional: Limit the number of lines for large log files
    $lines = collect(explode("\n", $logs))->reverse()->take(100)->reverse()->implode("\n");

    // Return the log content as plain text
    return response($lines, 200, ['Content-Type' => 'text/plain']);
});

Route::get('/backlog', function () {
    // Path to the Laravel log file
    $path = storage_path('logs/backlog.log');

    // Check if the file exists
    if (!File::exists($path)) {
        abort(404, 'Log file not found');
    }

    // Get the contents of the log file
    $logs = File::get($path);

    // Optional: Limit the number of lines for large log files
    $lines = collect(explode("\n", $logs))->reverse()->take(100)->reverse()->implode("\n");

    // Return the log content as plain text
    return response($lines, 200, ['Content-Type' => 'text/plain']);
});

Route::get('/process', function () {
    // Path to the Laravel log file
    $path = storage_path('logs/process.log');

    // Check if the file exists
    if (!File::exists($path)) {
        abort(404, 'Log file not found');
    }

    // Get the contents of the log file
    $logs = File::get($path);

    // Optional: Limit the number of lines for large log files
    $lines = collect(explode("\n", $logs))->reverse()->take(100)->reverse()->implode("\n");

    // Return the log content as plain text
    return response($lines, 200, ['Content-Type' => 'text/plain']);
});

Route::get('/process-pending-attempts', function () {
    Artisan::call('process:pending-attempts');

    return response()->json([
        'message' => 'process:pending-attempts executed',
        'output' => Artisan::output(),
    ]);
});

Route::get('/sync_tours', function () {
    // Path to the Laravel log file
    $path = storage_path('logs/sync_tours.log');

    // Check if the file exists
    if (!File::exists($path)) {
        abort(404, 'Log file not found');
    }

    // Get the contents of the log file
    $logs = File::get($path);

    // Optional: Limit the number of lines for large log files
    $lines = collect(explode("\n", $logs))->reverse()->take(100)->reverse()->implode("\n");

    // Return the log content as plain text
    return response($lines, 200, ['Content-Type' => 'text/plain']);
});

Route::get('/tours_featured', function () {
    // Path to the Laravel log file
    $path = storage_path('logs/tours_featured.log');

    // Check if the file exists
    if (!File::exists($path)) {
        abort(404, 'Log file not found');
    }

    // Get the contents of the log file
    $logs = File::get($path);

    // Optional: Limit the number of lines for large log files
    $lines = collect(explode("\n", $logs))->reverse()->take(100)->reverse()->implode("\n");

    // Return the log content as plain text
    return response($lines, 200, ['Content-Type' => 'text/plain']);
});

Route::get('traveler_id',[TravelersController::class, 'traveler_id']);

Route::get('status',[NewPackageController::class, 'checkBookingStatus']);

Route::get('tourradar-status/{id}',[TourRadarController::class, 'checkBooking']);

Route::get('/airports', [AirportController::class, 'getAirports']);

Route::get('/recover-pass',[AuthController::class, 'recoverPass']);

Route::get('/check-token-pass',[AuthController::class, 'checkToken']);

/* Route::get('/login', function () {
    return response()->json(['message' => 'Please log in.'], 401);
})->name('login'); */
//Route::middleware('auth:sanctum')->post('logout', [AuthController::class, 'logout']);

Route::post('/logout', [AuthController::class, 'logout'])->middleware('auth:sanctum');

Route::get('/validator', [ValidatorController::class, 'validatePhone']);

Route::post('/add-contact', [UserController::class, 'addContact']);
Route::post('/get-contact', [UserController::class, 'getContact']);
Route::post('/check-contact',[UserController::class, 'checkContact']);

Route::get('/get-tickets',[TourController::class, 'bookingTickets']);
Route::get('/get-nezasa-itinerary',[NezasaController::class, 'getItineraryTour']);
Route::get('/get-nezasa-locations',[NezasaController::class, 'getNezasaLocations']);
Route::get('/get-db-locations',[NezasaController::class, 'getLocationsFromDatabase']);

Scramble::registerUiRoute(path: '/documentation')->name('api.scramble.docs.ui');

Route::get('/test-speed', function () {
    $inicio = microtime(true);

    // Respuesta rápida de prueba
    $response = response()->json(['message' => 'API rápida']);

    $fin = microtime(true);
    $tiempo = $fin - $inicio;

    return response()->json(['message' => 'API rápida', 'tiempo' => $tiempo]);
});
