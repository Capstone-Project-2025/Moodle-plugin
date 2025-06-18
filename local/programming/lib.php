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
