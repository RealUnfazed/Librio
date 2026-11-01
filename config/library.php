<?php

return [
    // Who may create an account: open | invite | closed
    'registration' => env('LIBRARY_REGISTRATION', 'invite'),

    // How PDFs reach the browser:
    //   encrypted = browser downloads ciphertext from the Postfile CDN and decrypts it (use on Vercel)
    //   proxy     = Laravel streams decrypted bytes itself, CDN URL never leaves the server (use on a VPS/local)
    'delivery' => env('LIBRARY_DELIVERY', 'encrypted'),

    // 32 random bytes, base64. MUST be identical everywhere that shares the database.
    'master_key' => env('LIBRARY_MASTER_KEY'),

    'chunk_size' => 262144, // 256 KiB of plaintext per encrypted chunk

    // On Vercel: create the tables and the first admin automatically on the first visit.
    'auto_setup' => env('LIBRARY_AUTO_SETUP', false),
    'admin' => [
        'email' => env('LIBRARY_ADMIN_EMAIL'),
        'password' => env('LIBRARY_ADMIN_PASSWORD'),
    ],

    'force_https' => env('LIBRARY_FORCE_HTTPS', false),
    'trust_proxies' => env('LIBRARY_TRUST_PROXIES', false),

    'postfile' => [
        'key' => env('POSTFILE_API_KEY'),
        'api_url' => env('POSTFILE_API_URL', 'https://postfile.net'),
        'upload_url' => env('POSTFILE_UPLOAD_URL', 'https://postfile.net'),
    ],
];
