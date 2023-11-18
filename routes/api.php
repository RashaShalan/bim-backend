<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

use App\Http\Controllers\api\AuthController;
use App\Http\Controllers\api\ApiController;
/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/
Route::controller(AuthController::class)->group(function(){
    //Route::post('register', 'register');
    Route::post('login', 'login');
});



Route::group(['middleware' => 'auth:sanctum'], function () {
    Route::post('addTransaction', [ApiController::class, 'add_transaction']);
    Route::post('addPayment', [ApiController::class, 'add_payment']);
    Route::get('viewTransaction', [ApiController::class, 'view_transaction']);
    Route::get('monthlyReport/{from?}/{to?}', [ApiController::class, 'monthly_report']);

    
});

