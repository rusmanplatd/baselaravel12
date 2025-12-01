<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Default Hash Driver
    |--------------------------------------------------------------------------
    |
    | This option controls the default hash driver that will be used to hash
    | passwords for your application. By default, the argon2id algorithm is
    | used; however, you remain free to modify this option if you wish.
    |
    | Supported: "argon2id", "argon2i", "bcrypt"
    |
    */

    'driver' => env('HASH_DRIVER', 'argon2id'),

    /*
    |--------------------------------------------------------------------------
    | Argon2id Options
    |--------------------------------------------------------------------------
    |
    | Here you may specify the configuration options that should be used when
    | passwords are hashed using the Argon2id algorithm. These will allow you
    | to control the amount of time and memory used to generate the hash.
    |
    */

    'argon2id' => [
        'memory' => env('ARGON2ID_MEMORY', 65536), // 64 MB
        'time' => env('ARGON2ID_TIME', 4),         // 4 passes
        'threads' => env('ARGON2ID_THREADS', 3),   // 3 threads
    ],

    /*
    |--------------------------------------------------------------------------
    | Argon2i Options
    |--------------------------------------------------------------------------
    |
    | Here you may specify the configuration options that should be used when
    | passwords are hashed using the Argon2i algorithm. These will allow you
    | to control the amount of time and memory used to generate the hash.
    |
    */

    'argon2i' => [
        'memory' => env('ARGON2I_MEMORY', 65536),
        'time' => env('ARGON2I_TIME', 4),
        'threads' => env('ARGON2I_THREADS', 3),
    ],

    /*
    |--------------------------------------------------------------------------
    | Bcrypt Options
    |--------------------------------------------------------------------------
    |
    | Here you may specify the configuration options that should be used when
    | passwords are hashed using the Bcrypt algorithm. This will allow you
    | to control the amount of time it takes to hash the given password.
    |
    */

    'bcrypt' => [
        'rounds' => env('BCRYPT_ROUNDS', 12),
        'verify' => env('BCRYPT_VERIFY', true),
    ],

];
