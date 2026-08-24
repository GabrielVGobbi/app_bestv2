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

    /* Payloads are retained encrypted; deletion is intentionally outside FIN2. */
    'bank_statement_payload_retention_days' => (int) env('FINANCE_BANK_PAYLOAD_RETENTION_DAYS', 365),
];
