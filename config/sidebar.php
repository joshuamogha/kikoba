<?php

/*
|--------------------------------------------------------------------------
| Backend Sidebar Menu
|--------------------------------------------------------------------------
|
| Each menu mirrors a folder in app/Models and each item mirrors a model in
| that folder. Icons are Bootstrap Icons class names. Set an item's "route"
| to a named route once its page exists; until then it renders as "#".
| A menu's "color" class tints its icon and all of its submenu icons.
|
*/

return [
    'dashboard' => [
        'label' => 'Dashboard',
        'icon' => 'bi-speedometer2',
        'color' => 'text-info',
        'route' => 'dashboard',
    ],

    /*
    | Data that belongs to no group. Only shown to platform admins
    | (the "platform-admin" gate).
    */
    'platform' => [
        'label' => 'Platform Admin',
        'icon' => 'bi-shield-lock-fill',
        'color' => 'text-danger',
        'items' => [
            ['label' => 'Users', 'icon' => 'bi-person-gear', 'route' => 'users.index'],
            ['label' => 'Groups', 'icon' => 'bi-people', 'route' => 'groups.index', 'params' => ['scope' => 'all']],
            ['label' => 'Group Types', 'icon' => 'bi-diagram-3', 'route' => 'group-types.index'],
            ['label' => 'Relations', 'icon' => 'bi-heart', 'route' => 'relations.index'],
            ['label' => 'System Roles', 'icon' => 'bi-person-lock', 'route' => null],
        ],
    ],

    'my_groups' => [
        'label' => 'My Groups',
        'icon' => 'bi-people-fill',
        'color' => 'text-warning',
        'route' => 'groups.index',
    ],

    'menus' => [
        [
            'label' => 'User Management',
            'icon' => 'bi-people-fill',
            'color' => 'text-primary',
            'items' => [
                ['label' => 'Members', 'icon' => 'bi-person-lines-fill', 'route' => 'members.index'],
            ],
        ],
        [
            'label' => 'Payment Management',
            'icon' => 'bi-cash-coin',
            'color' => 'text-success',
            'items' => [
                ['label' => 'Financial Cycles', 'icon' => 'bi-calendar3', 'route' => 'financial-cycles.index'],
                ['label' => 'Group Funds', 'icon' => 'bi-piggy-bank', 'route' => 'group-funds.index'],
                ['label' => 'Contributions', 'icon' => 'bi-wallet2', 'route' => 'contributions.index'],
                ['label' => 'Contribution Calendar', 'icon' => 'bi-calendar-check', 'route' => 'contribution-calendar.index'],
                ['label' => 'Payment Methods', 'icon' => 'bi-credit-card', 'route' => 'payment-methods.index'],
                ['label' => 'Payment Transactions', 'icon' => 'bi-arrow-left-right', 'route' => 'payment-transactions.index'],
                ['label' => 'Income', 'icon' => 'bi-graph-up-arrow', 'route' => 'payment-pages.show', 'params' => ['page' => 'income']],
                ['label' => 'Expenses', 'icon' => 'bi-graph-down-arrow', 'route' => 'payment-pages.show', 'params' => ['page' => 'expenses']],
                ['label' => 'Fines', 'icon' => 'bi-exclamation-octagon', 'route' => 'payment-pages.show', 'params' => ['page' => 'fines']],
            ],
        ],
        [
            'label' => 'Loan Management',
            'icon' => 'bi-bank',
            'color' => 'text-warning',
            'items' => [
                ['label' => 'Loan Types', 'icon' => 'bi-bookmarks', 'route' => 'loan-types.index'],
                ['label' => 'Loan Products', 'icon' => 'bi-box-seam', 'route' => 'loan-products.index'],
                ['label' => 'Loan Ranges', 'icon' => 'bi-bar-chart-steps', 'route' => 'loan-ranges.index'],
                ['label' => 'Loan Applications', 'icon' => 'bi-file-earmark-text', 'route' => 'loan-applications.index'],
                ['label' => 'Loan Guarantors', 'icon' => 'bi-shield-check', 'route' => 'loan-guarantors.index'],
                ['label' => 'Loans', 'icon' => 'bi-bank2', 'route' => 'loans.index'],
                ['label' => 'Loan Installments', 'icon' => 'bi-calendar2-week', 'route' => 'loan-installments.index'],
                ['label' => 'Loan Repayments', 'icon' => 'bi-cash-stack', 'route' => 'loan-repayments.index'],
            ],
        ],
        [
            'label' => 'Share Management',
            'icon' => 'bi-pie-chart-fill',
            'color' => 'text-teal',
            'items' => [
                ['label' => 'Share Outs', 'icon' => 'bi-pie-chart', 'route' => 'share-outs.index'],
                ['label' => 'Member Share Outs', 'icon' => 'bi-person-vcard', 'route' => 'member-share-outs.index'],
                ['label' => 'Share Out Payments', 'icon' => 'bi-cash', 'route' => 'share-out-payments.index'],
                ['label' => 'Member Share Transactions', 'icon' => 'bi-currency-exchange', 'route' => 'member-share-transactions.index'],
            ],
        ],
        [
            'label' => 'Reports',
            'icon' => 'bi-file-earmark-bar-graph',
            'color' => 'text-danger',
            'items' => [
                ['label' => 'Member Statement', 'icon' => 'bi-person-lines-fill', 'route' => 'reports.member-statement'],
                ['label' => 'Monthly Shares', 'icon' => 'bi-pie-chart', 'route' => 'reports.monthly-shares'],
                ['label' => 'Monthly Contributions', 'icon' => 'bi-wallet2', 'route' => 'reports.monthly-contributions'],
                ['label' => 'Loan Statement', 'icon' => 'bi-bank', 'route' => 'reports.loan-statement'],
                ['label' => 'Cash Reconciliation', 'icon' => 'bi-calculator', 'route' => 'reports.cash-reconciliation'],
            ],
        ],
        [
            'label' => 'Setting Management',
            'icon' => 'bi-gear-fill',
            'color' => 'text-purple',
            'items' => [
                ['label' => 'General', 'icon' => 'bi-sliders', 'route' => 'setting-pages.show', 'params' => ['page' => 'general']],
                ['label' => 'Finance', 'icon' => 'bi-percent', 'route' => 'setting-pages.show', 'params' => ['page' => 'finance']],
                ['label' => 'Fine Types', 'icon' => 'bi-exclamation-octagon', 'route' => 'setting-pages.show', 'params' => ['page' => 'fines']],
            ],
        ],
    ],
];
