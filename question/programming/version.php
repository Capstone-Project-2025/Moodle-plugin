<?php
defined('MOODLE_INTERNAL') || die();

$plugin->component = 'qtype_programming';
$plugin->version = 20250702011;
$plugin->requires = 2021051700;
$plugin->maturity = MATURITY_ALPHA;
$plugin->settings = true;
$plugin->release = 'v1.0';
$plugin->dependencies = [
    'local_dmoj_user_link' => ANY_VERSION,
];

