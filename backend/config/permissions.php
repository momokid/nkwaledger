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
        // farmer accounts are never removed, only disabled, so there is no delete action
        'farmers' => [
            'label' => 'Farmers',
            'actions' => [
                'view' => 'View',
                'create' => 'Register',
                'update' => 'Edit',
                // opens credit scoring and bank facing reports, so it stands apart from editing
                'verify' => 'Verify identity',
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
        'accounting-periods' => [
            'label' => 'Accounting Periods',
            'actions' => [
                'view' => 'View',
                'create' => 'Add',
                'close' => 'Close',
                // reopening changes a period reports were already built from
                'reopen' => 'Reopen',
            ],
        ],
        'transaction-templates' => [
            'label' => 'Transaction Templates',
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
            'farm-type-categories.view',
            'farm-type-categories.create',
            'farm-type-categories.update',
            'farm-type-categories.delete',
            'farmers.view',
            'farmers.create',
            'farmers.update',
            'farmers.verify',
            'staff.view',
            'staff.create',
            'staff.update',
            'staff.delete',
            'audit.view',
            'accounting-periods.view',
            'accounting-periods.create',
            'accounting-periods.close',
            'accounting-periods.reopen',
            'transaction-templates.view',
            'transaction-templates.create',
            'transaction-templates.update',
            'transaction-templates.delete',
        ],
        'agent' => [
            'farm-types.view',
            'farmer-groups.view',
            'ledger-accounts.view',
            'farm-type-categories.view',
            'transaction-templates.view',
            'farmers.view',
            'farmers.create',
            'farmers.update',
        ],
    ],

];
