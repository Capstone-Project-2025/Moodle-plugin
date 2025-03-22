<?php
// Prevents direct access to the file for security reasons.
defined('MOODLE_INTERNAL') || die(); 

// Declares the unique name of the plugin. The format is 'local_pluginname' for a local plugin.
$plugin->component = 'local_problems'; 

// Defines the plugin version using the format YYYYMMDDXX.
$plugin->version = 2025031300;  

// Specifies the minimum required Moodle version for this plugin to run.
$plugin->requires = 2021051700; 

// Sets the maturity level of the plugin.
// Possible values: MATURITY_ALPHA, MATURITY_BETA, MATURITY_RC, MATURITY_STABLE.
$plugin->maturity = MATURITY_STABLE; 

// Defines the human-readable release version (e.g., "1.0", "1.1", "2.0").
$plugin->release = '1.0';