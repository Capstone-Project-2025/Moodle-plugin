<?php
defined('MOODLE_INTERNAL') || die();

function local_problems_extend_navigation_course($navigation, $course, $context) {
    global $PAGE;

    if ($PAGE->url->compare(new moodle_url('/course/view.php'), URL_MATCH_BASE)) {
        $node = $navigation->add(
            get_string('mycustombutton', 'local_problems'),
            new moodle_url('/local/problems/index.php'),
            navigation_node::TYPE_CUSTOM,
            null,
            'mycustombutton'
        );
        $node->showinflatnavigation = true;
    }
}