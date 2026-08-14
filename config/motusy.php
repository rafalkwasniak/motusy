<?php

return [

    // Temporary request tracing, switched on from .env when a client misbehaves
    // without leaving anything in the log. Off by default and meant to stay that way.
    'diagnostics' => [
        'enabled' => env('API_DIAGNOSTICS', false),
    ],

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

    'meetings' => [
        // One encounter per pair per this window, measured from the meeting itself and
        // reaching both ways. It does double duty: riding together re-detects the same
        // person every few seconds and stays one entry, and passing the same commuter
        // twice in a morning is one event rather than two.
        //
        // Deliberately not a "gap that ends the meeting": passing somebody at 8:00 and
        // again at 10:00 is not two encounters, and a gap rule would record it as such.
        'cooldown_hours' => 6,

        // Detections older than this are dropped. Reports arrive late when the phone
        // had no coverage, but a week-old detection is more likely junk than a ride.
        //
        // Must stay at or below ble.resolvable_after_rotation_hours, or reports the app
        // still considers sendable will hit a token that no longer resolves.
        'max_report_age_hours' => 24,

        // Phone clocks drift. Without this, a phone running two minutes fast has every
        // detection rejected as happening in the future.
        'clock_tolerance_minutes' => 5,

        // Riding in a group means passing several people at once, so the endpoint
        // takes a batch instead of one request per rider.
        'max_batch_size' => 20,

        // Per user, not per IP: a rally sits behind one carrier NAT and an address
        // limit would lock everybody out at once.
        //
        // Needed since a single side started creating meetings on its own: one report
        // now writes into somebody else's history, so the pace has to be bounded.
        'throttle' => [
            'attempts' => 30,
            'decay_minutes' => 1,
        ],
    ],

    'ble' => [
        // 128 bits, hex encoded. Must stay short: a BLE advertisement carries about
        // 31 bytes in total, so a long random string would not fit in the frame.
        'token_bytes' => 16,

        // How long a token is broadcast before a new one takes over. Rotation is what
        // stops the identifier from becoming a lifelong beacon that anyone with a
        // cheap scanner could log without the app.
        'rotation_hours' => 24,

        // A retired token still resolves for this long, so meetings recorded offline
        // and sent late can still be matched to a person.
        //
        // Must stay at or above meetings.max_report_age_hours: a detection the app is
        // still allowed to send has to land on a token that can still be resolved,
        // otherwise reports die in a way the phone cannot predict.
        'resolvable_after_rotation_hours' => 72,

        // A rotation the user asked for is a privacy action: the old token dies on the
        // spot, with no grace at all. Reports already in flight are lost, which is the
        // point of the button.
        'grace_after_manual_rotation' => false,

        // Retired tokens are deleted once they are this old. Nobody reports a meeting
        // from a month ago, and rotating daily would otherwise leave a row per user per
        // day forever.
        'prune_retired_after_days' => 30,

        // One UUID for the whole application, the same for every user. It is public by
        // design and says only "somebody running Motusy is nearby" — never who. The
        // identity sits in the token, which is read over a connection and rotates.
        //
        // iOS in the background advertises neither manufacturer nor service data, so
        // the token cannot travel in the advertisement. A fixed service UUID is what is
        // left: every phone knows what to scan for, and pulls the identity afterwards.
        //
        // Served from here rather than hardcoded in the app so it can be changed without
        // a store release. Changing it makes old and new app versions stop seeing each
        // other, so it is an emergency valve, not a routine knob.
        'service_uuid' => 'F8B8DA1E-F688-4478-B617-91C9BA4B33C5',

        // The GATT characteristic the token is read from. Returned next to the service
        // UUID because changing one without the other would still force a release.
        'characteristic_uuid' => '4438009D-8D2B-4C37-8FF5-E6270D88EB17',
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
