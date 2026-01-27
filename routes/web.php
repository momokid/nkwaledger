<?php

use App\Http\Controllers\ProfileController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Auth\OtpAuthenticationController;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/dashboard', function () {
    return view('dashboard');
})->middleware(['auth', 'verified'])->name('dashboard');

Route::middleware(['throttle:otp-request-phone', 'throttle:otp-request-ip'])
    ->post('/auth/otp/request', [OtpAuthenticationController::class, 'requestOtp']);

Route::middleware(['throttle:otp-verify-phone'])
    ->post('/auth/otp/verify', [OtpAuthenticationController::class, 'verifyOtp']);

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

require __DIR__.'/auth.php';
