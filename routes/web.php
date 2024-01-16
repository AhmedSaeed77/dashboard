<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Dashboard\Home\HomeController;
use App\Http\Controllers\Dashboard\Auth\AuthController;
use App\Http\Controllers\Dashboard\UserController;
use App\Http\Controllers\Dashboard\AdminTicketController;
use App\Http\Controllers\Dashboard\AdminTicketInfoController;
use App\Http\Controllers\Dashboard\UserTicketController;
use App\Http\Controllers\Dashboard\TicketRejectController;
use Mcamara\LaravelLocalization\Facades\LaravelLocalization;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "web" middleware group. Make something great!
|
*/

Route::group( [
        'prefix' => LaravelLocalization::setLocale(),
        'middleware' => [ 'localeSessionRedirect', 'localizationRedirect', 'localeViewPath' ],
    ], function () {

    Route::group(['prefix' => 'auth', 'as' => 'auth.'], function () {
        Route::get('login', [AuthController::class, '_login'])->name('_login');
        Route::post('login', [AuthController::class, 'login'])->name('login');
        Route::get('logout', [AuthController::class, 'logout'])->name('logout');
    });

    Route::group(['middleware' => 'auth'], function () {

        Route::get('setting', [SettingController::class , 'getpage'])->name('setting');
    });

    Route::get('/', [HomeController::class, 'index'])->name('/');

});
