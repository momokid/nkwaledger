<?php

// The credit score (task 6 of credit-score-and-loan-use). Every number here is provisional: the owner asked to tune the
// weights and bands once there is real data. Change them here and nowhere else.
return [
    // the last N calendar months, this one included
    'window_months' => 12,

    // fewer months with a record than this and there is no score yet
    'min_months' => 3,

    // points each pillar can earn before the pillars with nothing to measure are left out and the rest scale up to 100
    'weights' => [
        'purchases' => 30,
        'cash' => 30,
        'credit' => 30,
        'loan_use' => 10,
    ],

    // the credit pillar is multiplied by this while any loan is overdue, and by 0 while any loan is in default
    'overdue_factor' => 0.5,

    // a new loan is not judged on how its money was used until it is this many days old
    'loan_use_min_age_days' => 7,

    // band name => lowest score in it
    'bands' => [
        'Building' => 0,
        'Fair' => 40,
        'Good' => 60,
        'Strong' => 80,
    ],
];
