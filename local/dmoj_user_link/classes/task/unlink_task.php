<?php
namespace local_dmoj_user_link\task;
require_once(__DIR__ . '../../../link_dmoj.php');
/**
 * Scheduled task to unlink users and organizations from DMOJ.
 */
class unlink_task extends \core\task\scheduled_task {

    public function get_name() {
        return get_string('unlink_task', 'local_dmoj_user_link');
    }

    public function execute() {
        \local_dmoj_user_link\unlink_orgs();
        \local_dmoj_user_link\unlink_users_not_in_course_with_progcontest();
    }
}
