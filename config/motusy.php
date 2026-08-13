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

    'uploads' => [
        'disk' => 'public',
        'max_kilobytes' => 5120,

        // The stored extension is derived from these, never from the uploaded file
        // name, so nothing executable can land under the webroot.
        'mimes' => ['jpeg', 'jpg', 'png', 'webp'],

        'avatar_directory' => 'avatars',
        'motorcycle_photo_directory' => 'motorcycles',

        // Everything is re-encoded to one format. Besides cutting the weight, the
        // re-encode strips EXIF, which on phone photos carries the GPS coordinates of
        // the place the picture was taken.
        'format' => 'webp',
        'quality' => 82,

        // Longest edge, in pixels. Aspect ratio is preserved and images smaller than
        // this are left alone rather than upscaled.
        'avatar_max_dimension' => 600,
        'motorcycle_photo_max_dimension' => 1200,
    ],

    'motorcycle' => [
        'brand_max_length' => 60,
        'model_max_length' => 60,
        'color_max_length' => 30,
        'description_max_length' => 1000,
        'min_production_year' => 1900,
    ],

];
