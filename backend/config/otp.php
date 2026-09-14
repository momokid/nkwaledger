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

    // comma-separated phone numbers that get a fixed, known code ("000000") instead of a
    // real SMS, so a developer can log in locally without a working SMS provider. Only
    // ever takes effect in local/testing environments — see OtpService::testBypassCode()
    'test_phones' => env('OTP_TEST_PHONES', ''),

];
