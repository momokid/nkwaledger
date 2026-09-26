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
        // pens, plots and ponds, plus what is in them
        'farm-units' => [
            'label' => 'Farm Units',
            'actions' => [
                'view' => 'View',
                'create' => 'Add',
                'update' => 'Edit',
                // someone has been to the farm and seen the pen
                'approve' => 'Approve a unit',
                // someone has counted what is in it
                'confirm' => 'Confirm a count',
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
        // one page listing everything waiting on somebody
        'approvals' => [
            'label' => 'Approvals',
            'actions' => [
                'view' => 'View the queue',
            ],
        ],
        // records are never edited or removed, only cancelled by a correction
        'transactions' => [
            'label' => 'Transactions',
            'actions' => [
                'view' => 'View',
                'create' => 'Record',
                // asking and agreeing stay apart, so one person can never do both
                'reverse-request' => 'Ask to cancel a record',
                'reverse-approve' => 'Agree to a cancellation',
            ],
        ],
        // a health/disease issue on a farm unit, routed to a vet or adviser
        'disease-reports' => [
            'label' => 'Disease & Health Reports',
            'actions' => [
                // a farmer's own reports, an agent's farmers' reports, or an officer's assigned queue —
                // never every report in the system, which is what "manage" is for below
                'view' => 'View',
                'create' => 'Report a problem',
                'respond' => 'Respond to a report',
                // the admin queue of reports with no officer assigned yet — deliberately
                // its own action, so it is never granted just by holding the personal "view"
                'manage' => 'View the unassigned queue',
            ],
        ],
        // links an agent to the vets/advisers who handle their farmers' reports
        'officer-assignments' => [
            'label' => 'Officer Assignments',
            'actions' => [
                'view' => 'View',
                'create' => 'Add',
                'delete' => 'Remove',
            ],
        ],
        'marketplace-settings' => [
            'label' => 'Marketplace Settings',
            'actions' => [
                'view' => 'View',
                'update' => 'Edit',
            ],
        ],
        'marketplace-suppliers' => [
            'label' => 'Marketplace Suppliers',
            'actions' => [
                'view' => 'View',
                'suspend' => 'Suspend or restore',
            ],
        ],
        'marketplace-kiosks' => [
            'label' => 'Marketplace Kiosks',
            'actions' => [
                'view' => 'View',
                'approve' => 'Approve a 2nd kiosk',
                'suspend' => 'Suspend or restore',
            ],
        ],
        'marketplace-catalog' => [
            'label' => 'Marketplace Catalog',
            'actions' => [
                'view' => 'View catalog and supplier stock',
                'create' => 'Seed a catalog product',
                'merge' => 'Merge duplicate records',
            ],
        ],
        'marketplace-browse' => [
            'label' => 'Marketplace Browsing',
            'actions' => [
                'view' => 'Browse kiosks',
                'order' => 'Place, receive, and review orders',
            ],
        ],
        // posting/managing a farmer's own produce for sale - buying one is open to
        // any authenticated account and is never gated by this module
        'produce-listings' => [
            'label' => 'Produce Listings',
            'actions' => [
                'view' => 'View listings',
                'create' => 'Post a listing',
                // an agent vouching for a sale for credit eligibility - admin only ever
                // needs view above, never an approval action of its own
                'co-confirm' => 'Co-confirm a sale',
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
            'farm-units.view',
            'farm-units.create',
            'farm-units.update',
            'farm-units.approve',
            'farm-units.confirm',
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
            'transactions.view',
            'transactions.create',
            'transactions.reverse-request',
            'transactions.reverse-approve',
            'approvals.view',
            'disease-reports.manage',
            'officer-assignments.view',
            'officer-assignments.create',
            'officer-assignments.delete',
            'marketplace-settings.view',
            'marketplace-settings.update',
            'marketplace-suppliers.view',
            'marketplace-suppliers.suspend',
            'marketplace-kiosks.view',
            'marketplace-kiosks.approve',
            'marketplace-kiosks.suspend',
            'marketplace-catalog.view',
            'marketplace-catalog.create',
            'marketplace-catalog.merge',
            'produce-listings.view',
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
            // inspecting pens and counting stock is field work, so agents hold all of it
            'farm-units.view',
            'farm-units.create',
            'farm-units.update',
            'farm-units.approve',
            'farm-units.confirm',
            'transactions.view',
            'transactions.create',
            'transactions.reverse-request',
            'approvals.view',
            // an agent can see a report's progress, and may submit one for a farmer too
            'disease-reports.view',
            'disease-reports.create',
            // agent already holds every other permission a farmer has, as a superset -
            // this keeps that invariant, and lets an agent browse and order alongside a farmer too
            'marketplace-browse.view',
            'marketplace-browse.order',
            'produce-listings.view',
            'produce-listings.create',
            'produce-listings.co-confirm',
        ],
        // a farmer keeps their own books, and can see what is on their own farm
        'farmer' => [
            'transactions.view',
            'transactions.create',
            'transactions.reverse-request',
            'farm-units.view',
            'disease-reports.view',
            'disease-reports.create',
            'marketplace-browse.view',
            'marketplace-browse.order',
            'produce-listings.view',
            'produce-listings.create',
        ],
        // a vet only ever sees the reports routed to them, never anyone else's
        'vet' => [
            'disease-reports.view',
            'disease-reports.respond',
        ],
        'adviser' => [
            'disease-reports.view',
            'disease-reports.respond',
        ],
    ],

];
