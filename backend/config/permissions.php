<?php

return [

    'modules' => [
        'farm-types' => [
            'label' => 'Farm Types',
            'actions' => [
                'view' => 'View',
                'create' => 'Add',
                'update' => 'Edit',
                'delete' => 'Delete',
            ],
        ],
        'farmer-groups' => [
            'label' => 'Farmer Groups',
            'actions' => [
                'view' => 'View',
                'create' => 'Add',
                'update' => 'Edit',
                'delete' => 'Delete',
            ],
        ],
        'ledger-accounts' => [
            'label' => 'Ledger Accounts',
            'actions' => [
                'view' => 'View',
                'create' => 'Add',
                'update' => 'Edit',
                'delete' => 'Delete',
            ],
        ],
    ],

    'standalone' => [
        'access-control.manage' => 'Manage Roles & Permissions',
    ],

    'defaults' => [
        'admin' => [
            'farm-types.view',
            'farm-types.create',
            'farm-types.update',
            'farm-types.delete',
            'farmer-groups.view',
            'farmer-groups.create',
            'farmer-groups.update',
            'farmer-groups.delete',
            'ledger-accounts.view',
            'ledger-accounts.create',
            'ledger-accounts.update',
            'ledger-accounts.delete',
        ],
        'agent' => [
            'farm-types.view',
            'farmer-groups.view',
            'ledger-accounts.view',
        ],
    ],

];
