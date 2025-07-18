<?php
function get_all_users() {
    global $DB;

    // Fetch all users except deleted, guest, and admin
    // Link admin through the database yourself, you need admin for doing force linking anyway
    // On DMOJ, go to UserSocialAuth and create a field (id, "mmodle", 2, {}, 1, 2025-07-04 14:10:04.855597, 2025-07-04 14:10:04.855597)
    // i copied the date straight from the database, you can use any date you want
    $sql = "SELECT u.id, u.username, u.email, u.firstname, u.lastname
            FROM {user} u
            WHERE u.deleted = 0 AND u.username <> 'guest' AND u.username <> 'admin'";

    return $DB->get_records_sql($sql);
}