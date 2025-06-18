<?php
require_once('../../config.php');
require_login();

use local_programming\api\ProblemTestData;

$code = required_param('code', PARAM_TEXT);
$PAGE->set_url(new moodle_url('/local/programming/problems/addtestcase.php', ['code' => $code]));
$PAGE->set_context(context_system::instance());
$PAGE->set_title('Edit Problem Data');
$PAGE->set_heading('Edit Problem Data');

$message = '';
$testcases = [];
$metadata = [];
$zipfileurl = null;

try {
    $api = new ProblemTestData();

    // ✅ Récupération des données existantes
    $response = $api->get($code);
    $status = $response['status'];
    if ($status === 200) {
        $json = json_decode($response['body'], true);
        $testcases = $json['data']['test_cases'] ?? [];
        $metadata = $json['data']['problem_data'] ?? [];
        $zipfileurl = $json['data']['zipfile_download_url'] ?? null;
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        require_sesskey();

        $checker = required_param('checker', PARAM_TEXT);
        $output_limit = optional_param('output_limit', '', PARAM_INT);
        $output_prefix = optional_param('output_prefix', '', PARAM_INT);
        $unicode = optional_param('unicode', 0, PARAM_BOOL);
        $nobigmath = optional_param('nobigmath', 0, PARAM_BOOL);

        $post_cases = $_POST['test_cases'] ?? [];
        $post_cases = clean_param_array($post_cases, PARAM_RAW, true);

        $formatted_cases = [];
        foreach ($post_cases as $index => $tc) {
            $input = trim($tc['input_file'] ?? '');
            $output = trim($tc['output_file'] ?? '');
            if ($input !== '' && $output !== '') {
                $formatted_cases[] = [
                    'type' => $tc['type'] ?? 'C',
                    'input_file' => $input,
                    'output_file' => $output,
                    'points' => isset($tc['points']) ? (int)$tc['points'] : 0,
                    'order' => isset($tc['order']) ? (int)$tc['order'] : $index + 1
                ];
            }
        }

        // Construction du payload à envoyer
        $payload = [
            'problem_data' => [
                'checker' => $checker,
                'checker_args' => new stdClass(),
                'output_limit' => $output_limit,
                'output_prefix' => $output_prefix,
                'unicode' => (bool)$unicode,
                'nobigmath' => (bool)$nobigmath
            ],
            'test_cases' => $formatted_cases
        ];

        // 🧩 Upload du fichier ZIP s’il est présent
        if (!empty($_FILES['zipfile']['tmp_name'])) {
            $payload['problem_data']['zipfile'] = new CURLFile($_FILES['zipfile']['tmp_name'], 'application/zip', $_FILES['zipfile']['name']);
        }

        if (!empty($_FILES['generatorfile']['tmp_name'])) {
            $payload['problem_data']['generator'] = new CURLFile($_FILES['generatorfile']['tmp_name'], 'text/plain', $_FILES['generatorfile']['name']);
        }

        $putresponse = $api->update($code, $payload);
        if (in_array($putresponse['status'], [200, 201])) {
            $message .= html_writer::div("✅ Données modifiées avec succès (HTTP {$putresponse['status']})", 'alert alert-success');
        } else {
            $body = json_decode($putresponse['body'], true);
            $errormsg = $body['detail'] ?? json_encode($body);
            $message .= html_writer::div("❌ Erreur API (HTTP {$putresponse['status']})<br>" . s($errormsg), 'alert alert-danger');
        }
    }

} catch (Exception $e) {
    $message .= html_writer::div("❌ Exception: " . s($e->getMessage()), 'alert alert-danger');
}

// =====================
// === AFFICHAGE FORM ===
// =====================
echo $OUTPUT->header();
echo $message;
echo html_writer::start_div('form-container', ['style' => 'max-width: 1000px; margin: auto;']);
echo html_writer::tag('h3', '🛠️ Edit Problem Data: ' . s($code), ['style' => 'margin-bottom: 20px;']);

