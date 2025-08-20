<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Plugin settings for the local_[pluginname] plugin.
 *
 * @package   local_myplugin
 * @copyright Year, You Name <your@email.address>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
require_once(__DIR__ . '/classes/setting_update.php');

defined('MOODLE_INTERNAL') || die();

// Ensure the configurations for this site are set
if ($hassiteconfig) {

    // Create the new settings page
    // - in a local plugin this is not defined as standard, so normal $settings->methods will throw an error as
    // $settings will be null
    $settings = new admin_settingpage('local_dmoj_user_link', 'Capstone project settings');

    // Create
    $ADMIN->add('localplugins', $settings);

    // Add a setting field to the settings for this page
    if ($ADMIN->fulltree) {
        $settings->add(new admin_setting_configtext_notify(
            // This is the reference you will use to your configuration
                'local_dmoj_user_link/dmoj_domain',

            // This is the friendly title for the config, which will be displayed
            'DMOJ Domain',

            // This is helper text for this config field
            'Helper text',

            // This is the default value
            'http://example.com',

            // This is the type of Parameter this config is
            PARAM_URL
        ));
    }
}