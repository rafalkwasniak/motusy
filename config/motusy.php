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
        'nickname_max_length' => 30,
        'bio_max_length' => 500,
    ],

];
