<?php

return [
    

    'suffix_name' => [
        'Sr',
        'Jr',
        'II',
        'III',
        'IV',
    ],

    'gender' => [
        0 => 'Male',
        1 => 'Female',
    ],

    'marital_status' => [
        0 => 'Single',
        1 => 'Married',
        2 => 'Divorced',
        3 => 'Widowed',
    ],

    'employment_status' => [
        1 => 'Full Time Permanent',
        2 => 'Full Time Contract',
        3 => 'Full Time Probation',
        4 => 'Part Time Contract',
        5 => 'Freelance',
    ],

    'leave_type' => [
        '1' => 'Full Day',
        '2' => 'Half Day',
        '3' => 'Above a Day',
    ],

    'loan_status' => [
        1 => 'Pending',
        2 => 'Approved',
        3 => 'Disapproved'
    ],

    'attendance_status' => [
        1 => 'Present',
        2 => 'Late',
        3 => 'No sched',
        4 => 'Pending',
        5 => 'Disapproved',
    ],

    'holiday_percentage' => [
        'double' => [
            'regular' => 2,
            'overtime' => 2.90,
            'restday' => 2.6,
            'restday_ot' => 3.38,
        ],
        'legal' => [
            'regular' => 1,
            'overtime' => 1.60,
            'restday' => 1.3,
            'restday_ot' => 1.69,
        ],
        'special' => [
            'regular' => .3,
            'overtime' => .69,
            'restday' => .2,
            'restday_ot' => .26,
        ],
    ]
];