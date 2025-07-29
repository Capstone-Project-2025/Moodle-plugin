<?php
defined('MOODLE_INTERNAL') || die();

$capabilities = [
    'qtype/prog:use' => [
        'captype' => 'write',
        'contextlevel' => CONTEXT_MODULE,
        'archetypes' => [
            'teacher' => CAP_ALLOW,
            'editingteacher' => CAP_ALLOW,
            'manager' => CAP_ALLOW
        ]
    ]
];
