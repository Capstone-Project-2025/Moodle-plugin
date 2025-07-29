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
 * Administration settings definitions for the progcontest module.
 *
 * @package   mod_progcontest
 * @copyright 2010 Petr Skoda
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */


defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/mod/progcontest/lib.php');

// First get a list of progcontest reports with there own settings pages. If there none,
// we use a simpler overall menu structure.
$reports = core_component::get_plugin_list_with_file('progcontest', 'settings.php', false);
$reportsbyname = array();
foreach ($reports as $report => $reportdir) {
    $strreportname = get_string($report . 'report', 'progcontest_'.$report);
    $reportsbyname[$strreportname] = $report;
}
core_collator::ksort($reportsbyname);

// First get a list of progcontest reports with there own settings pages. If there none,
// we use a simpler overall menu structure.
$rules = core_component::get_plugin_list_with_file('progcontestaccess', 'settings.php', false);
$rulesbyname = array();
foreach ($rules as $rule => $ruledir) {
    $strrulename = get_string('pluginname', 'progcontestaccess_' . $rule);
    $rulesbyname[$strrulename] = $rule;
}
core_collator::ksort($rulesbyname);

// Create the progcontest settings page.
if (empty($reportsbyname) && empty($rulesbyname)) {
    $pagetitle = get_string('modulename', 'progcontest');
} else {
    $pagetitle = get_string('generalsettings', 'admin');
}
$progcontestsettings = new admin_settingpage('modsettingprogcontest', $pagetitle, 'moodle/site:config');

