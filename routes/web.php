<?php

use App\Http\Controllers\ActivityController;
use App\Http\Controllers\Admin\ProjectSesSuppressionController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\ProjectSesSuppressionUserController;
use App\Http\Controllers\SesSuppressionChooserController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\PasswordResetController;
use App\Http\Controllers\SendTestController;
use App\Http\Controllers\SocialAuthController;
use App\Http\Controllers\TwoFactorAuthenticationController;
use App\Http\Controllers\UserController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::get('login', [AuthController::class, 'login'])->name('login');
Route::post('login', [AuthController::class, 'login'])->name('login');

Route::get('forgot-password', [PasswordResetController::class, 'showForgotForm'])->name('password.request');
Route::post('forgot-password', [PasswordResetController::class, 'sendResetLink'])->name('password.email');
Route::get('reset-password/{token}', [PasswordResetController::class, 'showResetForm'])->name('password.reset');
Route::post('reset-password', [PasswordResetController::class, 'reset'])->name('password.update');

Route::post('webhook/{token}', App\Http\Controllers\SesWebhookController::class);

// Invitation routes (public)
Route::get('invitation/accept/{token}', [App\Http\Controllers\InvitationController::class, 'show'])->name('invitation.show');
Route::post('invitation/accept', [App\Http\Controllers\InvitationController::class, 'accept'])->name('invitation.accept');

// SSO routes (public)
Route::get('auth/{provider}/redirect', [SocialAuthController::class, 'redirect'])
    ->where('provider', 'google|microsoft')
    ->name('social.redirect');
Route::get('auth/{provider}/callback', [SocialAuthController::class, 'callback'])
    ->where('provider', 'google|microsoft')
    ->name('social.callback');

Route::middleware(['throttle:two-factor-challenge'])->group(function () {
    Route::get('two-factor/challenge', [TwoFactorAuthenticationController::class, 'showChallenge'])
        ->name('two-factor.challenge');
    Route::post('two-factor/challenge', [TwoFactorAuthenticationController::class, 'confirmChallenge'])
        ->name('two-factor.challenge.confirm');
    Route::post('two-factor/challenge/cancel', [TwoFactorAuthenticationController::class, 'cancelChallenge'])
        ->name('two-factor.challenge.cancel');
});

Route::middleware(['auth'])->group(function () {
    Route::get('logout', [AuthController::class, 'logout'])->name('logout');
    Route::get('two-factor/setup', [TwoFactorAuthenticationController::class, 'showSetup'])->name('two-factor.setup');
    Route::post('two-factor/setup', [TwoFactorAuthenticationController::class, 'confirmSetup'])
        ->middleware('throttle:two-factor-setup')
        ->name('two-factor.setup.confirm');
});

Route::group([
    'middleware' => ['auth', 'two-factor.enrolled'],
], function () {
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard.index');
    Route::get('dashboard/api', [DashboardController::class, 'jsApi'])->name('dashboard.api');
    Route::get('activity', [ActivityController::class, 'index'])->name('activity');
    Route::get('activity/list/api', [ActivityController::class, 'listApi']);
    Route::get('activity/details/api', [ActivityController::class, 'detailsApi']);
    Route::get('activity/export', [ActivityController::class, 'export']);
    Route::get('reports', [App\Http\Controllers\ReportsController::class, 'index'])->name('reports.index');
    Route::get('reports/emails', [App\Http\Controllers\ReportsController::class, 'emailsReport'])->name('reports.emails');
    Route::get('reports/recipients', [App\Http\Controllers\ReportsController::class, 'recipientsReport'])->name('reports.recipients');
    Route::get('reports/senders', [App\Http\Controllers\ReportsController::class, 'sendersReport'])->name('reports.senders');
    Route::get('reports/bounced-recipients', [App\Http\Controllers\ReportsController::class, 'bouncedRecipientsReport'])->name('reports.bounced-recipients');
    Route::get('reports/unsubscribes', [App\Http\Controllers\ReportsController::class, 'unsubscribesReport'])->name('reports.unsubscribes');
    Route::get('send_test', [SendTestController::class, 'index'])->name('send_test');
    Route::post('send_test/send', [SendTestController::class, 'send'])->name('send_test.send');
    Route::any('edit_profile', [UserController::class, 'edit'])->name('edit_profile');

    Route::get('two-factor/recovery-codes', [TwoFactorAuthenticationController::class, 'showRecoveryCodes'])
        ->name('two-factor.recovery-codes');

    // Project request routes (available to all authenticated users)
    Route::get('project-requests/create', [App\Http\Controllers\ProjectRequestController::class, 'create'])->name('project-requests.create');
    Route::post('project-requests', [App\Http\Controllers\ProjectRequestController::class, 'store'])->name('project-requests.store');

    Route::get('ses-suppression', [SesSuppressionChooserController::class, 'index'])->name('ses-suppression.chooser');
    Route::middleware(['throttle:60,1'])->group(function () {
        Route::get('projects/{project}/ses-suppression', [ProjectSesSuppressionUserController::class, 'index'])->name('ses-suppression.index');
        Route::post('projects/{project}/ses-suppression', [ProjectSesSuppressionUserController::class, 'store'])->name('ses-suppression.store');
        Route::delete('projects/{project}/ses-suppression', [ProjectSesSuppressionUserController::class, 'destroy'])->name('ses-suppression.destroy');
    });

});

// Admin Routes
Route::middleware(['auth', 'two-factor.enrolled', 'admin'])->prefix('admin')->name('admin.')->group(function () {
    Route::get('projects/search-users', [App\Http\Controllers\Admin\ProjectManagementController::class, 'searchUsers'])->name('projects.search-users');
    Route::middleware(['throttle:60,1'])->group(function () {
        Route::get('projects/{project}/ses-suppression', [ProjectSesSuppressionController::class, 'index'])->name('projects.ses-suppression.index');
        Route::post('projects/{project}/ses-suppression', [ProjectSesSuppressionController::class, 'store'])->name('projects.ses-suppression.store');
        Route::delete('projects/{project}/ses-suppression', [ProjectSesSuppressionController::class, 'destroy'])->name('projects.ses-suppression.destroy');
    });
    Route::resource('projects', App\Http\Controllers\Admin\ProjectManagementController::class);
    Route::resource('users', App\Http\Controllers\Admin\UserManagementController::class);
    Route::post('users/invite', [App\Http\Controllers\Admin\UserManagementController::class, 'invite'])->name('users.invite');
});

// Project request management routes (super admin only)
Route::middleware(['auth', 'two-factor.enrolled'])->prefix('project-requests')->name('project-requests.')->group(function () {
    Route::get('/', [App\Http\Controllers\ProjectRequestController::class, 'index'])->name('index');
    Route::get('/{projectRequest}', [App\Http\Controllers\ProjectRequestController::class, 'show'])->name('show');
    Route::post('/{projectRequest}/approve', [App\Http\Controllers\ProjectRequestController::class, 'approve'])->name('approve');
    Route::post('/{projectRequest}/reject', [App\Http\Controllers\ProjectRequestController::class, 'reject'])->name('reject');
});
