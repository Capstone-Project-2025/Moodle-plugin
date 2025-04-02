<?php
require_once('../../config.php');

$id = required_param('id', PARAM_INT);
$cm = get_coursemodule_from_id('programmingassign', $id, 0, false, MUST_EXIST);
$course = $DB->get_record('course', ['id' => $cm->course], '*', MUST_EXIST);
$instance = $DB->get_record('programmingassign', ['id' => $cm->instance], '*', MUST_EXIST);

require_login($course, true, $cm);
$context = context_module::instance($cm->id);
require_capability('mod/programmingassign:view', $context);

$PAGE->set_url('/mod/programmingassign/view.php', ['id' => $id]);
$PAGE->set_title($course->shortname . ': ' . $instance->name);
$PAGE->set_heading($course->fullname);

echo $OUTPUT->header();

// Encadré principal centré
echo html_writer::start_div('box generalbox', ['style' => 'max-width: 700px; margin: 0 auto;']);

// ---------- GRADING SUMMARY ----------

    echo html_writer::tag('h4', 'Grading summary');

    echo html_writer::start_tag('table', ['class' => 'generaltable', 'style' => 'width: 100%; margin-bottom: 20px;']);
    echo html_writer::start_tag('tbody');

    $data = [
        'Hidden from students' => $cm->visible ? 'No' : 'Yes',
        'Participants' => count_enrolled_users($context),
        'Submitted' => $DB->count_records('assign_submission', ['assignment' => $cm->instance, 'status' => 'submitted']),
        'Needs grading' => 0,
    ];

    if (!empty($instance->duedate)) {
        $remaining = $instance->duedate - time();
        if ($remaining > 0) {
            $days = floor($remaining / DAYSECS);
            $hours = floor(($remaining % DAYSECS) / HOURSECS);
            $timeleft = "$days day" . ($days !== 1 ? 's' : '') . " $hours hour" . ($hours !== 1 ? 's' : '');
        } else {
            $timeleft = "Expired";
        }
        $data['Time remaining'] = $timeleft;
    }

    foreach ($data as $label => $value) {
        echo html_writer::start_tag('tr');
        echo html_writer::tag('td', '<strong>' . $label . '</strong>', ['style' => 'width: 50%;']);
        echo html_writer::tag('td', $value);
        echo html_writer::end_tag('tr');
    }

    echo html_writer::end_tag('tbody');
    echo html_writer::end_tag('table');


echo $OUTPUT->footer();
