<?php
return [
    'default' => env('MAIL_MAILER', 'smtp'),
    'mailers' => [
        'smtp' => ['transport' => 'smtp', 'scheme' => env('MAIL_SCHEME', 'smtp'), 'host' => env('MAIL_HOST'), 'port' => (int) env('MAIL_PORT', 587), 'username' => env('MAIL_USERNAME'), 'password' => env('MAIL_PASSWORD'), 'timeout' => 15, 'local_domain' => parse_url(env('APP_URL', ''), PHP_URL_HOST)],
        'array' => ['transport' => 'array'],
    ],
    'from' => ['address' => env('MAIL_FROM_ADDRESS', 'info@londratransfers.co.uk'), 'name' => 'Londra Transfers'],
    'reply_to' => ['address' => env('MAIL_REPLY_TO_ADDRESS', 'info@londratransfers.co.uk'), 'name' => 'Londra Transfers'],
];
