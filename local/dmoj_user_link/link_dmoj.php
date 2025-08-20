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

use MongoDB\Operation\Delete;

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
        $payload[$id] = [
            'username' => $user->username,
            'email' => $user->email,
            'first_name' => $user->firstname,
            'last_name' => $user->lastname,
            'is_admin' => $is_admin,
        ];
    }
    debugging("Linking DMOJ for users: " . json_encode($payload));

    // send request to force create DMOJ account
    $request = new local_dmoj_user_link\api\ForceCreateDMOJAccount($payload);
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
            debugging("DMOJ user linked: moodleid = {$moodleid}, dmojuid = {$userinfo['dmoj_uid']}", DEBUG_DEVELOPER);
        }
    }

    return $response;
}

function unlink_dmoj($user_id = null) {
    // Require login and admin privileges
    require_login();
    require_admin();

    global $DB;

    if (!$user_id) {
        # Get all users and admins
        $allusers = array_merge(\local_dmoj_user_link\get_all_users(), \local_dmoj_user_link\get_all_admins());
        $users = [];
        foreach ($allusers as $user) {
            $users[$user->id] = $user;
        }
    } else {
        $users = [$user_id => $DB->get_record('user', ['id' => $user_id])];
    }
    $params = [];

    foreach ($users as $id => $user) {
        $params["id"][] = $id;
    }

    // send request to delete DMOJ account
    $request = new local_dmoj_user_link\api\DeleteDMOJAccount($params);
    $response = $request->run();

    debugging("Unlinking DMOJ for users with ids: " . json_encode($params));

    // Delete the record from the database
    foreach ($users as $id => $user) {
        debugging("Unlinking DMOJ for user: {$user->username}");
        $DB->delete_records('myplugin_dmoj_users', ['moodle_user_id' => $id]);
    }
}
?>