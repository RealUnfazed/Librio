<?php

return [
    // Who may create an account: open | invite | closed
    'registration' => env('LIBRARY_REGISTRATION', 'invite'),

    // How PDFs reach the browser:
    //   proxy     = your server fetches the file from Postfile, decrypts it and streams it (default; Postfile's CDN has no CORS)
    //   encrypted = browser downloads the ciphertext straight from the CDN (only for hosts that send CORS headers)
    'delivery' => env('LIBRARY_DELIVERY', 'proxy'),

    // 32 random bytes, base64. MUST be identical everywhere that shares the database.
    'master_key' => env('LIBRARY_MASTER_KEY'),

    // Text drawn faintly across every page of the reader:
    //   domain    = your site's domain, e.g. librio.unfazed.ir (default)
    //   domain-id = domain + the reader's user number (lets you trace a leaked copy without showing an email)
    //   user      = the reader's email + number
    'watermark' => env('LIBRARY_WATERMARK', 'domain'),

    'chunk_size' => 262144, // 256 KiB of plaintext per encrypted chunk
    'upload_piece' => 3 * 1024 * 1024, // browser -> server upload piece (multiple of chunk_size, < Vercel's 4.5 MB request cap)

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
