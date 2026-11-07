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

    /*
    | The starting values of a new group's settings (group_settings). Lookup ids
    | (contribution frequency, interest method, penalty type) are resolved by
    | ProvisionGroupDefaults from the lookups above.
    */
    'settings' => [
        'minimum_contribution' => 20000,
        'maximum_contribution' => 100000,
        'share_price' => 5000,
        'minimum_shares' => 1,
        'maximum_shares' => 40,
        'default_loan_interest_rate' => 10,
        'default_interest_period' => 'per_loan',
        'maximum_loan_multiplier' => 2,
        'default_loan_duration_months' => 6,
        'loan_grace_period_days' => 7,
        'late_payment_penalty_value' => 5,
        'loan_guarantors_required' => true,
        'withdrawal_approvals_required' => true,
        'allow_partial_contributions' => false,
        'allow_multiple_loans' => false,
    ],

    /*
    | Funds created for every new group: [code, name, description, minimum
    | contribution, mandatory, distributable, loanable]. Mandatory funds use the
    | monthly contribution frequency.
    */
    'funds' => [
        ['SAV', 'Akiba (Savings)', 'Mandatory monthly member savings.', 20000, true, true, true],
        ['SHR', 'Hisa (Shares)', 'Share capital bought by members.', 0, false, true, false],
        ['SOC', 'Mfuko wa Jamii (Social Fund)', 'Welfare fund for funerals, weddings and emergencies.', 5000, true, false, false],
        ['GEN', 'Mfuko Mkuu (General Fund)', 'Operating income and expenses of the group.', 0, false, true, false],
    ],

    /*
    | Fine types: [code, name, description, default amount, calculation type].
    */
    'fine_types' => [
        ['MISSED_CONTRIB', 'Kukosa mchango', 'Fine for not paying the monthly contribution.', 2000, 'fixed'],
        ['LATE_MEETING', 'Kuchelewa kikao', 'Fine for arriving late to a meeting.', 1000, 'fixed'],
        ['ABSENT_MEETING', 'Kukosa kikao', 'Fine for missing a meeting without permission.', 2000, 'fixed'],
        ['LATE_LOAN', 'Kuchelewa kurejesha mkopo', 'Percentage of the installment charged when it is overdue.', 5, 'percentage'],
    ],

    'loan_types' => ['Emergency (Dharura)', 'Development (Maendeleo)', 'Education (Elimu)'],

    /*
    | Loan products, all on the Savings fund with flat interest and monthly
    | repayment: [code, name, minimum months, maximum months, minimum amount, maximum amount].
    */
    'loan_product_interest_rate' => 10,
    'loan_products' => [
        ['DHARURA', 'Mkopo wa Dharura', 1, 3, 40000, 500000],
        ['MAENDELEO', 'Mkopo wa Maendeleo', 3, 12, 100000, 1000000],
        ['ELIMU', 'Mkopo wa Elimu', 2, 12, 100000, 600000],
    ],

    'system_roles' => [
        ['name' => 'Chairperson', 'description' => 'Mwenyekiti: leads the group and approves transactions.'],
        ['name' => 'Vice Chairperson', 'description' => 'Makamu Mwenyekiti: deputises the chairperson.'],
        ['name' => 'Secretary', 'description' => 'Katibu: keeps records and minutes.'],
        ['name' => 'Treasurer', 'description' => 'Mweka Hazina: receives and banks contributions.'],
        ['name' => 'Member', 'description' => 'Mwanachama: ordinary member.'],
    ],
];
