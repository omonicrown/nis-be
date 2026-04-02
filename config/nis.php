<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Cron Secret Token
    |--------------------------------------------------------------------------
    |
    | This token secures the /api/cron/* endpoints so only your cron service
    | (e.g. cron-job.org) can trigger them. Generate one with:
    |   php -r "echo bin2hex(random_bytes(32));"
    |
    */

    'cron_token' => env('CRON_SECRET_TOKEN'),

    /*
    |--------------------------------------------------------------------------
    | Paystack Configuration
    |--------------------------------------------------------------------------
    */

    'paystack' => [
        'public_key'  => env('PAYSTACK_PUBLIC_KEY'),
        'secret_key'  => env('PAYSTACK_SECRET_KEY'),
        'payment_url' => env('PAYSTACK_PAYMENT_URL', 'https://api.paystack.co'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Default Import Password
    |--------------------------------------------------------------------------
    |
    | Default password assigned to members imported from the Excel file.
    | Members should be prompted to change this on first login.
    |
    */

    'default_import_password' => env('DEFAULT_IMPORT_PASSWORD', 'NISMember@2026'),

];
