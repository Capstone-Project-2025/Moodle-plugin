<?php
require_once('../../config.php');
require_login();

$PAGE->set_url(new moodle_url('/mod/programmingassign/uploadproblem.php'));
$PAGE->set_context(context_system::instance());
$PAGE->set_title('Upload New Problem');


$message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_sesskey();

    $code = required_param('code', PARAM_TEXT);
    $name = required_param('name', PARAM_TEXT);
    $description = required_param('description', PARAM_RAW);
    $points = required_param('points', PARAM_INT);
    $time_limit = required_param('time_limit', PARAM_FLOAT);
    $memory_limit = required_param('memory_limit', PARAM_INT);
    $types = required_param_array('types', PARAM_INT);
    $group = required_param('group', PARAM_INT);
    $languages = optional_param_array('allowed_languages', [], PARAM_INT);

    // Auth config
    $apiurl = 'http://139.59.105.152';
    $username = 'basicuser1';
    $password = 'RoadRageWarrior1!';

    // Get token
    $curl = curl_init("$apiurl/api/token/");
    curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($curl, CURLOPT_POST, true);
    curl_setopt($curl, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    curl_setopt($curl, CURLOPT_POSTFIELDS, json_encode([
        'username' => $username,
        'password' => $password
    ]));
    $response = curl_exec($curl);
    $data = json_decode($response, true);
    curl_close($curl);

    $token = $data['access'] ?? null;

    if ($token) {
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

        $curl = curl_init("$apiurl/api/v2/problems");
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl, CURLOPT_POST, true);
        curl_setopt($curl, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            "Authorization: Bearer $token"
        ]);
        curl_setopt($curl, CURLOPT_POSTFIELDS, json_encode($payload));
        $result = curl_exec($curl);
        $httpcode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        curl_close($curl);

        if ($httpcode === 201) {
            $message = html_writer::div('✅ Problem successfully uploaded!', 'alert alert-success');
        } else {
            $error = json_decode($result, true);
            $errormsg = $error['detail'] ?? json_encode($error);
            $message = html_writer::div('❌ Failed to upload problem (HTTP ' . $httpcode . ')<br>' . s($errormsg), 'alert alert-danger');
        }
    } else {
        $message = html_writer::div('❌ Authentication failed. Please check credentials.', 'alert alert-danger');
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

echo html_writer::tag('label', 'Problem types (IDs, comma-separated)');
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
