<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Seed passwords
    |--------------------------------------------------------------------------
    |
    | Initial passwords for the two seeded users (SPEC §3). There is no public
    | registration route, so seeding is the only way an account is created.
    | Override these in the environment before seeding a deployed instance -
    | the fallback is a local-development convenience only.
    |
    */

    'seed_passwords' => [
        'jasper' => env('SEED_JASPER_PASSWORD', 'password'),
        'stephan' => env('SEED_STEPHAN_PASSWORD', 'password'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Countries
    |--------------------------------------------------------------------------
    |
    | ISO 3166-1 alpha-2 codes offered when creating a customer. A customer's
    | country matters because VAT treatment depends on it - but the treatment
    | itself is always chosen by hand on the quote, never derived from this
    | list (SPEC §2). EU members first, then common non-EU markets.
    |
    */

    'countries' => [
        'NL' => 'Netherlands',
        'BE' => 'Belgium',
        'DE' => 'Germany',
        'FR' => 'France',
        'AT' => 'Austria',
        'BG' => 'Bulgaria',
        'HR' => 'Croatia',
        'CY' => 'Cyprus',
        'CZ' => 'Czechia',
        'DK' => 'Denmark',
        'EE' => 'Estonia',
        'FI' => 'Finland',
        'GR' => 'Greece',
        'HU' => 'Hungary',
        'IE' => 'Ireland',
        'IT' => 'Italy',
        'LV' => 'Latvia',
        'LT' => 'Lithuania',
        'LU' => 'Luxembourg',
        'MT' => 'Malta',
        'PL' => 'Poland',
        'PT' => 'Portugal',
        'RO' => 'Romania',
        'SK' => 'Slovakia',
        'SI' => 'Slovenia',
        'ES' => 'Spain',
        'SE' => 'Sweden',
        'GB' => 'United Kingdom',
        'CH' => 'Switzerland',
        'NO' => 'Norway',
        'IS' => 'Iceland',
        'US' => 'United States',
        'CA' => 'Canada',
        'AU' => 'Australia',
        'NZ' => 'New Zealand',
        'ZA' => 'South Africa',
        'JP' => 'Japan',
        'SG' => 'Singapore',
    ],

];
