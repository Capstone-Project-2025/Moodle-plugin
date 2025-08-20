<?php
namespace local_dmoj_user_link;
use context_course;

function get_all_users() {
    global $DB;

    // Fetch all users except deleted, guest, and admin
    // Link admin through the database yourself, you need admin for doing force linking anyway
    $sql = "SELECT u.id, u.username, u.email, u.firstname, u.lastname
            FROM {user} u
            WHERE u.deleted = 0
            AND u.username <> 'guest'
            AND EXISTS (
                SELECT 1
                FROM STRING_SPLIT((SELECT value FROM {config} WHERE name = 'siteadmins'), ',') s
                WHERE TRY_CAST(s.value AS INT) = u.id
            )";


    return $DB->get_records_sql($sql);
}

function get_all_admins() {
    global $DB;

    // Fetch all admin users
    $sql = "SELECT u.id, u.username, u.email, u.firstname, u.lastname
            FROM {user} u
            WHERE u.deleted = 0 AND u.username = 'admin'";

    return $DB->get_records_sql($sql);
}

// Get all users and their roles in a specific course
function get_users_and_roles_in_course($courseid) {
    global $DB;

    $context = context_course::instance($courseid);

    $sql = "SELECT u.id AS userid,
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
               AND u.deleted = 0
          ORDER BY u.lastname, u.firstname";

    return $DB->get_records_sql($sql, ['contextid' => $context->id]);
}

// Get users and roles in a course with DMOJ ID
function get_users_and_roles_in_course_with_dmoj_id($courseid) {
    global $DB;

    $context = context_course::instance($courseid);

    $sql = "SELECT u.id AS userid,
                   d.dmoj_user_profile_id AS dmojuserid,
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
        INNER JOIN {myplugin_dmoj_users} d ON d.moodle_user_id = u.id
             WHERE ctx.id = :contextid
               AND u.deleted = 0
          ORDER BY u.lastname, u.firstname";

    return $DB->get_records_sql($sql, ['contextid' => $context->id]);
}