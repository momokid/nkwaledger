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
                // an agent's own narrower view: only groups that hold one of their assigned
                // farmers, and full details only for those farmers - never the admin "view"
                'view-own' => 'View groups with my farmers',
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
                // capture and approval are separate: an agent submits a farmer's document for
                // their assigned farmers, only an admin (farmers.verify) ever approves it
                'kyc-submit' => 'Submit identity documents',
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
        // offline records held back for an admin's decision
        'sync-submissions' => [
            'label' => 'Offline Records',
            'actions' => [
                'approve' => 'Approve a held record',
                'reject' => 'Reject a held record',
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
        // the curated Market Center homepage rows - spans kiosk products and produce
        // listings both, admin-only, same shape as farm-type-categories
        'marketplace-categories' => [
            'label' => 'Marketplace Categories',
            'actions' => [
                'view' => 'View',
                'create' => 'Add',
                'update' => 'Edit',
                'delete' => 'Delete',
            ],
        ],
        // the admin-wide, unscoped visibility list of every produce sale in the system -
        // deliberately its own permission, never produce-listings.view, which an agent
        // and a farmer both legitimately hold for their own scoped listings/sales and
        // must never double as a key to this system-wide page (see the Sept 2026
        // privilege-escalation investigation: Admin\ProduceSaleController::index() has
        // no per-farmer scoping at all, unlike the approvals/farmers admin pages)
        'marketplace-produce-sales' => [
            'label' => 'Marketplace Produce Sales (admin visibility)',
            'actions' => [
                'view' => 'View every produce sale',
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
            'sync-submissions.approve',
            'sync-submissions.reject',
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
            'marketplace-categories.view',
            'marketplace-categories.create',
            'marketplace-categories.update',
            'marketplace-categories.delete',
            'marketplace-produce-sales.view',
        ],
        // farm-type-categories.view, transaction-templates.view, ledger-accounts.view,
        // farmer-groups.view and farmers.verify are deliberately NOT granted here: no
        // agent-facing route reads them, and the admin pages they gate are additionally
        // behind role:admin. farm-types.view IS an agent permission (decided Sept 2026);
        // the admin-only farm-types.create/update/delete stay with the admin role. An
        // agent instead gets farmers.kyc-submit (submit, never approve) and
        // farmer-groups.view-own (only groups holding one of their assigned farmers)
        'agent' => [
            'farm-types.view',
            'farmers.view',
            'farmers.create',
            'farmers.update',
            'farmers.kyc-submit',
            'farmer-groups.view-own',
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
        // a vet only ever sees the reports routed to them, never anyone else's -
        // marketplace-browse.view here only ever reaches a kiosk's own storefront
        // page from Market Center; ordering is never granted, since neither role has
        // a FarmerProfile to charge a purchase against
        'vet' => [
            'disease-reports.view',
            'disease-reports.respond',
            'marketplace-browse.view',
        ],
        'adviser' => [
            'disease-reports.view',
            'disease-reports.respond',
            'marketplace-browse.view',
        ],
        // suppliers are role-gated everywhere else in this app (role:supplier
        // middleware, never access:), but browsing the kiosk marketplace as a buyer
        // is a real permission check, so this is the one place a supplier needs an
        // entry here at all
        'supplier' => [
            'marketplace-browse.view',
            'marketplace-browse.order',
        ],
    ],

];
