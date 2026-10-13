<?php
return [
    'locales' => ['en' => 'English', 'it' => 'Italiano', 'es' => 'Español'],
    'country_locales' => ['IT' => 'it', 'ES' => 'es'],
    'timezone' => 'Europe/London',
    'accept_inquiries' => (bool) env('LONDRA_ACCEPT_INQUIRIES', false),
    'send_notifications' => (bool) env('LONDRA_SEND_NOTIFICATIONS', false),
    'publication_approved' => (bool) env('LONDRA_PUBLICATION_APPROVED', false),
    'privacy_approved' => (bool) env('LONDRA_PRIVACY_APPROVED', false),
    'indexing_enabled' => (bool) env('LONDRA_INDEXING_ENABLED', false),
    'country_lookup' => (bool) env('LONDRA_COUNTRY_LOOKUP', false),
    'contact' => ['phone' => '+44 7438 700287', 'tel' => '+447438700287', 'email' => 'info@londratransfers.co.uk', 'instagram' => 'londra_transfers_ltd'],
    'colours' => ['black' => '#000000', 'onyx' => '#0B0B0B', 'white' => '#FFFFFF', 'porcelain' => '#F7F7F5', 'gold' => '#BDA177', 'champagne' => '#F1ECD9'],
];