echo html_writer::start_tag('form', [
    'method' => 'post',
    'enctype' => 'multipart/form-data',
    'action' => ''
]);
echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'sesskey', 'value' => sesskey()]);

function field_input($label, $name, $value = '', $type = 'text') {
    return <<<HTML
        <label>$label</label>
        <input type="$type" name="$name" value="$value" class="form-control" style="margin-bottom: 15px;" />
    HTML;
}

echo field_input('Checker', 'checker', $metadata['checker'] ?? 'standard');
echo field_input('Output Limit Length', 'output_limit', $metadata['output_limit'] ?? '', 'number');
echo field_input('Output Prefix Length', 'output_prefix', $metadata['output_prefix'] ?? '', 'number');
echo '<label><input type="checkbox" name="unicode" ' . (!empty($metadata['unicode']) ? 'checked' : '') . '> Enable Unicode</label><br>';
echo '<label><input type="checkbox" name="nobigmath" ' . (!empty($metadata['nobigmath']) ? 'checked' : '') . '> Disable bigInteger / bigDecimal</label><br><br>';

echo '<label>📦 Data zip file (.zip) :</label>';
echo html_writer::empty_tag('input', ['type' => 'file', 'name' => 'zipfile', 'accept' => '.zip', 'class' => 'form-control']);

echo '<label>⚙️ Generator file (optional) :</label>';
echo html_writer::empty_tag('input', ['type' => 'file', 'name' => 'generatorfile', 'accept' => '.py,.cpp,.sh,.txt', 'class' => 'form-control']);

echo html_writer::tag('h4', '✏️ Existing Test Cases');
echo '<table class="generaltable" id="testcase-table" style="width: 100%; margin-bottom: 20px;">';
echo '<thead><tr><th>Type</th><th>Input File</th><th>Output File</th><th>Points</th><th>Order</th><th>Action</th></tr></thead><tbody id="testcase-body">';

foreach ($testcases as $i => $tc) {
    $type = $tc['type'] ?? 'C';
    $input = $tc['input_file'] ?? '';
    $output = $tc['output_file'] ?? '';
    $points = $tc['points'] ?? 0;
    $order = $tc['order'] ?? ($i + 1);
    echo "<tr>
        <td><input type='text' name='test_cases[$i][type]' value='$type' class='form-control'></td>
        <td><input type='text' name='test_cases[$i][input_file]' value='$input' class='form-control'></td>
        <td><input type='text' name='test_cases[$i][output_file]' value='$output' class='form-control'></td>
        <td><input type='number' name='test_cases[$i][points]' value='$points' class='form-control'></td>
        <td><input type='number' name='test_cases[$i][order]' value='$order' class='form-control'></td>
        <td><button type='button' onclick='removeRow(this)'>❌</button></td>
    </tr>";
}
echo '</tbody></table>';
echo '<button type="button" class="btn btn-secondary" onclick="addTestCase()">➕ Add Test Case</button><br><br>';
echo '<input type="submit" value="💾 Submit Changes" class="btn btn-primary">';
echo html_writer::end_tag('form');
echo html_writer::end_div();
?>
<script>
let caseIndex = document.querySelectorAll('#testcase-body tr').length;
function addTestCase() {
    const tbody = document.getElementById('testcase-body');
    const row = document.createElement('tr');
    row.innerHTML = `
        <td><input type="text" name="test_cases[\${caseIndex}][type]" value="C" class="form-control"></td>
        <td><input type="text" name="test_cases[\${caseIndex}][input_file]" class="form-control"></td>
        <td><input type="text" name="test_cases[\${caseIndex}][output_file]" class="form-control"></td>
        <td><input type="number" name="test_cases[\${caseIndex}][points]" value="0" class="form-control"></td>
        <td><input type="number" name="test_cases[\${caseIndex}][order]" value="\${caseIndex + 1}" class="form-control"></td>
        <td><button type="button" onclick="removeRow(this)">❌</button></td>
    `;
    tbody.appendChild(row);
    caseIndex++;
}
function removeRow(button) {
    button.closest('tr').remove();
}
</script>
<?php echo $OUTPUT->footer(); ?>
