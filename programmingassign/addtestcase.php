<?php
require_once('../../config.php');
require_login();

$code = required_param('code', PARAM_TEXT);
$PAGE->set_url(new moodle_url('/mod/programmingassign/addtestcase.php', ['code' => $code]));
$PAGE->set_context(context_system::instance());
$PAGE->set_title('Edit Problem Data');
$PAGE->set_heading('Edit Problem Data');

$message = '';
$testcases = [];
$metadata = [];
$zipfileurl = null;

$apiurl = 'http://139.59.105.152';
$username = 'admin';
$password = 'admin';

// Authentification
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
    // Récupération des données existantes
    $curl = curl_init("$apiurl/api/v2/problem/$code/test_data");
    curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($curl, CURLOPT_HTTPGET, true);
    curl_setopt($curl, CURLOPT_HTTPHEADER, ["Authorization: Bearer $token"]);
    $result = curl_exec($curl);
    $httpcode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
    curl_close($curl);

    if ($httpcode === 200) {
        $json = json_decode($result, true);
        echo html_writer::start_div('alert alert-secondary');
echo '<strong>🔍 Debug GET /test_data:</strong><br>';
echo '<pre>';
var_dump($json);
echo '</pre>';
echo html_writer::end_div();
        $testcases = $json['test_cases'] ?? [];
        $metadata = $json['problem_data'] ?? [];
        $zipfileurl = $json['zipfile_download_url'] ?? null; // <=== ici on récupère l'URL du zip
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

        $postdata = [
            'problem_data.checker' => $checker,
            'problem_data.checker_args' => json_encode(new stdClass()),
            'problem_data.output_limit' => $output_limit,
            'problem_data.output_prefix' => $output_prefix,
            'problem_data.unicode' => $unicode ? 'true' : 'false',
            'problem_data.nobigmath' => $nobigmath ? 'true' : 'false',
            'test_cases' => json_encode($formatted_cases)
        ];

        // ==============================
        // 🔄 Gestion du zip file
        // ==============================

        if (!empty($_FILES['zipfile']['tmp_name'])) {
            $postdata['problem_data.zipfile'] = new CURLFile($_FILES['zipfile']['tmp_name'], 'application/zip', $_FILES['zipfile']['name']);
        } elseif (!empty($zipfileurl)) {
            $ch = curl_init($zipfileurl);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0');
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); // <- au cas où HTTPS est mal signé (si applicable)
curl_setopt($ch, CURLOPT_HEADER, false);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    "Authorization: Bearer $token"
]);


$downloadedZip = curl_exec($ch);
$httpcode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$err = curl_error($ch);
curl_close($ch);

// DEBUG
echo html_writer::start_div('alert alert-warning');
echo "<strong>🛠️ Debug téléchargement ZIP :</strong><br>";
echo "HTTP Code : $httpcode<br>";
echo "Erreur cURL : " . s($err) . "<br>";
echo "Taille reçue : " . strlen($downloadedZip) . " octets<br>";
echo html_writer::end_div();

if ($httpcode === 200 && $downloadedZip !== false && strlen($downloadedZip) > 100) {
    $tmpZipPath = tempnam(sys_get_temp_dir(), 'zipfile_');
    file_put_contents($tmpZipPath, $downloadedZip);
    $filename = basename(parse_url($zipfileurl, PHP_URL_PATH));
    $postdata['problem_data.zipfile'] = new CURLFile($tmpZipPath, 'application/zip', $filename);
    $message .= html_writer::div("📥 Fichier zip téléchargé automatiquement depuis l'API et utilisé.", 'alert alert-info');
} else {
    $message .= html_writer::div("⚠️ Échec du téléchargement du fichier zip (HTTP $httpcode) - cURL error: $err", 'alert alert-danger');
}


        } elseif (!empty($metadata['zipfile'])) {
            $message .= html_writer::div('📦 Ancien fichier zip conservé : ' . s($metadata['zipfile']), 'alert alert-info');
        }

        // ==============================
        // 🔄 Gestion du generator file
        // ==============================

        if (!empty($_FILES['generatorfile']['tmp_name'])) {
            $postdata['problem_data.generator'] = new CURLFile($_FILES['generatorfile']['tmp_name'], 'text/plain', $_FILES['generatorfile']['name']);
        } elseif (!empty($metadata['generator'])) {
            $message .= html_writer::div('⚙️ Ancien générateur conservé : ' . s($metadata['generator']), 'alert alert-info');
        }

        // Envoi vers l'API
        $curl = curl_init("$apiurl/api/v2/problem/$code/test_data");
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl, CURLOPT_POST, true);
        curl_setopt($curl, CURLOPT_POSTFIELDS, $postdata);
        curl_setopt($curl, CURLOPT_HTTPHEADER, ["Authorization: Bearer $token"]);
        $response = curl_exec($curl);
        $httpcode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        curl_close($curl);

        if ($httpcode === 200 || $httpcode === 201) {
            $message .= html_writer::div("✅ Données modifiées avec succès (HTTP $httpcode)", 'alert alert-success');
        } else {
            $error = json_decode($response, true);
            $errormsg = $error['detail'] ?? json_encode($error);
            $message .= html_writer::div("❌ Erreur API (HTTP $httpcode)<br>" . s($errormsg), 'alert alert-danger');
        }
    }
} else {
    $message = html_writer::div('❌ Échec de l\'authentification.', 'alert alert-danger');
}

// === Affichage formulaire ===
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
if (!empty($metadata['zipfile'])) {
    echo '<p>🗂️ Fichier zip actuel : <strong>' . s($metadata['zipfile']) . '</strong></p>';
}

echo '<label>⚙️ Generator file (optional) :</label>';
echo html_writer::empty_tag('input', ['type' => 'file', 'name' => 'generatorfile', 'accept' => '.py,.cpp,.sh,.txt', 'class' => 'form-control']);
if (!empty($metadata['generator'])) {
    echo '<p>🧮 Fichier générateur actuel : <strong>' . s($metadata['generator']) . '</strong></p>';
}

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
        <td><input type='text' name='test_cases[$i].type' value='$type' class='form-control'></td>
        <td><input type='text' name='test_cases[$i].input_file' value='$input' class='form-control'></td>
        <td><input type='text' name='test_cases[$i].output_file' value='$output' class='form-control'></td>
        <td><input type='number' name='test_cases[$i].points' value='$points' class='form-control'></td>
        <td><input type='number' name='test_cases[$i].order' value='$order' class='form-control'></td>
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
        <td><input type="text" name="test_cases[\${caseIndex}].type" value="C" class="form-control"></td>
        <td><input type="text" name="test_cases[\${caseIndex}].input_file" class="form-control"></td>
        <td><input type="text" name="test_cases[\${caseIndex}].output_file" class="form-control"></td>
        <td><input type="number" name="test_cases[\${caseIndex}].points" value="0" class="form-control"></td>
        <td><input type="number" name="test_cases[\${caseIndex}].order" value="\${caseIndex + 1}" class="form-control"></td>
        <td><button type="button" onclick="removeRow(this)">❌</button></td>
    `;
    tbody.appendChild(row);
    caseIndex++;
}
function removeRow(button) {
    button.closest('tr').remove();
}
</script>
<?php
echo $OUTPUT->footer();
