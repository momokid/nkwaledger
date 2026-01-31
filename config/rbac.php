<?php

return [

    'roles' => [
        'admin',
        'supervisor',
        'farmer',
    ],

    'permissions' => [

        'admin' => [
            'manage_users',
            'view_ledger',
            'record_income',
            'record_expense',
            'approve_transaction',
            'edit_transaction',
        ],

        'supervisor' => [
            'view_ledger',
            'approve_transaction',
        ],

        'farmer' => [
            'record_income',
            'record_expense',
            'view_own_ledger',
        ],

    ],
];
