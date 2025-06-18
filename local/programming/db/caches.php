<?php
defined('MOODLE_INTERNAL') || die();

$definitions = [
    'problemtype' => [
        'mode' => cache_store::MODE_APPLICATION,
        'simplekeys' => true,
        'ttl' => 600 // 10 minutes
    ]
];