if ($ADMIN->fulltree) {
    // Introductory explanation that all the settings are defaults for the add progcontest form.
    $progcontestsettings->add(new admin_setting_heading('progcontestintro', '', get_string('configintro', 'progcontest')));

    // Time limit.
    $setting = new admin_setting_configduration('progcontest/timelimit',
            get_string('timelimit', 'progcontest'), get_string('configtimelimitsec', 'progcontest'),
            '0', 60);
    $setting->set_advanced_flag_options(admin_setting_flag::ENABLED, false);
    $progcontestsettings->add($setting);

    // What to do with overdue attempts.
    $progcontestsettings->add(new mod_progcontest_admin_setting_overduehandling('progcontest/overduehandling',
            get_string('overduehandling', 'progcontest'), get_string('overduehandling_desc', 'progcontest'),
            array('value' => 'autosubmit', 'adv' => false), null));

    // Grace period time.
    $setting = new admin_setting_configduration('progcontest/graceperiod',
            get_string('graceperiod', 'progcontest'), get_string('graceperiod_desc', 'progcontest'),
            '86400');
    $setting->set_advanced_flag_options(admin_setting_flag::ENABLED, false);
    $progcontestsettings->add($setting);

    // Minimum grace period used behind the scenes.
    $progcontestsettings->add(new admin_setting_configduration('progcontest/graceperiodmin',
            get_string('graceperiodmin', 'progcontest'), get_string('graceperiodmin_desc', 'progcontest'),
            60, 1));

    // Number of attempts.
    $options = array(get_string('unlimited'));
    for ($i = 1; $i <= progcontest_MAX_ATTEMPT_OPTION; $i++) {
        $options[$i] = $i;
    }
    $setting = new admin_setting_configselect('progcontest/attempts',
            get_string('attemptsallowed', 'progcontest'), get_string('configattemptsallowed', 'progcontest'),
            0, $options);
    $setting->set_advanced_flag_options(admin_setting_flag::ENABLED, false);
    $progcontestsettings->add($setting);

    // Grading method.
    $progcontestsettings->add(new mod_progcontest_admin_setting_grademethod('progcontest/grademethod',
            get_string('grademethod', 'progcontest'), get_string('configgrademethod', 'progcontest'),
            array('value' => progcontest_GRADEHIGHEST, 'adv' => false), null));

    // Maximum grade.
    $progcontestsettings->add(new admin_setting_configtext('progcontest/maximumgrade',
            get_string('maximumgrade'), get_string('configmaximumgrade', 'progcontest'), 10, PARAM_INT));

    // Questions per page.
    $perpage = array();
    $perpage[0] = get_string('never');
    $perpage[1] = get_string('aftereachquestion', 'progcontest');
    for ($i = 2; $i <= progcontest_MAX_QPP_OPTION; ++$i) {
        $perpage[$i] = get_string('afternquestions', 'progcontest', $i);
    }
    $setting = new admin_setting_configselect('progcontest/questionsperpage',
            get_string('newpageevery', 'progcontest'), get_string('confignewpageevery', 'progcontest'),
            1, $perpage);
    $setting->set_advanced_flag_options(admin_setting_flag::ENABLED, false);
    $progcontestsettings->add($setting);

    // Navigation method.
    $setting = new admin_setting_configselect('progcontest/navmethod',
            get_string('navmethod', 'progcontest'), get_string('confignavmethod', 'progcontest'),
            progcontest_NAVMETHOD_FREE, progcontest_get_navigation_options());
    $setting->set_advanced_flag_options(admin_setting_flag::ENABLED, true);
    $progcontestsettings->add($setting);

    // Shuffle within questions.
    $setting = new admin_setting_configcheckbox('progcontest/shuffleanswers',
            get_string('shufflewithin', 'progcontest'), get_string('configshufflewithin', 'progcontest'),
            1);
    $setting->set_advanced_flag_options(admin_setting_flag::ENABLED, false);
    $progcontestsettings->add($setting);

    // Preferred behaviour.
    $progcontestsettings->add(new admin_setting_question_behaviour('progcontest/preferredbehaviour',
            get_string('howquestionsbehave', 'question'), get_string('howquestionsbehave_desc', 'progcontest'),
            'deferredfeedback'));

    // Can redo completed questions.
    $setting = new admin_setting_configselect('progcontest/canredoquestions',
            get_string('canredoquestions', 'progcontest'), get_string('canredoquestions_desc', 'progcontest'),
            0,
            array(0 => get_string('no'), 1 => get_string('canredoquestionsyes', 'progcontest')));
    $setting->set_advanced_flag_options(admin_setting_flag::ENABLED, true);
    $progcontestsettings->add($setting);

    // Each attempt builds on last.
    $setting = new admin_setting_configcheckbox('progcontest/attemptonlast',
            get_string('eachattemptbuildsonthelast', 'progcontest'),
            get_string('configeachattemptbuildsonthelast', 'progcontest'),
            0);
    $setting->set_advanced_flag_options(admin_setting_flag::ENABLED, true);
    $progcontestsettings->add($setting);

    // Review options.
    $progcontestsettings->add(new admin_setting_heading('reviewheading',
            get_string('reviewoptionsheading', 'progcontest'), ''));
    foreach (mod_progcontest_admin_review_setting::fields() as $field => $name) {
        $default = mod_progcontest_admin_review_setting::all_on();
        $forceduring = null;
        if ($field == 'attempt') {
            $forceduring = true;
        } else if ($field == 'overallfeedback') {
            $default = $default ^ mod_progcontest_admin_review_setting::DURING;
            $forceduring = false;
        }
        $progcontestsettings->add(new mod_progcontest_admin_review_setting('progcontest/review' . $field,
                $name, '', $default, $forceduring));
    }

    // Show the user's picture.
    $progcontestsettings->add(new mod_progcontest_admin_setting_user_image('progcontest/showuserpicture',
            get_string('showuserpicture', 'progcontest'), get_string('configshowuserpicture', 'progcontest'),
            array('value' => 0, 'adv' => false), null));

    // Decimal places for overall grades.
    $options = array();
    for ($i = 0; $i <= progcontest_MAX_DECIMAL_OPTION; $i++) {
        $options[$i] = $i;
    }
    $setting = new admin_setting_configselect('progcontest/decimalpoints',
            get_string('decimalplaces', 'progcontest'), get_string('configdecimalplaces', 'progcontest'),
            2, $options);
    $setting->set_advanced_flag_options(admin_setting_flag::ENABLED, false);
    $progcontestsettings->add($setting);

    // Decimal places for question grades.
    $options = array(-1 => get_string('sameasoverall', 'progcontest'));
    for ($i = 0; $i <= progcontest_MAX_Q_DECIMAL_OPTION; $i++) {
        $options[$i] = $i;
    }
    $setting = new admin_setting_configselect('progcontest/questiondecimalpoints',
            get_string('decimalplacesquestion', 'progcontest'),
            get_string('configdecimalplacesquestion', 'progcontest'),
            -1, $options);
    $setting->set_advanced_flag_options(admin_setting_flag::ENABLED, false);
    $progcontestsettings->add($setting);

    // Show blocks during progcontest attempts.
    $setting = new admin_setting_configcheckbox('progcontest/showblocks',
            get_string('showblocks', 'progcontest'), get_string('configshowblocks', 'progcontest'),
            0);
    $setting->set_advanced_flag_options(admin_setting_flag::ENABLED, true);
    $progcontestsettings->add($setting);

    // Password.
    $setting = new admin_setting_configpasswordunmask('progcontest/progcontestpassword',
            get_string('requirepassword', 'progcontest'), get_string('configrequirepassword', 'progcontest'),
            '');
    $setting->set_advanced_flag_options(admin_setting_flag::ENABLED, false);
    $setting->set_required_flag_options(admin_setting_flag::ENABLED, false);
    $progcontestsettings->add($setting);

    // IP restrictions.
    $setting = new admin_setting_configtext('progcontest/subnet',
            get_string('requiresubnet', 'progcontest'), get_string('configrequiresubnet', 'progcontest'),
            '', PARAM_TEXT);
    $setting->set_advanced_flag_options(admin_setting_flag::ENABLED, true);
    $progcontestsettings->add($setting);

    // Enforced delay between attempts.
    $setting = new admin_setting_configduration('progcontest/delay1',
            get_string('delay1st2nd', 'progcontest'), get_string('configdelay1st2nd', 'progcontest'),
            0, 60);
    $setting->set_advanced_flag_options(admin_setting_flag::ENABLED, true);
    $progcontestsettings->add($setting);
    $setting = new admin_setting_configduration('progcontest/delay2',
            get_string('delaylater', 'progcontest'), get_string('configdelaylater', 'progcontest'),
            0, 60);
    $setting->set_advanced_flag_options(admin_setting_flag::ENABLED, true);
    $progcontestsettings->add($setting);

    // Browser security.
    $progcontestsettings->add(new mod_progcontest_admin_setting_browsersecurity('progcontest/browsersecurity',
            get_string('showinsecurepopup', 'progcontest'), get_string('configpopup', 'progcontest'),
            array('value' => '-', 'adv' => true), null));

    $progcontestsettings->add(new admin_setting_configtext('progcontest/initialnumfeedbacks',
            get_string('initialnumfeedbacks', 'progcontest'), get_string('initialnumfeedbacks_desc', 'progcontest'),
            2, PARAM_INT, 5));

    // Allow user to specify if setting outcomes is an advanced setting.
    if (!empty($CFG->enableoutcomes)) {
        $progcontestsettings->add(new admin_setting_configcheckbox('progcontest/outcomes_adv',
            get_string('outcomesadvanced', 'progcontest'), get_string('configoutcomesadvanced', 'progcontest'),
            '0'));
    }

    // Autosave frequency.
    $progcontestsettings->add(new admin_setting_configduration('progcontest/autosaveperiod',
            get_string('autosaveperiod', 'progcontest'), get_string('autosaveperiod_desc', 'progcontest'), 60, 1));
}

