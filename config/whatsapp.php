<?php

return [

    /*
    |--------------------------------------------------------------------------
    | WhatsApp Cloud API (Meta)
    |--------------------------------------------------------------------------
    |
    | Credentials for the official Meta WhatsApp Business Cloud API. See
    | https://developers.facebook.com/docs/whatsapp/cloud-api for details.
    | `verify_token` is not issued by Meta — it's an arbitrary string we
    | invent and register in the Meta App's webhook configuration screen,
    | used to confirm webhook subscription requests belong to us.
    |
    */

    'api_version' => env('WHATSAPP_API_VERSION', 'v23.0'),

    'base_url' => env('WHATSAPP_BASE_URL', 'https://graph.facebook.com'),

    'phone_number_id' => env('WHATSAPP_PHONE_NUMBER_ID'),

    'business_account_id' => env('WHATSAPP_BUSINESS_ACCOUNT_ID'),

    'access_token' => env('WHATSAPP_ACCESS_TOKEN'),

    'app_secret' => env('WHATSAPP_APP_SECRET'),

    'verify_token' => env('WHATSAPP_VERIFY_TOKEN'),

];
