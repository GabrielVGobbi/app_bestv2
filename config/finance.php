<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Global company administrators
    |--------------------------------------------------------------------------
    |
    | These roles may operate across every company. Other users must have an
    | explicit row in the company_user pivot before accessing Finance data.
    |
    */
    'global_company_roles' => ['developer', 'admin', 'super-admin'],
];
