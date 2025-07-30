<?php

defined('MOODLE_INTERNAL') || die();

$plugin->component = 'local_prog';
$plugin->version   = 2025061909;      // Format YYYYMMDDXX
$plugin->requires  = 2021051700;      // Compatible à partir de Moodle 3.11
$plugin->maturity  = MATURITY_STABLE;
$plugin->release   = '1.0';

$plugin->dependencies = [
    //'local_oauth' => 2023091301, // https://github.com/projectestac/moodle-local_oauth
];