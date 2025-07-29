<?php
defined('MOODLE_INTERNAL') || die();

$capabilities = [
    'local/prog:viewstats' => [
        'captype' => 'read',
        'contextlevel' => CONTEXT_COURSE,
        'archetypes' => [
            'teacher' => CAP_ALLOW,
            'manager' => CAP_ALLOW
        ]
    ],
    'local/prog:viewproblems' => [
        'captype' => 'read',
        'contextlevel' => CONTEXT_COURSE,
        'archetypes' => [
            'teacher' => CAP_ALLOW,
            'manager' => CAP_ALLOW
        ]
    ]
];


