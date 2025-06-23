<?php
defined('MOODLE_INTERNAL') || die();

function dmojorganize_extend_navigation_course($navigation, $course, $context) {
    if (is_siteadmin() && has_capability('mod/dmojorganize:view', $context)) {
        $url = new moodle_url('/mod/dmojorganize/view.php', ['id' => $course->id]);
        $navigation->add(
            get_string('dmojsettings', 'mod_dmojorganize'),
            $url,
            navigation_node::TYPE_CUSTOM,
            null,
            null
        );
    }
}
