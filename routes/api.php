<?php
use App\Http\Controllers\ApiController;
use App\Http\Controllers\PartnerApiController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function(){
    Route::post('/login',[ApiController::class,'login'])->middleware('throttle:10,1');
    Route::middleware(['installed','api.token'])->group(function(){Route::get('/me',[ApiController::class,'me']);Route::get('/work-orders',[ApiController::class,'index']);Route::post('/work-orders',[ApiController::class,'store'])->middleware('throttle:60,1');});
    Route::prefix('partner')->middleware(['installed','partner.api','throttle:120,1'])->group(function(){Route::post('/work-orders',[PartnerApiController::class,'store']);Route::get('/work-orders/{workOrderNumber}',[PartnerApiController::class,'show']);Route::get('/external/{externalReference}',[PartnerApiController::class,'byExternal']);});
});
