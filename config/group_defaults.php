<?php

/*
|--------------------------------------------------------------------------
| Defaults provisioned for every new group
|--------------------------------------------------------------------------
|
| "lookups" are the per-group lookup tables, keyed by table name. Each row is
| inserted with the group's id; extra keys (code, interval_days) map straight
| onto columns. "system_roles" are shared by all groups (group_id is null).
|
*/

return [
    'lookups' => [
        'contribution_frequencies' => [
            ['name' => 'Weekly (Kila wiki)'],
            ['name' => 'Bi-weekly (Kila wiki mbili)'],
            ['name' => 'Monthly (Kila mwezi)'],
            ['name' => 'Quarterly (Kila robo mwaka)'],
            ['name' => 'Annually (Kila mwaka)'],
        ],
        'penalty_types' => [
            ['name' => 'Late Contribution', 'description' => 'Contribution paid after the agreed date.'],
            ['name' => 'Missed Meeting', 'description' => 'Absence from a scheduled group meeting.'],
            ['name' => 'Late Loan Repayment', 'description' => 'Loan installment paid after the due date.'],
            ['name' => 'Misconduct', 'description' => 'Breach of the group constitution (katiba).'],
        ],
        'loan_interest_methods' => [
            ['name' => 'Flat Rate', 'description' => 'Interest charged on the original principal for the whole loan period.', 'code' => 'FLAT'],
            ['name' => 'Reducing Balance', 'description' => 'Interest charged on the outstanding balance.', 'code' => 'REDUCING'],
        ],
        'membership_types' => [
            ['name' => 'Founder Member', 'description' => 'Member who founded the group.'],
            ['name' => 'Ordinary Member', 'description' => 'Fully paid-up member with voting rights.'],
            ['name' => 'Associate Member', 'description' => 'Member without voting rights.'],
        ],
        'payment_methods' => [
            ['name' => 'Cash', 'description' => 'Cash handed to the treasurer.', 'code' => 'CASH'],
            ['name' => 'M-Pesa', 'description' => 'Vodacom M-Pesa mobile money.', 'code' => 'MPESA'],
            ['name' => 'Tigo Pesa', 'description' => 'Tigo Pesa mobile money.', 'code' => 'TIGOPESA'],
            ['name' => 'Airtel Money', 'description' => 'Airtel Money mobile money.', 'code' => 'AIRTELMONEY'],
            ['name' => 'HaloPesa', 'description' => 'Halotel HaloPesa mobile money.', 'code' => 'HALOPESA'],
            ['name' => 'Bank Transfer', 'description' => 'Transfer to or from the group bank account (CRDB / NMB).', 'code' => 'BANK'],
        ],
        'repayment_frequencies' => [
            ['name' => 'Weekly', 'description' => 'Repaid every week.', 'interval_days' => 7],
            ['name' => 'Bi-weekly', 'description' => 'Repaid every two weeks.', 'interval_days' => 14],
            ['name' => 'Monthly', 'description' => 'Repaid every month.', 'interval_days' => 30],
        ],
        'group_expense_categories' => [
            ['name' => 'Bank Charges'],
            ['name' => 'Meeting Venue'],
            ['name' => 'Stationery'],
            ['name' => 'Transport'],
            ['name' => 'Welfare & Condolences'],
            ['name' => 'Training'],
        ],
        'income_categories' => [
            ['name' => 'Registration Fees'],
            ['name' => 'Passbook Sales'],
            ['name' => 'Bank Interest'],
            ['name' => 'Donations & Grants'],
            ['name' => 'Loan Interest'],
        ],
        'fund_types' => [
            ['name' => 'Savings', 'description' => 'Member savings (akiba).', 'code' => 'SAV'],
            ['name' => 'Shares', 'description' => 'Share capital (hisa).', 'code' => 'SHR'],
            ['name' => 'Social Fund', 'description' => 'Welfare fund (mfuko wa jamii).', 'code' => 'SOC'],
            ['name' => 'General Fund', 'description' => 'Operating income and expenses.', 'code' => 'GEN'],
        ],
    ],

    'system_roles' => [
        ['name' => 'Chairperson', 'description' => 'Mwenyekiti: leads the group and approves transactions.'],
        ['name' => 'Vice Chairperson', 'description' => 'Makamu Mwenyekiti: deputises the chairperson.'],
        ['name' => 'Secretary', 'description' => 'Katibu: keeps records and minutes.'],
        ['name' => 'Treasurer', 'description' => 'Mweka Hazina: receives and banks contributions.'],
        ['name' => 'Member', 'description' => 'Mwanachama: ordinary member.'],
    ],
];
