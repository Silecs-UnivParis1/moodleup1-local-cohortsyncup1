<?php

$tasks = [
    [
        'classname' => 'local_cohortsyncup1\task\sync_cohorts',
        'blocking' => 0,
        'minute' => '0',
        'hour' => '3',
        'day' => '*',
        'month' => '*',
        'dayofweek' => '*',
    ],
    [
        'classname' => 'local_cohortsyncup1\task\sync_cohorts_from_users',
        'blocking' => 0,
        'minute' => '20',
        'hour' => '*',
        'day' => '*',
        'month' => '*',
        'dayofweek' => '*',
    ],
    [
        'classname' => 'local_cohortsyncup1\task\sync_cohorts_initial',
        'blocking' => 0,
        'minute' => '40',
        'hour' => '3',
        'day' => '*',
        'month' => '*',
        'dayofweek' => '0',
    ]
];
