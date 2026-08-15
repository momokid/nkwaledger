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

];
