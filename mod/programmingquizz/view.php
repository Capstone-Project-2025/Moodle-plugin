<?php
require_once('../../config.php');

$id = required_param('id', PARAM_INT);
$cm = get_coursemodule_from_id('programmingquizz', $id, 0, false, MUST_EXIST);
$course = $DB->get_record('course', ['id' => $cm->course], '*', MUST_EXIST);
$instance = $DB->get_record('programmingquizz', ['id' => $cm->instance], '*', MUST_EXIST);

require_login($course, true, $cm);
$context = context_module::instance($cm->id);
$PAGE->set_url('/mod/programmingquizz/view.php', ['id' => $id]);
$PAGE->set_context($context);
$PAGE->set_cm($cm);
$PAGE->set_course($course);

echo $OUTPUT->header();
echo $OUTPUT->heading(format_string($instance->name));
echo format_text($instance->intro, FORMAT_HTML);

if (!empty($instance->code)) {
    echo html_writer::tag('h4', get_string('code', 'programmingquizz'));
    echo html_writer::tag('pre', s($instance->code));
}

echo $OUTPUT->footer();
