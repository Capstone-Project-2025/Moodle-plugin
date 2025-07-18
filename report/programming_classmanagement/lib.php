<?php
defined('MOODLE_INTERNAL') || die();

/**
 * Ajoute le lien du rapport dans le menu "Reports" d’un cours
 */
function report_programming_classmanagement_extend_navigation_course($navigation, $course, $context) {
    if (has_capability('report/programming_classmanagement:view', $context)) {
        $url = new moodle_url('/report/programming_classmanagement/index.php', ['id' => $course->id]);
        $navigation->add(
            get_string('pluginname', 'report_programming_classmanagement'),
            $url,
            navigation_node::TYPE_SETTING,
            null,
            'report_programming_classmanagement',
            new pix_icon('i/report', '')
        );
    }
}
