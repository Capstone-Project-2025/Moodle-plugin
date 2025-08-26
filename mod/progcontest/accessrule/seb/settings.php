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
 * Global configuration settings for the progcontestaccess_seb plugin.
 *
 * @package    progcontestaccess_seb
 * @author     Andrew Madden <andrewmadden@catalyst-au.net>
 * @author     Dmitrii Metelkin <dmitriim@catalyst-au.net>
 * @copyright  2019 Catalyst IT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die;

global $ADMIN;

if ($hassiteconfig) {

    $settings->add(new admin_setting_heading(
        'progcontestaccess_seb/supportedversions',
        '',
        $OUTPUT->notification(get_string('setting:supportedversions', 'progcontestaccess_seb'), 'warning')));

    $settings->add(new admin_setting_configcheckbox('progcontestaccess_seb/autoreconfigureseb',
        get_string('setting:autoreconfigureseb', 'progcontestaccess_seb'),
        get_string('setting:autoreconfigureseb_desc', 'progcontestaccess_seb'),
        '1'));

    $links = [
        'seb' => get_string('setting:showseblink', 'progcontestaccess_seb'),
        'http' => get_string('setting:showhttplink', 'progcontestaccess_seb')
    ];
    $settings->add(new admin_setting_configmulticheckbox('progcontestaccess_seb/showseblinks',
        get_string('setting:showseblinks', 'progcontestaccess_seb'),
        get_string('setting:showseblinks_desc', 'progcontestaccess_seb'),
        $links, $links));

    $settings->add(new admin_setting_configtext('progcontestaccess_seb/downloadlink',
        get_string('setting:downloadlink', 'progcontestaccess_seb'),
        get_string('setting:downloadlink_desc', 'progcontestaccess_seb'),
        'https://safeexambrowser.org/download_en.html',
        PARAM_URL));

    $settings->add(new admin_setting_configcheckbox('progcontestaccess_seb/progcontestpasswordrequired',
        get_string('setting:progcontestpasswordrequired', 'progcontestaccess_seb'),
        get_string('setting:progcontestpasswordrequired_desc', 'progcontestaccess_seb'),
        '0'));

    $settings->add(new admin_setting_configcheckbox('progcontestaccess_seb/displayblocksbeforestart',
        get_string('setting:displayblocksbeforestart', 'progcontestaccess_seb'),
        get_string('setting:displayblocksbeforestart_desc', 'progcontestaccess_seb'),
        '0'));

    $settings->add(new admin_setting_configcheckbox('progcontestaccess_seb/displayblockswhenfinished',
        get_string('setting:displayblockswhenfinished', 'progcontestaccess_seb'),
        get_string('setting:displayblockswhenfinished_desc', 'progcontestaccess_seb'),
        '1'));
}

if (has_capability('progcontestaccess/seb:managetemplates', context_system::instance())) {
    $ADMIN->add('modsettingsprogcontestcat',
        new admin_externalpage(
            'progcontestaccess_seb/template',
            get_string('manage_templates', 'progcontestaccess_seb'),
            new moodle_url('/mod/progcontest/accessrule/seb/template.php'),
            'progcontestaccess/seb:managetemplates'
        )
    );
}
