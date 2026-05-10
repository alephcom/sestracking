<?php

return [

    /*
    |--------------------------------------------------------------------------
    | IAM actions (Amazon SES account-level suppression list, SES v2 API)
    |--------------------------------------------------------------------------
    |
    | Canonical list for UI and documentation. Policy Resource is typically "*".
    |
    */
    'iam_actions' => [
        'ses:PutSuppressedDestination' => 'Auto-push on bounce/complaint (Option 1) and manual add (Option 3)',
        'ses:ListSuppressedDestinations' => 'View suppression list (Option 2) and daily sync into the database',
        'ses:GetSuppressedDestination' => 'Optional; available on SesSuppressionService for single-address lookups',
        'ses:DeleteSuppressedDestination' => 'Remove an address from the list (Option 3)',
    ],

];
