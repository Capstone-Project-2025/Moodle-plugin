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
 * Readme file for local customisations
 *
 * @package    local_myplugin
 * @copyright  Dinh
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
require_once(__DIR__ . '/../../config.php');
require_once(__DIR__ . '/classes/user_data_form.php');
require_once(__DIR__ . '/classes/api/requests_to_dmoj.php');
// Require login
require_login();

// Set up the page
$PAGE->set_url(new moodle_url('/local/myplugin/index.php'));
$PAGE->set_context(context_system::instance());
$PAGE->set_heading(get_string('download_user_data', 'local_myplugin'));
$PAGE->set_pagelayout('standard');

// Output starts here
echo $OUTPUT->header();

// Form section
$mform = new UserDataForm();
$mform->display();

if ($mform->is_cancelled()) {
    // If there is a cancel element on the form, and it was pressed,
    // then the `is_cancelled()` function will return true.
    // You can handle the cancel operation here.
} else if ($fromform = $mform->get_data()) {
    // When the form is submitted, and the data is successfully validated,
    // the `get_data()` function will return the data posted in the form.
    $payload = [
        'comment_download' => $fromform->comment_download,
        'submission_download' => $fromform->submission_download,
        'submission_problem_glob' => $fromform->submission_problem_glob,
        'submission_results' => $fromform->submission_results,
    ];

    
    // Prepare downlaod data on DMOJ side
    $request = new PrepareDownloadData($payload);
    $response = $request->run();
    if ($response['status'] != 202) echo $OUTPUT->notification($response['body'], \core\output\notification::NOTIFY_ERROR);
    else echo $OUTPUT->notification('download data successfully prepared', \core\output\notification::NOTIFY_SUCCESS);

} else {
    // this came from moodledoc but i found that it is not necessary, since the form will definitely be displayed
    // even if the validated data is incorrect, the form is still there and you can just submit again
    // This branch is executed if the form is submitted but the data doesn't
    // validate and the form should be redisplayed or on the first display of the form.
    // Display the form.
    // $mform->display();
}

// Display the download URL if available
$request = new GetDownloadURL();
$response = $request->run();
if ($response && isset($response['body'])) {
    $body = json_decode($response['body'], true);

    if (!empty($body['download_url'])) {
        $downloadurl = new moodle_url('/local/myplugin/download.php', [
            'download_url' => $body['download_url']
        ]);

        $context = (object)[
            'download_url' => $downloadurl,
        ];
        echo $OUTPUT->render_from_template('local_myplugin/download_user_data', $context);
    }
}
echo $OUTPUT->footer();
?>