<?php

// 👇 Détection requête AJAX ZIP dès le début
if ($_SERVER['REQUEST_METHOD'] === 'POST'
    && !empty($_FILES['zipfile']['tmp_name'])
    && isset($_GET['ajax']) && $_GET['ajax'] === '1') {

    define('AJAX_SCRIPT', true);

    require_once(__DIR__ . '/../../../config.php');
    require_login(null, false);
    require_sesskey();

    header('Content-Type: application/json');

    if (!isset($_FILES['zipfile']) || !is_uploaded_file($_FILES['zipfile']['tmp_name'])) {
        echo json_encode(['error' => 'No valid zip file uploaded']);
        exit;
    }

    $tmpfile = $_FILES['zipfile']['tmp_name'];
    $zip = new ZipArchive();

    if ($zip->open($tmpfile) !== true) {
        echo json_encode(['error' => 'Failed to open ZIP']);
        exit;
    }

    $input_files = [];
    $output_files = [];

    for ($i = 0; $i < $zip->numFiles; $i++) {
        $name = $zip->getNameIndex($i);

        // Ignore les dossiers dans le ZIP
        if (substr($name, -1) === '/') continue;

        // 🔥 Utiliser le nom complet, avec le chemin (ex: logical/logicari.in.1)
        if (preg_match('/\.in(\.|$)/i', $name)) {
            $input_files[] = $name;
        } elseif (preg_match('/\.out(\.|$)/i', $name)) {
            $output_files[] = $name;
        } elseif (preg_match('/\.txt$/i', $name)) {
            $input_files[] = $name;
            $output_files[] = $name;
        }
    }

    $zip->close();

    echo json_encode([
        'input_files' => $input_files,
        'output_files' => $output_files
    ]);
    exit;
}

require_once('../../../config.php'); // Rechargé pour le reste
require_login();

use local_dmoj_user_link\api\ProblemTestData;

$code = required_param('code', PARAM_TEXT);

// ⚠️ si ce fichier est bien sous mod/progcontest/, adapte l’URL ci-dessous :
$PAGE->set_url(new moodle_url('/local/dmoj_user_link/problems/addtestcase.php', ['code' => $code]));
$PAGE->set_context(context_system::instance());
$PAGE->set_title('Edit Problem Data');
$PAGE->set_heading('Edit Problem Data');

$message = '';
$testcases = [];
$metadata = [];
$zipfileurl = null;
$input_files_from_zip = [];
$output_files_from_zip = [];

/**
 * 🔍 Lecture des fichiers du zip
 */
function extract_testcases_from_zip(string $zipPath): array {
    $zip = new ZipArchive();
    $cases = [];

    if ($zip->open($zipPath) === true) {
        $entries = [];
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $entries[] = $zip->getNameIndex($i);
        }

        $input_files = array_filter($entries, function($f) {
            return substr($f, -3) === '.in';
        });
        $output_files = array_filter($entries, function($f) {
            return substr($f, -4) === '.out';
        });

        foreach ($input_files as $infile) {
            $basename = pathinfo($infile, PATHINFO_FILENAME);
            $outname = $basename . '.out';

            if (in_array($outname, $output_files)) {
                $cases[] = [
                    'type' => 'C',
                    'input_file' => $infile,
                    'output_file' => $outname,
                    'points' => 2,
                    'order' => count($cases) + 1
                ];
            }
        }

        $zip->close();
    }

    return $cases;
}

