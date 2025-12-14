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
Route::get('reports',[App\Http\Controllers\ReportsController::class,'index'])->name('reports.index');
Route::get('reports/emails',[App\Http\Controllers\ReportsController::class,'emailsReport'])->name('reports.emails');
Route::get('reports/recipients',[App\Http\Controllers\ReportsController::class,'recipientsReport'])->name('reports.recipients');
Route::get('reports/senders',[App\Http\Controllers\ReportsController::class,'sendersReport'])->name('reports.senders'); 
Route::get('send_test',[SendTestController::class,'index'])->name('send_test');
Route::post('send_test/send',[SendTestController::class,'send'])->name('send_test.send');
Route::any('edit_profile',[UserController::class,'edit'])->name('edit_profile');

// Project request routes (available to all authenticated users)
Route::get('project-requests/create', [App\Http\Controllers\ProjectRequestController::class, 'create'])->name('project-requests.create');
Route::post('project-requests', [App\Http\Controllers\ProjectRequestController::class, 'store'])->name('project-requests.store');

});

// Admin Routes
Route::middleware(['auth', 'admin'])->prefix('admin')->name('admin.')->group(function () {
    Route::resource('projects', App\Http\Controllers\Admin\ProjectManagementController::class);
    Route::get('projects/search-users', [App\Http\Controllers\Admin\ProjectManagementController::class, 'searchUsers'])->name('projects.search-users');
    Route::resource('users', App\Http\Controllers\Admin\UserManagementController::class);
});

// Project request management routes (super admin only)
Route::middleware(['auth'])->prefix('project-requests')->name('project-requests.')->group(function () {
    Route::get('/', [App\Http\Controllers\ProjectRequestController::class, 'index'])->name('index');
    Route::get('/{projectRequest}', [App\Http\Controllers\ProjectRequestController::class, 'show'])->name('show');
    Route::post('/{projectRequest}/approve', [App\Http\Controllers\ProjectRequestController::class, 'approve'])->name('approve');
    Route::post('/{projectRequest}/reject', [App\Http\Controllers\ProjectRequestController::class, 'reject'])->name('reject');
});