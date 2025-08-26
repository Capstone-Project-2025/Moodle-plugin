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
namespace local_dmoj_user_link;
use stdClass, Exception;
require_once(__DIR__ . '/../../config.php');
require_once(__DIR__ . '/classes/sql.php');
require_once(__DIR__ . '/classes/api/requests_to_dmoj.php');

// link all admins or users in a specific course
function link_dmoj($course_id = null) {
    // Require login and admin privileges
    require_login();
    require_admin();

    global $DB;

    $payload = [];
    $is_admin = 0;
    if (!$course_id) {
        $users = \local_dmoj_user_link\get_all_admins();
        $is_admin = 1;
    } else {
        $users = \local_dmoj_user_link\get_users_and_roles_in_course($course_id);
    }

    foreach ($users as $id => $user) {
        $payload[$user->userid] = [
            'username' => $user->username,
            'email' => $user->email,
            'first_name' => $user->firstname,
            'last_name' => $user->lastname,
            'is_admin' => $is_admin,
        ];
    }

    // send request to force create DMOJ account
    $request = new api\ForceCreateDMOJAccount($payload);
    $response = $request->send();

    // get the response and save to db
    $data = $response['body'];
        
    // Handle successful user links
    if (!empty($data['success'])) {
        foreach ($data['success'] as $moodleid => $userinfo) {
            $insertdata = new stdClass();
            $insertdata->moodle_user_id = (int)$moodleid;
            $insertdata->dmoj_user_id = $userinfo['dmoj_uid'];
            $insertdata->dmoj_user_profile_id = $userinfo['dmoj_profile_uid'] ?? 0;

            // Save to database
            $DB->insert_record('myplugin_dmoj_users', $insertdata);
        }
    }

    return $response;
}

function unlink_dmoj($users_id = []) {
    // Require login and admin privileges
    require_login();
    require_admin();

    global $DB;

    if (empty($users_id)) {
        # Get all users and admins
        $allusers = array_merge(get_all_users(), get_all_admins());
        foreach ($allusers as $user) {
            $users_id[] = $user->userid;
        }
    } 
    $params = [];

    foreach ($users_id as $id) {
        $params["id"][] = $id;
    }

    // send request to delete DMOJ account
    $request = new api\DeleteDMOJAccount($params);
    $response = $request->run();

    debugging("Unlinking DMOJ for users with ids: " . json_encode($params));

    // Delete the record from the database
    foreach ($users_id as $id) {
        debugging("Unlinking DMOJ for user: {$user->username}");
        $DB->delete_records('myplugin_dmoj_users', ['moodle_user_id' => $id]);
    }
}

function link_orgs($courseid) {
    global $DB;

    // event might not have courseid
    if (empty($courseid)) {
        return;
    }

    // try linking course users
    try {
        $response = link_dmoj($courseid);
        $status = $response['status'];
        $body = $response['body'];

        // Add a notification so it appears after redirect
        if ($status == 207 || $status == 201) {
            \core\notification::add('course accounts linked successfully!', \core\output\notification::NOTIFY_SUCCESS);

            // if users are created, try to add them to the org on DMOJ based on this side course permission
            $data = \local_dmoj_user_link\get_users_and_roles_in_course_with_dmoj_id($courseid);

            // getting members and admins
            $members = array_values(array_filter($data, fn($user) => $user->roleshortname === 'student'));
            $admins  = array_values(array_filter($data, fn($user) => in_array($user->roleshortname, ['editingteacher', 'manager', 'teacher'])));

            // Sending requests to create org with users from course
            $payload = [
                "name" => "Course Organization - " . $courseid,
                "slug" => "course-organization-" . $courseid,
                "short_name" => "Course Org - " . $courseid,
                "about" => "This organization is for the course: " . $courseid,
                "members" => array_map(fn($user) => $user->dmojuserid, $members),
                "admins" => array_map(fn($user) => $user->dmojuserid, $admins)
            ];
            $org = $DB->get_record('myplugin_course_org_link', ['course_id' => $courseid]);
            if ($org) {
                // \core\notification::add('DMOJ org already exists for this course', \core\output\notification::NOTIFY_INFO);
                // if the org exists update it
                $request = new \local_dmoj_user_link\api\UpdateOrg($org->dmoj_org_id, $payload);
                $response = $request->run();
                $body = $response['body'];
                if ($response['status'] == 200) {
                    \core\notification::add('DMOJ org updated successfully', \core\output\notification::NOTIFY_SUCCESS);
                    
                } else {
                    \core\notification::add('DMOJ org update failed. Status: ' . $response['status'] . ' Body: ' . json_encode($body), \core\output\notification::NOTIFY_ERROR);
                }
            } else {
                // if the org that match course doesn't exist create it
                $request = new \local_dmoj_user_link\api\CreateOrg($payload);
                $response = $request->run();
                $body = $response['body'];
                $insertdata = new stdClass();
                $insertdata->course_id = $courseid;
                $insertdata->dmoj_org_id = $body['id'] ?? 0;
                $DB->insert_record('myplugin_course_org_link', $insertdata);
            }
            
        } else {
            \core\notification::add('course linking failed. Status: ' . $status . ' Body: ' . json_encode($body), \core\output\notification::NOTIFY_ERROR);
        }
    } catch (Exception $e) {
        \core\notification::add('course linking failed. Error: ' . $e->getMessage(), \core\output\notification::NOTIFY_ERROR);
    }
}

function unlink_orgs() {
    global $DB;

    $courses = \local_dmoj_user_link\get_courses_without_progcontest();

    foreach ($courses as $course) {
        // send request to delete DMOJ org
        $request = new \local_dmoj_user_link\api\DeleteOrg($course->dmoj_org_id);
        $response = $request->run();

        // Delete the record from the database
        $DB->delete_records('myplugin_course_org_link', ['course_id' => $course->course_id]);
    }
}

function unlink_users_not_in_course_with_progcontest() {
    global $DB;

    $users = \local_dmoj_user_link\get_users_not_in_course_with_progcontest();
    $users_id = array_map(fn($user) => $user->moodle_user_id, $users);
    unlink_dmoj($users_id);
}