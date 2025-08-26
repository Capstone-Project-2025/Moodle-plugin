<?php
namespace local_dmoj_user_link;
use context_course;

function get_all_users() {
    global $DB;

    $sql = "SELECT u.id as userid, u.username, u.email, u.firstname, u.lastname
              FROM {user} u
             WHERE u.deleted = 0
               AND u.username <> 'guest'
               AND u.id NOT IN (" . implode(',', array_keys(get_admins())) . ")";

    return $DB->get_records_sql($sql);
}

function get_all_admins() {
    return get_admins();

    $result = [];
    foreach ($admins as $admin) {
        $obj = clone $admin;
        $obj->userid = $obj->id;
        unset($obj->id);
        $result[$obj->userid] = $obj;
    }

    return $result;
}

// Get all users and their roles in a specific course
function get_users_and_roles_in_course($courseid) {
    global $DB;

    $context = context_course::instance($courseid);

    $sql = "SELECT  ra.id AS roleassignmentid,
                    u.id AS userid,
                    u.username,
                    u.firstname,
                    u.lastname,
                    u.email,
                    r.id AS roleid,
                    r.shortname AS roleshortname,
                    r.name AS rolename
              FROM {user} u
        INNER JOIN {role_assignments} ra ON ra.userid = u.id
        INNER JOIN {role} r ON r.id = ra.roleid
        INNER JOIN {context} ctx ON ctx.id = ra.contextid
             WHERE ctx.id = :contextid
               AND u.deleted = 0";

    return $DB->get_records_sql($sql, ['contextid' => $context->id]);
}

// Get users and roles in a course with DMOJ ID
function get_users_and_roles_in_course_with_dmoj_id($courseid) {
    global $DB;

    $context = context_course::instance($courseid);

    $sql = "SELECT  ra.id AS roleassignmentid,
                    u.id AS userid,
                    d.dmoj_user_profile_id AS dmojuserid,
                    u.username,
                    u.firstname,
                    u.lastname,
                    u.email,
                    r.id AS roleid,
                    r.shortname AS roleshortname,
                    r.name AS rolename
            FROM {user} u
            JOIN {role_assignments} ra ON ra.userid = u.id
            JOIN {role} r ON r.id = ra.roleid
            JOIN {context} ctx ON ctx.id = ra.contextid
            JOIN {myplugin_dmoj_users} d ON d.moodle_user_id = u.id
            WHERE ctx.id = :contextid
              AND u.deleted = 0";

    return $DB->get_records_sql($sql, ['contextid' => $context->id], 0, 0);
}

/**
 * Get courses without associated programming contests
 * return deleted courses as an associative array with
 * ID => course_id and dmoj_org_id
 */
function get_courses_without_progcontest() {
    global $DB;

    // get courses without associated programming contests
    $sql = "SELECT col.*
            FROM {myplugin_course_org_link} col
            WHERE NOT EXISTS (
                SELECT 1
                FROM {progcontest} p
                WHERE p.course = col.course_id
            )";

    return $DB->get_records_sql($sql);
}

/**
 * Get users not enrolled in any course with an associated programming contest
 * return an associative array with
 * ID => moodle_user_id and dmoj_user_id and dmoj_user_profile_id
 */
function get_users_not_in_course_with_progcontest() {
    global $DB;

    // Get all users to be deleted
    $sql = "
        SELECT du.*
        FROM {myplugin_dmoj_users} du
        WHERE NOT EXISTS (
            SELECT 1
            FROM {user_enrolments} ue
            JOIN {enrol} e ON e.id = ue.enrolid
            JOIN {course} c ON c.id = e.courseid
            JOIN {progcontest} p ON p.course = c.id
            WHERE ue.userid = du.moodle_user_id
        )
    ";

    return $DB->get_records_sql($sql);
}
