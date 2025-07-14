<?php

use App\Http\Controllers\ActivityController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\SendTestController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\WebHookController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});



Route::get('login',[AuthController::class,'login'])->name('login');
Route::post('login',[AuthController::class,'login'])->name('login');
Route::post('webhook/{token}', App\Http\Controllers\SesWebhookController::class);



Route::group([
    'middleware' => ['auth']
], function () {

Route::get('logout',[AuthController::class,'logout'])->name('logout');
Route::get('/',[DashboardController::class,'index'])->name('dashboard.index');
Route::get('dashboard/api',[DashboardController::class,'jsApi'])->name('dashboard.api');
Route::get('activity',[ActivityController::class,'index'])->name('activity');
Route::get('activity/list/api',[ActivityController::class,'listApi']);
Route::get('activity/details/api',[ActivityController::class,'detailsApi']); 
Route::get('activity/export',[ActivityController::class,'export']); 
Route::get('send_test',[SendTestController::class,'index'])->name('send_test');
Route::post('send_test/send',[SendTestController::class,'send'])->name('send_test.send');
Route::any('edit_profile',[UserController::class,'edit'])->name('edit_profile');

});

// Admin Routes
Route::middleware(['auth', 'admin'])->prefix('admin')->name('admin.')->group(function () {
    Route::resource('projects', App\Http\Controllers\Admin\ProjectManagementController::class);
    Route::resource('users', App\Http\Controllers\Admin\UserManagementController::class);
});