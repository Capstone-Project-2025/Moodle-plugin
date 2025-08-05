<?php
defined('MOODLE_INTERNAL') || die();

function local_dmoj_user_linkextend_navigation_course($navigation, $course, $context) {

    // stat link
    if (has_capability('local/dmoj_user_link:viewstats', $context)) {
        global $USER;
        $url1 = new moodle_url('/local/dmoj_user_link/stats/report.php', [
            'id' => $course->id,
            'user' => $USER->id
        ]);
        $navigation->add(
            get_string('viewstats', 'local_dmoj_user_link'),
            $url1,
            navigation_node::TYPE_CUSTOM,
            null,
            null,
            new pix_icon('i/stats', '')
        );
    }

    // problem link
    if (has_capability('local/dmoj_user_link:viewproblems', $context)) {
        $url2 = new moodle_url('/local/dmoj_user_link/problems/apiproblems.php', ['id' => $course->id]);
        $navigation->add(
            get_string('viewproblems', 'local_dmoj_user_link'),
            $url2,
            navigation_node::TYPE_CUSTOM,
            null,
            null,
            new pix_icon('i/report', '')
        );
    }
}

function local_dmoj_user_link_myprofile_navigation(core_user\output\myprofile\tree $tree, $user, $iscurrentuser, $course) {
    global $DB, $USER;
    $domain = get_config('local_dmoj_user_link', 'dmoj_domain');
    // Create a new category
    $category = new core_user\output\myprofile\category('dmoj', get_string('category_title', 'local_dmoj_user_link'), 'miscellaneous');

    // add nodes to tree
    $tree->add_category($category);
    $check = $DB->get_record('myplugin_dmoj_users', ['moodle_user_id' => $user->id], 'dmoj_user_id');

    if (!$check) {
        // If the user does not have a DMOJ account, might as well ask the admin to link it for them through the database
        // I am doing this because the requirement for Capstone is linking should be automatic
        // If it is not working correctly, maybe some bugs arise and i fucked up
    } else {
        // If the user does have a DMOJ account
        // You should leave all the nodes that need a DMOJ account here
        // create node "download user data button"
        $url = new moodle_url('/local/dmoj_user_link/index.php', []);
        $string = get_string('download_user_data', 'local_dmoj_user_link');
        $node_download = new core_user\output\myprofile\node('dmoj', 'download_user_data', $string, null, $url);
    }
}
