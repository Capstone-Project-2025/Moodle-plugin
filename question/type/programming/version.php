<?php
defined('MOODLE_INTERNAL') || die();

$plugin->component = 'qtype_programming';
$plugin->version = 2025070205;
$plugin->requires = 2022041900;
$plugin->maturity = MATURITY_ALPHA;
$plugin->release = 'v1.0';
$plugin->dependencies = [
    'local_programming' => ANY_VERSION,
];

