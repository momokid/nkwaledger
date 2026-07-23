<?php

use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\RolePermissionController;
use App\Http\Controllers\Admin\UserAccessController;
use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\Auth\ConfirmablePasswordController;
use App\Http\Controllers\Auth\EmailVerificationNotificationController;
use App\Http\Controllers\Auth\EmailVerificationPromptController;
use App\Http\Controllers\Auth\NewPasswordController;
use App\Http\Controllers\Auth\OtpController;
use App\Http\Controllers\Auth\PasswordController;
use App\Http\Controllers\Auth\PasswordResetLinkController;
use App\Http\Controllers\Auth\RegisteredUserController;
use App\Http\Controllers\Auth\SocialAuthController;
use App\Http\Controllers\Auth\VerifyEmailController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', function () {
    return Inertia::render('Welcome', [
        'canLogin'    => Route::has('login'),
        'canRegister' => Route::has('register'),
    ]);
});

Route::get('verify-otp', [OtpController::class, 'create'])->name('otp.create');
Route::post('verify-otp', [OtpController::class, 'store'])->name('otp.store');
Route::post('resend-otp', [OtpController::class, 'resend'])->name('otp.resend');

Route::middleware('guest')->group(function () {
    Route::get('register', [RegisteredUserController::class, 'create'])
        ->name('register');
    Route::post('register', [RegisteredUserController::class, 'store']);

    Route::get('login', [AuthenticatedSessionController::class, 'create'])
        ->name('login');
    Route::post('login', [AuthenticatedSessionController::class, 'store']);
    Route::post('login/otp', [OtpController::class, 'requestLogin'])->name('login.otp');

    Route::get('forgot-password', [PasswordResetLinkController::class, 'create'])
        ->name('password.request');
    Route::post('forgot-password', [PasswordResetLinkController::class, 'store'])
        ->name('password.email');

    Route::get('reset-password/{token}', [NewPasswordController::class, 'create'])
        ->name('password.reset');
    Route::post('reset-password', [NewPasswordController::class, 'store'])
        ->name('password.store');

    Route::get('auth/{provider}', [SocialAuthController::class, 'redirect'])
        ->name('social.redirect')
        ->where('provider', 'google|facebook');
    Route::get('auth/{provider}/callback', [SocialAuthController::class, 'callback'])
        ->name('social.callback')
        ->where('provider', 'google|facebook');
});

Route::middleware('auth')->group(function () {
    Route::get('/dashboard', fn() => redirect()->route('farmer.dashboard'));
    Route::get('/farmer/dashboard', fn() => Inertia::render('Dashboard'))
        ->name('farmer.dashboard');
    Route::get('/agent/dashboard', fn() => Inertia::render('Dashboard'))
        ->name('agent.dashboard');
    Route::get('/vet/dashboard', fn() => Inertia::render('Dashboard'))
        ->name('vet.dashboard');
    Route::get('/adviser/dashboard', fn() => Inertia::render('Dashboard'))
        ->name('adviser.dashboard');
    Route::get('/supplier/dashboard', fn() => Inertia::render('Dashboard'))
        ->name('supplier.dashboard');

    Route::get('/auth/check', fn() => response()->json(['authenticated' => true]));

    Route::get('verify-email', EmailVerificationPromptController::class)
        ->name('verification.notice');
    Route::get('verify-email/{id}/{hash}', VerifyEmailController::class)
        ->middleware(['signed', 'throttle:6,1'])
        ->name('verification.verify');
    Route::post('email/verification-notification', [EmailVerificationNotificationController::class, 'store'])
        ->middleware('throttle:6,1')
        ->name('verification.send');

    Route::get('confirm-password', [ConfirmablePasswordController::class, 'show'])
        ->name('password.confirm');
    Route::post('confirm-password', [ConfirmablePasswordController::class, 'store']);

    Route::put('password', [PasswordController::class, 'update'])->name('password.update');
    Route::post('logout', [AuthenticatedSessionController::class, 'destroy'])
        ->name('logout');
});

Route::middleware(['auth', 'role:admin'])->prefix('admin')->name('admin.')->group(function () {
    Route::get('/dashboard', [DashboardController::class, 'index'])
        ->name('dashboard');

    Route::middleware('access:access-control.manage')->prefix('permissions')->name('permissions.')->group(function () {
        Route::get('/roles', [RolePermissionController::class, 'index'])->name('roles.index');
        Route::get('/users', [UserAccessController::class, 'index'])->name('users.index');
        Route::get('/users/{user}', [UserAccessController::class, 'show'])->name('users.show');

        // sensitive mutations require a recent password confirmation, silently extended by real activity elsewhere in the app
        Route::middleware('password.confirm')->group(function () {
            Route::put('/roles/{role}', [RolePermissionController::class, 'update'])->name('roles.update');
            Route::put('/users/{user}/role', [UserAccessController::class, 'updateRole'])->name('users.role.update');
            Route::post('/users/{user}/grants', [UserAccessController::class, 'storeGrant'])->name('users.grants.store');
            Route::delete('/users/{user}/grants/{permission}', [UserAccessController::class, 'destroyGrant'])->name('users.grants.destroy');
            Route::post('/users/{user}/denials', [UserAccessController::class, 'storeDenial'])->name('users.denials.store');
            Route::delete('/users/{user}/denials/{permission}', [UserAccessController::class, 'destroyDenial'])->name('users.denials.destroy');
        });
    });
});
