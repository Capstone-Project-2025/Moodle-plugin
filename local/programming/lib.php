<?php
defined('MOODLE_INTERNAL') || die();

function local_programming_extend_navigation_course($navigation, $course, $context) {

    // Lien vers les statistiques
    if (has_capability('local/programming:viewstats', $context)) {
        $url1 = new moodle_url('/local/programming/stats/report.php', ['id' => $course->id]);
        $navigation->add(
            get_string('viewstats', 'local_programming'),
            $url1,
            navigation_node::TYPE_CUSTOM,
            null,
            null,
            new pix_icon('i/stats', '')
        );
    }

    // Lien vers les problèmes
    if (has_capability('local/programming:viewproblems', $context)) {
        $url2 = new moodle_url('/local/programming/problems/apiproblems.php', ['id' => $course->id]);
        $navigation->add(
            get_string('viewproblems', 'local_programming'),
            $url2,
            navigation_node::TYPE_CUSTOM,
            null,
            null,
            new pix_icon('i/report', '')
        );
    }
}

function local_programming_myprofile_navigation(core_user\output\myprofile\tree $tree, $user, $iscurrentuser, $course) {
    global $DB, $USER;
    $domain = get_config('local_programming', 'dmoj_domain');
    // Create a new category
    $category = new core_user\output\myprofile\category('dmoj', get_string('category_title', 'local_programming'), 'miscellaneous');
    
    // add nodes to tree
    $tree->add_category($category);
    $check = $DB->get_record('programming_dmoj_users', ['moodle_user_id' => $user->id], 'dmoj_user_id');

    if (!$check) {
        // If the user does not have a DMOJ account, might as well ask the admin to link it for them through the database
        // I am doing this because the requirement for Capstone is linking should be automatic
        // If it is not working correctly, maybe some bugs arise and i fucked up
    } else {
        // If the user does have a DMOJ account
        // You should leave all the nodes that need a DMOJ account here
        // create node "download user data button"
        $url = new moodle_url('/local/programming/index.php', []);
        $string = get_string('download_user_data', 'local_programming');
        $node_download = new core_user\output\myprofile\node('dmoj', 'download_user_data', $string, null, $url);
    }
}
