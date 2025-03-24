<?php

defined('MOODLE_INTERNAL') || die();

function local_problems_extend_navigation_course($navigation, $course, $context) {
    global $PAGE;

    if ($PAGE->url->compare(new moodle_url('/course/view.php'), URL_MATCH_BASE)) {
        $node = $navigation->add(
            get_string('contestButton', 'local_problems'),
            new moodle_url('/local/problems/contest.php', ['courseid' => $course->id]),
            navigation_node::TYPE_CUSTOM,
            null,
            'contestButton'
        );
        $node->showinflatnavigation = true;
    }
}


function local_problems_extend_navigation_user_settings(
    navigation_node $parentnode,
    stdClass $user,
    context_user $context,
    stdClass $course,
    context_course $coursecontext
) {
    global $USER;

    if (!$parentnode) {
        return;
    }

    if ($USER->id == $user->id || has_capability('moodle/user:viewdetails', $context)) {
        $parentnode->add(
            get_string('userstatistics', 'local_problems'), 
            new moodle_url('/local/problems/user_statistic.php', array('id' => $user->id)),
            navigation_node::TYPE_SETTING,
            null,
            'userstatistics'
        );
    }
}