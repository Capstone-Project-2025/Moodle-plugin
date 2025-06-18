<?php
require_once('../../config.php');
require_login();

use local_programming\api\ProblemList;

$PAGE->set_url(new moodle_url('/mod/programmingassign/uploadproblem.php'));
$PAGE->set_context(context_system::instance());
$PAGE->set_title('Upload New Problem');

$message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_sesskey();

    // 🧾 Récupération des paramètres du formulaire
    $code = required_param('code', PARAM_TEXT);
    $name = required_param('name', PARAM_TEXT);
    $description = required_param('description', PARAM_RAW);
    $points = required_param('points', PARAM_INT);
    $time_limit = required_param('time_limit', PARAM_FLOAT);
    $memory_limit = required_param('memory_limit', PARAM_INT);
    $types = required_param_array('types', PARAM_TEXT); // 👈 peut être PARAM_TEXT si en chaîne CSV
    $group = required_param('group', PARAM_INT);
    $languages = optional_param_array('allowed_languages', [], PARAM_INT);

    // Création du tableau de données à envoyer
    $payload = [
        'code' => $code,
        'name' => $name,
        'description' => $description,
        'points' => $points,
        'time_limit' => $time_limit,
        'memory_limit' => $memory_limit,
        'types' => $types,
        'group' => $group,
        'allowed_languages' => $languages,
        'is_public' => true
    ];

    try {
        $api = new ProblemList();
        $response = $api->create($payload);
        $status = $response['status'] ?? 0;

        if ($status === 201) {
            $message = html_writer::div('✅ Problem successfully uploaded!', 'alert alert-success');
        } else {
            $body = json_decode($response['body'], true);
            $errormsg = $body['detail'] ?? json_encode($body);
            $message = html_writer::div("❌ Failed to upload problem (HTTP $status)<br>" . s($errormsg), 'alert alert-danger');
        }
    } catch (Exception $e) {
        $message = html_writer::div("❌ Error: " . s($e->getMessage()), 'alert alert-danger');
    }
}

echo $OUTPUT->header();
echo $message;

// === Wrapper container ===
echo html_writer::start_div('form-container', [
    'style' => 'max-width: 800px; margin: 0 auto; padding: 30px; background: #f9f9f9; border: 1px solid #ddd; border-radius: 8px;'
]);

echo html_writer::tag('h3', '📝 Submit New Problem', ['style' => 'text-align: center; margin-bottom: 20px;']);

// === Upload Form ===
echo html_writer::start_tag('form', [
    'method' => 'post',
    'action' => '',
]);
echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'sesskey', 'value' => sesskey()]);

echo html_writer::tag('label', 'Problem code');
echo html_writer::empty_tag('input', ['type' => 'text', 'name' => 'code', 'required' => true, 'style' => 'width: 100%; margin-bottom: 10px;']);

echo html_writer::tag('label', 'Problem name');
echo html_writer::empty_tag('input', ['type' => 'text', 'name' => 'name', 'required' => true, 'style' => 'width: 100%; margin-bottom: 10px;']);

echo html_writer::tag('label', 'Description');
echo html_writer::tag('textarea', '', ['name' => 'description', 'rows' => 6, 'style' => 'width: 100%; margin-bottom: 10px;']);

echo html_writer::tag('label', 'Points');
echo html_writer::empty_tag('input', ['type' => 'number', 'name' => 'points', 'value' => 100, 'required' => true, 'style' => 'width: 100%; margin-bottom: 10px;']);

echo html_writer::tag('label', 'Time limit (seconds)');
echo html_writer::empty_tag('input', ['type' => 'number', 'name' => 'time_limit', 'value' => 2.0, 'step' => '0.1', 'required' => true, 'style' => 'width: 100%; margin-bottom: 10px;']);

echo html_writer::tag('label', 'Memory limit (KB)');
echo html_writer::empty_tag('input', ['type' => 'number', 'name' => 'memory_limit', 'value' => 262144, 'required' => true, 'style' => 'width: 100%; margin-bottom: 10px;']);

echo html_writer::tag('label', 'Problem types (IDs)');
echo html_writer::empty_tag('input', ['type' => 'text', 'name' => 'types[]', 'value' => '1', 'required' => true, 'style' => 'width: 100%; margin-bottom: 10px;']);

echo html_writer::tag('label', 'Group ID');
echo html_writer::empty_tag('input', ['type' => 'number', 'name' => 'group', 'value' => 1, 'required' => true, 'style' => 'width: 100%; margin-bottom: 10px;']);

echo html_writer::tag('label', 'Allowed languages:');
echo '<div style="display: flex; flex-wrap: wrap; gap: 10px; margin-bottom: 20px; padding: 10px; border: 1px solid #ccc; border-radius: 6px;">';

$langs = [
    3 => 'AWK', 17 => 'Brain****', 4 => 'C', 16 => 'C11', 5 => 'C++03',
    6 => 'C++11', 13 => 'C++14', 15 => 'C++17', 18 => 'C++20',
    11 => 'Assembly (x86)', 2 => 'Assembly (x64)', 9 => 'Java 8',
    14 => 'Pascal', 7 => 'Perl', 1 => 'Python 2', 8 => 'Python 3',
    12 => 'Sed', 10 => 'Text'
];

foreach ($langs as $id => $label) {
    echo '<label style="width: calc(100% / 6 - 10px); display: flex; align-items: center;">';
    echo '<input type="checkbox" name="allowed_languages[]" value="' . $id . '" id="lang' . $id . '">';
    echo '<span style="margin-left: 6px;">' . $label . '</span>';
    echo '</label>';
}

echo '</div>';

echo html_writer::empty_tag('input', [
    'type' => 'submit',
    'name' => 'submit',
    'value' => 'Upload Problem',
    'class' => 'btn btn-primary',
    'style' => 'width: 100%; padding: 10px;'
]);

echo html_writer::end_tag('form');
echo html_writer::end_div();
echo $OUTPUT->footer();
