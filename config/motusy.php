<?php

return [

    'auth' => [
        // Brute-force guard on register and login, per IP.
        'throttle' => [
            'attempts' => 10,
            'decay_minutes' => 1,
        ],

        'password_min_length' => 8,

        // Falls back to this when the client sends no device_name. Once devices land
        // in stage 3, tokens are named per device so a single one can be revoked.
        'default_token_name' => 'mobile',
    ],

    'profile' => [
        'nickname_min_length' => 2,
        'nickname_max_length' => 30,
        'bio_max_length' => 500,
        'name_max_length' => 60,
        'phone_max_length' => 20,

        // Stable codes, never shown to the user as-is. The app maps them to labels.
        'genders' => ['male', 'female', 'other'],
    ],

    'motorcycle' => [
        'brand_max_length' => 60,
        'model_max_length' => 60,
        'color_max_length' => 30,
        'description_max_length' => 1000,
        'min_production_year' => 1900,
    ],

];
