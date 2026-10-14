<?php

return [

    // sms costs real money, so both the number and the source are capped
    'throttle' => [

        'login' => [
            'per_phone' => (int) env('OTP_LOGIN_PER_PHONE', 3),
            'per_ip'    => (int) env('OTP_LOGIN_PER_IP', 10),
        ],

        'resend' => [
            'per_phone' => (int) env('OTP_RESEND_PER_PHONE', 3),
            'per_ip'    => (int) env('OTP_RESEND_PER_IP', 10),
        ],

    ],

    // a signed-in user resetting their PIN on this phone: each request is an sms, so it is capped
    'pin_reset' => [
        'per_user' => (int) env('OTP_PIN_RESET_PER_USER', 6),
        'per_ip' => (int) env('OTP_PIN_RESET_PER_IP', 20),
        'confirm_per_user' => (int) env('OTP_PIN_RESET_CONFIRM_PER_USER', 12),
        'cooldown_seconds' => (int) env('OTP_PIN_RESET_COOLDOWN', 60),
    ],

    // comma-separated phone numbers that get a fixed, known code ("000000") instead of a
    // real SMS, so a developer can log in locally without a working SMS provider. Only
    // ever takes effect in local/testing environments — see OtpService::testBypassCode()
    'test_phones' => env('OTP_TEST_PHONES', ''),

];