try {
    $response = ProblemTestData::get_data($code);
    $status = $response['status'];
    if ($status === 200) {
        $json = $response['body'];
        $testcases = $json['data']['test_cases'] ?? [];
        $metadata = $json['data']['problem_data'] ?? [];
        $zipfileurl = $json['zipfile_download_url'] ?? null;
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        require_sesskey();

        // ZIP analysé lors du POST manuel
        if (!empty($_FILES['zipfile']['tmp_name'])) {
            $testcases = extract_testcases_from_zip($_FILES['zipfile']['tmp_name']);
            foreach ($testcases as $case) {
                $input_files_from_zip[] = $case['input_file'];
                $output_files_from_zip[] = $case['output_file'];
            }

            if (!isset($_POST['submitfull'])) {
                $message .= html_writer::div("✅ Zip analysé avec succès. Vous pouvez maintenant modifier ou compléter les test cases.", 'alert alert-info');
            }
        }

        if (isset($_POST['submitfull'])) {
            $checker_raw = optional_param('checker', '', PARAM_RAW);
            $checker = is_array($checker_raw) ? implode(',', array_map('trim', $checker_raw)) : trim($checker_raw ?? '');

            $checker_args_array = optional_param_array('checker_args', [], PARAM_RAW);
            $checker_args = null;

            if (in_array($checker, ['floats', 'floatsabs', 'floatsrel'])) {
                $precision = isset($checker_args_array['precision']) ? trim($checker_args_array['precision']) : '';
                // On envoie toujours un objet JSON contenant "precision"
                $checker_args = json_encode(['precision' => $precision]);
            } else {
                // Pour les autres checkers, on peut envoyer un objet vide
                $checker_args = json_encode(new stdClass());
            }

            $output_limit = optional_param('output_limit', null, PARAM_RAW);
            $output_prefix = optional_param('output_prefix', null, PARAM_RAW);
            $unicode = optional_param('unicode', 0, PARAM_BOOL);
            $nobigmath = optional_param('nobigmath', 0, PARAM_BOOL);

            $post_cases = clean_param_array($_POST['test_cases'] ?? [], PARAM_RAW, true);
            $formatted_cases = [];

            foreach ($post_cases as $index => $tc) {
                $type = $tc['type'] ?? 'C';

                $entry = ['type' => $type];

                if ($type === 'C') {
                    $input = trim($tc['input_file'] ?? '');
                    $output = trim($tc['output_file'] ?? '');
                    if (!$input || !$output) {
                        continue; // Ignore si les fichiers sont manquants
                    }
                    $entry['input_file'] = $input;
                    $entry['output_file'] = $output;
                    $entry['points'] = isset($tc['points']) ? (int)$tc['points'] : 2;
                    $entry['order'] = isset($tc['order']) ? (int)$tc['order'] : $index + 1;
                }

                $formatted_cases[] = $entry;
            }

            $problem_data = [
                'checker' => $checker,
                'checker_args' => $checker_args,
                'unicode' => $unicode,
                'nobigmath' => $nobigmath
            ];

            if ($output_limit !== null && $output_limit !== '') {
                $problem_data['output_limit'] = (int)$output_limit;
            }
            if ($output_prefix !== null && $output_prefix !== '') {
                $problem_data['output_prefix'] = (int)$output_prefix;
            }

            $flatdata = array_merge(
                ProblemTestData::flatten_payload(['problem_data' => $problem_data]),
                ProblemTestData::flatten_payload(['test_cases' => $formatted_cases])
            );

            if (!empty($_FILES['zipfile']['tmp_name'])) {
                $flatdata['problem_data.zipfile'] = new \CURLFile(
                    $_FILES['zipfile']['tmp_name'],
                    'application/zip',
                    $_FILES['zipfile']['name']
                );
            } elseif (!empty($zipfileurl)) {
                $curlfile = ProblemTestData::download_zip_protected($zipfileurl);
                if ($curlfile) {
                    $flatdata['problem_data.zipfile'] = $curlfile;
                }
            }

            // ✅ generator supprimé ici

            $putresponse = ProblemTestData::update_data($code, $flatdata);
            if (in_array($putresponse['status'], [200, 201])) {
                $message .= html_writer::div("✅ Changes saved successfully (HTTP {$putresponse['status']})", 'alert alert-success');
            } else {
                $body = $putresponse['body'];
                $errormsg = $body['detail'] ?? json_encode($body);
                $message .= html_writer::div("❌ API error (HTTP {$putresponse['status']})<br>" . s($errormsg), 'alert alert-danger');
            }
        }
    }

    // 🧩 Liste des checkers
    $selectedchecker = $metadata['checker'] ?? '';
    $checkers = [
        ['value' => '',             'label' => '-------',                'selected' => $selectedchecker === ''],
        ['value' => 'standard',     'label' => 'Standard (default)',     'selected' => $selectedchecker === 'standard'],
        ['value' => 'floats',       'label' => 'Floats (epsilon error)', 'selected' => $selectedchecker === 'floats'],
        ['value' => 'floatsabs',    'label' => 'Floats (absolute)',      'selected' => $selectedchecker === 'floatsabs'],
        ['value' => 'floatsrel',    'label' => 'Floats (relative)',      'selected' => $selectedchecker === 'floatsrel'],
        ['value' => 'identical',    'label' => 'Identical (strict)',     'selected' => $selectedchecker === 'identical'],
        ['value' => 'linecount',    'label' => 'Line-by-line',           'selected' => $selectedchecker === 'linecount'],
        ['value' => 'sorted',       'label' => 'Sorted (lines)',         'selected' => $selectedchecker === 'sorted'],
    ];

} catch (Exception $e) {
    $message .= html_writer::div("❌ Exception: " . s($e->getMessage()), 'alert alert-danger');
}

echo $OUTPUT->header();

// ⚠️ adapte le nom du template si tu l’as déjà migré sous mod_progcontest
echo $OUTPUT->render_from_template('local_dmoj_user_link/edit_problem_data', [
    'code' => $code,
    'message' => $message,
    'sesskey' => sesskey(),
    'zipfileurl' => $zipfileurl,
    'metadata' => $metadata,
    'testcases' => $testcases,
    'checkers' => $checkers,
    'input_files' => $input_files_from_zip,
    'output_files' => $output_files_from_zip

]);

echo $OUTPUT->footer();
