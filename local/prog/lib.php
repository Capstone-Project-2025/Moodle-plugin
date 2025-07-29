<?php
defined('MOODLE_INTERNAL') || die();

function local_prog_extend_navigation_course($navigation, $course, $context) {

    // Lien vers les statistiques
    if (has_capability('local/prog:viewstats', $context)) {
        global $USER;
        $url1 = new moodle_url('/local/prog/stats/report.php', [
            'id' => $course->id,
            'user' => $USER->id
        ]);
        $navigation->add(
            get_string('viewstats', 'local_prog'),
            $url1,
            navigation_node::TYPE_CUSTOM,
            null,
            null,
            new pix_icon('i/stats', '')
        );
    }

    // Lien vers les problèmes
    if (has_capability('local/prog:viewproblems', $context)) {
        $url2 = new moodle_url('/local/prog/problems/apiproblems.php', ['id' => $course->id]);
        $navigation->add(
            get_string('viewproblems', 'local_prog'),
            $url2,
            navigation_node::TYPE_CUSTOM,
            null,
            null,
            new pix_icon('i/report', '')
        );
    }
}
