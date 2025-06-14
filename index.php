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
 * Moodle frontpage.
 *
 * @package    core
 * @copyright  1999 onwards Martin Dougiamas (http://dougiamas.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use function DI\get;

if (!file_exists('./config.php')) {
    header('Location: install.php');
    die;
}

require_once('config.php');
require_once($CFG->dirroot .'/course/lib.php');
require_once($CFG->libdir .'/filelib.php');
require_once('apirequest.php');

redirect_if_major_upgrade_required();

// Redirect logged-in users to homepage if required.
$redirect = optional_param('redirect', 1, PARAM_BOOL);

$urlparams = array();
if (!empty($CFG->defaulthomepage) &&
        ($CFG->defaulthomepage == HOMEPAGE_MY || $CFG->defaulthomepage == HOMEPAGE_MYCOURSES) &&
        $redirect === 0
) {
    $urlparams['redirect'] = 0;
}
$PAGE->set_url('/', $urlparams);
$PAGE->set_pagelayout('frontpage');
$PAGE->add_body_class('limitedwidth');
$PAGE->set_other_editing_capability('moodle/course:update');
$PAGE->set_other_editing_capability('moodle/course:manageactivities');
$PAGE->set_other_editing_capability('moodle/course:activityvisibility');

// Prevent caching of this page to stop confusion when changing page after making AJAX changes.
$PAGE->set_cacheable(false);

require_course_login($SITE);

$hasmaintenanceaccess = has_capability('moodle/site:maintenanceaccess', context_system::instance());

// If the site is currently under maintenance, then print a message.
if (!empty($CFG->maintenance_enabled) and !$hasmaintenanceaccess) {
    print_maintenance_message();
}

$hassiteconfig = has_capability('moodle/site:config', context_system::instance());

if ($hassiteconfig && moodle_needs_upgrading()) {
    redirect($CFG->wwwroot .'/'. $CFG->admin .'/index.php');
}

// If site registration needs updating, redirect.
\core\hub\registration::registration_reminder('/index.php');

$homepage = get_home_page();
if ($homepage != HOMEPAGE_SITE) {
    if (optional_param('setdefaulthome', false, PARAM_BOOL)) {
        set_user_preference('user_home_page_preference', HOMEPAGE_SITE);
    } else if (!empty($CFG->defaulthomepage) && ($CFG->defaulthomepage == HOMEPAGE_MY) && $redirect === 1) {
        // At this point, dashboard is enabled so we don't need to check for it (otherwise, get_home_page() won't return it).
        redirect($CFG->wwwroot .'/my/');
    } else if (!empty($CFG->defaulthomepage) && ($CFG->defaulthomepage == HOMEPAGE_MYCOURSES) && $redirect === 1) {
        redirect($CFG->wwwroot .'/my/courses.php');
    } else if ($homepage == HOMEPAGE_URL) {
        redirect(get_default_home_page_url());
    } else if (!empty($CFG->defaulthomepage) && ($CFG->defaulthomepage == HOMEPAGE_USER)) {
        $frontpagenode = $PAGE->settingsnav->find('frontpage', null);
        if ($frontpagenode) {
            $frontpagenode->add(
                get_string('makethismyhome'),
                new moodle_url('/', array('setdefaulthome' => true)),
                navigation_node::TYPE_SETTING);
        } else {
            $frontpagenode = $PAGE->settingsnav->add(get_string('frontpagesettings'), null, navigation_node::TYPE_SETTING, null);
            $frontpagenode->force_open();
            $frontpagenode->add(get_string('makethismyhome'),
                new moodle_url('/', array('setdefaulthome' => true)),
                navigation_node::TYPE_SETTING);
        }
    }
}

// Trigger event.
course_view(context_course::instance(SITEID));

$PAGE->set_pagetype('site-index');
$PAGE->set_docs_path('');
$editing = $PAGE->user_is_editing();
$PAGE->set_title(get_string('home'));
$PAGE->set_heading($SITE->fullname);
$PAGE->set_secondary_active_tab('coursehome');

$courserenderer = $PAGE->get_renderer('core', 'course');

if ($hassiteconfig) {
    $editurl = new moodle_url('/course/view.php', ['id' => SITEID, 'sesskey' => sesskey()]);
    $editbutton = $OUTPUT->edit_button($editurl);
    $PAGE->set_button($editbutton);
}

echo $OUTPUT->header();

$siteformatoptions = course_get_format($SITE)->get_format_options();
$modinfo = get_fast_modinfo($SITE);
$modnamesused = $modinfo->get_used_module_names();

// Print Section or custom info.
if (!empty($CFG->customfrontpageinclude)) {
    // Pre-fill some variables that custom front page might use.
    $modnames = get_module_types_names();
    $modnamesplural = get_module_types_names(true);
    $mods = $modinfo->get_cms();

    include($CFG->customfrontpageinclude);

} else if ($siteformatoptions['numsections'] > 0) {
    echo $courserenderer->frontpage_section1();
}
// Include course AJAX.
include_course_ajax($SITE, $modnamesused);

echo $courserenderer->frontpage();

if ($editing && has_capability('moodle/course:create', context_system::instance())) {
    echo $courserenderer->add_new_course_button();
}
/*
function getAccessToken(){
    global $USER;
    if (!isset($USER) || !isset($USER->id)) {
        echo "USER not set properly.";
        return;
    }

    $url = 'http://10.0.10.83:4000/api/token/';
    $data = json_encode([
        'uid' => $USER->id,
        'api_secret' => 'secret',
        'provider' => 'moodle'
    ]);
    // Initialize a cURL session
    $ch = curl_init($url);

    // Return the transfer as a string of the return value of curl_exec() instead of outputting it directly.
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

    // Sets the body of the POST request
    curl_setopt($ch, CURLOPT_POSTFIELDS, $data);

    // Set HTTP header fields, indicate that the body is JSON.
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json'
    ]);

    // FALSE: do not fail verbosely if the HTTP code returned is greater than or equal to 400.
    curl_setopt($ch, CURLOPT_FAILONERROR, false);

    // Execute the request
    $response = curl_exec($ch);
    
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);

    echo "HTTP Status Code: ". $httpCode . "<br>";

    if ($error) {
        echo "cURL Error: $error\n";
    } else {
        echo "Response Body:" . "<br>";
        $responseData = json_decode($response, true);
        echo '<pre>';
        echo json_encode($responseData, JSON_PRETTY_PRINT);
        echo '</pre>';
    }
}
*/
/*
$sth = getAccessToken();
echo $sth;
*/
// Testing the "Request" class
/*
$url = "http://139.59.105.152/api/v2/organizations";
$curl = curl_init();
curl_setopt($curl, CURLOPT_URL, $url);
curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
$response = curl_exec($curl);
$statusCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
$error = curl_error($curl);
$errorCode = curl_errno($curl);
curl_close($curl);
if ($errorCode) {
    $output = [
        'success' => false,
        'error' => $error,
        'code' => $errorCode
    ];
} else {
    $output = [
        'success' => true,
        'status_code' => $statusCode,
        'response' => json_decode($response, true)
    ];
}
if ($output["success"]){
    echo "Status code: " . $output["status_code"] . "<br>";
    echo '<pre>';
    echo json_encode($output["response"], JSON_PRETTY_PRINT);
    echo '</pre>';
}
*/
/*
require_login();
global $USER;
$user_array = (array) $USER;
$json_output = json_encode($user_array, JSON_PRETTY_PRINT);
echo '<pre>' . $json_output . '</pre>';
*/

$url = "http://139.59.105.152/";
$method = "POST";
$base_request = new APIRequest($url, $method);
$API_response = $base_request->GetAccessToken()["response"]; // This does both things: get access token as stored variable, and also set the access token in the storage
$json_response = json_encode($API_response, JSON_PRETTY_PRINT);
echo "Token Pair: <br>";
echo '<pre>' . $json_response . '</pre>';

$result = new GetProblemList();
$response = $result->run();
$json_output = json_encode($response, JSON_PRETTY_PRINT);
echo '<pre>' . $json_output . '</pre>';

echo $OUTPUT->footer();
?>