<?php

// The admin sidebar. The browser never holds this list: NavigationAccessService::adminMenu()
// filters it down to what the signed-in admin may open and sends the result as a prop.
// `route` is a route name, `icon` is a key the layout maps to an icon, and `ready => false`
// marks a coming-soon placeholder that has no route to be gated on.
return [
    ['label' => 'Dashboard', 'icon' => 'layout-dashboard', 'route' => 'admin.dashboard'],
    ['label' => 'Approvals', 'icon' => 'checklist', 'route' => 'admin.approvals.index', 'badge' => 'approvals'],
    ['label' => 'Records to review', 'icon' => 'checklist', 'route' => 'admin.sync-submissions.index'],
    [
        'label' => 'Farm Setup',
        'icon' => 'plant',
        'children' => [
            ['label' => 'Farm Type Categories', 'route' => 'admin.farm-type-categories.index'],
            ['label' => 'Farm Types', 'route' => 'admin.farm-types.index'],
            ['label' => 'Farmer Groups', 'route' => 'admin.farmer-groups.index'],
            ['label' => 'Farmers Account', 'route' => 'admin.farmers.index'],
        ],
    ],
    [
        'label' => 'Ledger Setup',
        'icon' => 'book',
        'children' => [
            ['label' => 'Ledger Classes', 'route' => 'admin.ledger-classes.index'],
            ['label' => 'Ledger Types', 'route' => 'admin.ledger-types.index'],
            ['label' => 'Ledger Controls', 'route' => 'admin.ledger-controls.index'],
            ['label' => 'Ledger Categories', 'route' => 'admin.ledger-categories.index'],
            ['label' => 'Ledger Subcategories', 'route' => 'admin.ledger-subcategories.index'],
            ['label' => 'Ledger Accounts', 'route' => 'admin.ledger-accounts.index'],
            ['label' => 'Accounting Periods', 'route' => 'admin.accounting-periods.index'],
            ['label' => 'Transaction Templates', 'route' => 'admin.transaction-templates.index'],
        ],
    ],
    [
        'label' => 'Access Control',
        'icon' => 'shield-lock',
        'children' => [
            ['label' => 'Staff Accounts', 'route' => 'admin.staff.index'],
            ['label' => 'Roles & Permissions', 'route' => 'admin.permissions.roles.index'],
            ['label' => 'User Access', 'route' => 'admin.permissions.users.index'],
            ['label' => 'Audit Log', 'route' => 'admin.audit.index'],
        ],
    ],
    [
        'label' => 'Disease & Health Reports',
        'icon' => 'stethoscope',
        'children' => [
            ['label' => 'Waiting for Officer', 'route' => 'admin.disease-reports.index'],
            ['label' => 'Officer Assignments', 'route' => 'admin.officer-assignments.index'],
        ],
    ],
    [
        'label' => 'Marketplace',
        'feature' => 'marketplace',
        'icon' => 'shopping-cart',
        'children' => [
            ['label' => 'Setup: Suppliers', 'route' => 'admin.marketplace.suppliers.index'],
            ['label' => 'Setup: Kiosks', 'route' => 'admin.marketplace.kiosks.index'],
            ['label' => 'Setup: Catalog', 'route' => 'admin.marketplace.catalog.index'],
            ['label' => 'Setup: Settings', 'route' => 'admin.marketplace.settings.index'],
            ['label' => 'Setup: Reports', 'route' => 'admin.marketplace.kiosk-reports.index'],
            ['label' => 'Setup: Categories', 'route' => 'admin.marketplace.categories.index'],
            ['label' => 'Market Center', 'route' => 'market-center.index'],
            ['label' => 'Marketplace Dashboard', 'route' => 'admin.marketplace.dashboard'],
            ['label' => 'Product Analysis', 'ready' => false],
            ['label' => 'Finance', 'ready' => false],
        ],
    ],
];