// Now, depending on whether any reports have their own settings page, add
// the progcontest setting page to the appropriate place in the tree.
if (empty($reportsbyname) && empty($rulesbyname)) {
    $ADMIN->add('modsettings', $progcontestsettings);
} else {
    $ADMIN->add('modsettings', new admin_category('modsettingsprogcontestcat',
            get_string('modulename', 'progcontest'), $module->is_enabled() === false));
    $ADMIN->add('modsettingsprogcontestcat', $progcontestsettings);

    // Add settings pages for the progcontest report subplugins.
    foreach ($reportsbyname as $strreportname => $report) {
        $reportname = $report;

        $settings = new admin_settingpage('modsettingsprogcontestcat'.$reportname,
                $strreportname, 'moodle/site:config', $module->is_enabled() === false);
        include($CFG->dirroot . "/mod/progcontest/report/$reportname/settings.php");
        if (!empty($settings)) {
            $ADMIN->add('modsettingsprogcontestcat', $settings);
        }
    }

    // Add settings pages for the progcontest access rule subplugins.
    foreach ($rulesbyname as $strrulename => $rule) {
        $settings = new admin_settingpage('modsettingsprogcontestcat' . $rule,
                $strrulename, 'moodle/site:config', $module->is_enabled() === false);
        include($CFG->dirroot . "/mod/progcontest/accessrule/$rule/settings.php");
        if (!empty($settings)) {
            $ADMIN->add('modsettingsprogcontestcat', $settings);
        }
    }
}

$settings = null; // We do not want standard settings link.
