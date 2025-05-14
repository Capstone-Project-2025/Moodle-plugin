<?php
require_once('../../config.php');

$courseid = required_param('id', PARAM_INT);
require_login($courseid);

$course = get_course($courseid);
$context = context_course::instance($courseid);
require_capability('mod/programmingassign:view', $context);

$PAGE->set_url(new moodle_url('/mod/programmingassign/list.php', ['id' => $courseid]));
$PAGE->set_title(get_string('pluginname', 'mod_programmingassign'));
$PAGE->set_heading($course->fullname);

echo $OUTPUT->header();
echo $OUTPUT->heading('Programming Assignments');

// Affiche les entêtes
echo html_writer::tag('h4', 'Assignments Overview');

echo html_writer::start_div('', ['style' => 'max-width: 900px; margin: 0 auto;']);
echo html_writer::start_tag('table', ['class' => 'generaltable', 'style' => 'width: 100%; border-collapse: collapse;']);
echo html_writer::start_tag('thead');
echo html_writer::start_tag('tr');

$headers = ['Name', 'Created At', 'Due Date', 'Language', 'Status', 'Action'];
foreach ($headers as $header) {
    echo html_writer::tag('th', $header, ['style' => 'border: 1px solid #ddd; padding: 8px; text-align: left; background-color: #f2f2f2;']);
}

echo html_writer::end_tag('tr');
echo html_writer::end_tag('thead');

echo html_writer::start_tag('tbody');

// Récupération des assignements
$assignments = $DB->get_records('programmingassign', ['course' => $courseid]);

foreach ($assignments as $assign) {
    $cm = get_coursemodule_from_instance('programmingassign', $assign->id, $courseid);
    $url = new moodle_url('/mod/programmingassign/details.php', ['id' => $assign->id]);

    $created = !empty($assign->timecreated) ? userdate($assign->timecreated) : '-';
    $due = !empty($assign->duedate) ? userdate($assign->duedate) : '-';
    $language = !empty($assign->language) ? s($assign->language) : '-';

    // Calcul du statut
    if (!empty($assign->duedate)) {
        $status = (time() > $assign->duedate) ? 'Finished' : 'In Progress';
    } else {
        $status = '-';
    }

    echo html_writer::start_tag('tr');
    echo html_writer::tag('td', format_string($assign->name), ['style' => 'border: 1px solid #ddd; padding: 8px;']);
    echo html_writer::tag('td', $created, ['style' => 'border: 1px solid #ddd; padding: 8px;']);
    echo html_writer::tag('td', $due, ['style' => 'border: 1px solid #ddd; padding: 8px;']);
    echo html_writer::tag('td', $language, ['style' => 'border: 1px solid #ddd; padding: 8px;']);
    echo html_writer::tag('td', $status, ['style' => 'border: 1px solid #ddd; padding: 8px;']);
    echo html_writer::tag('td',
        html_writer::link($url, 'View', [
            'class' => 'btn btn-primary',
            'style' => 'background-color: #0073e6; color: white; padding: 4px 10px; border-radius: 4px; text-decoration: none;'
        ]),
        ['style' => 'border: 1px solid #ddd; padding: 8px;']
    );
    echo html_writer::end_tag('tr');
}

echo html_writer::end_tag('tbody');
echo html_writer::end_tag('table');
echo html_writer::end_div();

echo $OUTPUT->footer();
