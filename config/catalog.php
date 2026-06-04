<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Private catalog data
    |--------------------------------------------------------------------------
    |
    | Raw phone records are runtime data, not public source code. Keep them in
    | ignored private storage or point this setting at an external directory.
    |
    */
    'phone_data_path' => env('PHONE_DATA_PATH') ?: storage_path('app/private/phone-data'),
];
