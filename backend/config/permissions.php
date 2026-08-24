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
        'farm-type-categories' => [
            'label' => 'Farm Type Categories',
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
        // covers agent, vet, adviser and supplier accounts, which only an invite can create
        'staff' => [
            'label' => 'Staff Accounts',
            'actions' => [
                'view' => 'View',
                'create' => 'Invite',
                'update' => 'Enable or disable',
                'delete' => 'Cancel invitation',
            ],
        ],
        // reading the trail is its own privilege, separate from managing anything
        'audit' => [
            'label' => 'Audit Log',
            'actions' => [
                'view' => 'View',
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
            'farm-type-categories.view',
            'farm-type-categories.create',
            'farm-type-categories.update',
            'farm-type-categories.delete',
            'staff.view',
            'staff.create',
            'staff.update',
            'staff.delete',
            'audit.view',
        ],
        'agent' => [
            'farm-types.view',
            'farmer-groups.view',
            'ledger-accounts.view',
            'farm-type-categories.view',
        ],
    ],

];
