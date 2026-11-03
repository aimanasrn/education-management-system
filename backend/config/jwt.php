<?php

return [
    'secret' => env('JWT_SECRET'),
    'issuer' => env('JWT_ISSUER', 'ems-api'),
    'audience' => env('JWT_AUDIENCE', 'ems-api'),
    'ttl_minutes' => (int) env('JWT_TTL_MINUTES', 15),
];
