<?php

return [

    // separate from APP_KEY on purpose, rotating the app key must never invalidate stored identity hashes
    'pepper' => env('IDENTITY_PEPPER'),

];
