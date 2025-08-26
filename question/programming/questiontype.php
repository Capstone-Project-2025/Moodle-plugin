<?php
defined('MOODLE_INTERNAL') || die();

/**
 * Main class for the Programming question type.
 */
require_once($CFG->dirroot . '/question/type/questiontypebase.php');
use local_dmoj_user_link\api\ProblemList;
use local_dmoj_user_link\api\ProblemTestData;

class qtype_programming extends question_type {

    public function save_question_options($question) {
        global $DB, $USER;

        if ($question->problem_mode === 'new') {
            // Sanitize inputs
            $code = trim($question->new_code);

            $name = trim($question->new_name);
            $name = preg_replace('/\s+/u', ' ', $name);
            $name = strip_tags($name);

            // Description
            $rawdescription = $question->new_description['text'] ?? '';
            $cleanhtml = clean_text($rawdescription, FORMAT_HTML);  // assainit le HTML
            $description = trim(html_to_text($cleanhtml));

            $time_limit = floatval($question->new_time_limit);
            $memory_limit = intval($question->new_memory_limit);
            $points = 100;
            $difficulty = $question->new_difficulty;
            $ispublic = !empty($question->new_is_public) ? 1 : 0;

            $types = [];
            if (!empty($question->new_types) && is_array($question->new_types)) {
                foreach ($question->new_types as $typeid => $checked) {
                    if ($checked) {
                        $types[] = (int)$typeid;
                    }
                }
            }

            $languages = [];
            if (!empty($question->new_languages) && is_array($question->new_languages)) {
                foreach ($question->new_languages as $langid => $checked) {
                    if ($checked) {
                        $languages[] = (int)$langid;
                    }
                }
            }

            $payload = [
                'code' => $code,
                'name' => $name,
                'description' => $description,
                'points' => $points,
                'difficulty' => $difficulty,
                'time_limit' => $time_limit,
                'memory_limit' => $memory_limit,
                'types' => $types,
                'group' => '1',
                'allowed_languages' => $languages,
                'is_public' => (bool)$ispublic
            ];

            try {

                $response = ProblemList::create($payload);
                $status = $response['status'] ?? 0;
                if ($status !== 201) {
                    $body = $response['body'];
                    $errormsg = $body['detail'] ?? json_encode($body);
                    throw new \moodle_exception("API upload failed: HTTP $status - $errormsg");
                }
            } catch (Exception $e) {
                throw new \moodle_exception('API error: ' . $e->getMessage());
            }

            $existing = $DB->get_record('programming_problem', ['code' => $code]);
            if ($existing) {
                throw new \moodle_exception('A problem with this code already exists: ' . $code);
            }

            $problem = new stdClass();
            $problem->code = $code;
            $problem->name = $name;
            $problem->description = $description;
            $problem->userid = $USER->id;
            $problem->ispublic = $ispublic;
            $problem->points = $points;
            $problem->difficulty = $difficulty;
            $problem->time_limit = $time_limit;
            $problem->memory_limit = $memory_limit;


            $problemid = $DB->insert_record('programming_problem', $problem);

            foreach (array_unique($types) as $typeid) {
                $type = new stdClass();
                $type->problem_id = $problemid;
                $type->type_id = $typeid;
                $DB->insert_record('programming_problem_type', $type);
            }

            foreach (array_unique($languages) as $langid) {
                $lang = new stdClass();
                $lang->problem_id = $problemid;
                $lang->language_id = $langid;
                $DB->insert_record('programming_problem_language', $lang);
            }

            // ➕ Ajout des test cases depuis le formulaire
            $zipfile = null;
            $filename = null;
            $draftitemid = $question->zipfile ?? 0;

            if ($draftitemid) {
                $fs = get_file_storage();
                $usercontext = context_user::instance($USER->id);
                $files = $fs->get_area_files($usercontext->id, 'user', 'draft', $draftitemid, 'id', false);

                foreach ($files as $file) {
                    if ($file->get_mimetype() === 'application/zip') {
                        $filename = $file->get_filename();
                        $zipfile = $file->copy_content_to_temp(); // Chemin absolu temporaire
                        break;
                    }
                }
            }

            if (!empty($zipfile)) {
                $testcases = extract_testcases_from_zip($zipfile);
                $formatted_cases = [];

                $rawcases = json_decode($question->test_cases_json ?? '[]', true);

                if (!empty($rawcases)) {
                    foreach ($rawcases as $index => $case) {
                        $points = isset($case['points']) ? (int)$case['points'] : 2;
                        if ($points < 1) { $points = 1; }
                        $formatted_cases[] = [
                            'type' => $case['type'] ?? 'C',
                            'input_file' => $case['input_file'] ?? '',
                            'output_file' => $case['output_file'] ?? '',
                            'points' => intval($case['points'] ?? 0),
                            'order' => intval($case['order'] ?? ($index + 1)),
                        ];
                    }
                } else {
                    // Sinon : fallback avec .in/.out
                    foreach ($testcases as $index => $case) {
                        $formatted_cases[] = [
                            'type' => 'C',
                            'input_file' => $case['input_file'],
                            'output_file' => $case['output_file'],
                            'points' => 1,
                            'order' => $index + 1
                        ];
                    }
                }

                $checker = $question->checker ?? '';
                $precision = $question->checker_precision ?? null;

                $checker_args = in_array($checker, ['floats', 'floatsabs', 'floatsrel'])
                    ? json_encode(['precision' => $precision])
                    : json_encode(new stdClass());

                $problem_data = [
                    'checker' => $checker,
                    'checker_args' => $checker_args,
                    'unicode' => !empty($question->unicode),
                    'nobigmath' => !empty($question->nobigmath)
                ];

                if (!empty($question->output_limit)) {
                    $problem_data['output_limit'] = (int)$question->output_limit;
                }
                if (!empty($question->output_prefix)) {
                    $problem_data['output_prefix'] = (int)$question->output_prefix;
                }

                $flatdata = array_merge(
                    ProblemTestData::flatten_payload(['problem_data' => $problem_data]),
                    ProblemTestData::flatten_payload(['test_cases' => $formatted_cases])
                );

                $flatdata['problem_data.zipfile'] = new \CURLFile(
                    $zipfile,
                    'application/zip',
                    $filename
                );

                $response = ProblemTestData::update_data($code, $flatdata);
                if (!in_array($response['status'], [200, 201])) {
                    $body = $response['body'];
                    $errormsg = $body['detail'] ?? json_encode($body);
                    throw new \moodle_exception("Failed to save testcases: HTTP {$response['status']} - $errormsg");
                }
            }



            $question->name = $name;
            $question->questiontext = $description;
            $question->questiontextformat = FORMAT_PLAIN;

            $problemcode = $code;

        } else {
            $problemid = $question->problemid;
            $problemcode = $question->problemcode;

            if ($problemid && is_numeric($problemid)) {
                $record = $DB->get_record('programming_problem', ['id' => $problemid], '*', IGNORE_MISSING);
                if ($record && !empty($record->name)) {
                    $question->name = $record->name;
                    $question->questiontext = $record->description ?? '';
                    $question->questiontextformat = FORMAT_PLAIN;
                }
            }


        }

        // === Enregistrement dans qtype_programming_options ===
        if ($existing = $DB->get_record('qtype_programming_options', ['questionid' => $question->id])) {
            $options = $existing;
        } else {
            $options = new stdClass();
            $options->questionid = $question->id;
        }

        $options->problem_id = $problemid;
        $options->problemcode = $problemcode;

        if (isset($question->problemname)) {
            $options->problemname = $question->problemname;
        }

        if (isset($options->id)) {
            $DB->update_record('qtype_programming_options', $options);
        } else {
            $DB->insert_record('qtype_programming_options', $options);
        }

        return true;
    }

    public function get_question_options($question) {
        global $DB;

        // ✅ Vérifie si contextid est bien défini et valide
        if (empty($question->contextid) && !empty($question->category)) {
            // ➕ On récupère le contextid depuis la catégorie
            $question->contextid = $DB->get_field('question_categories', 'contextid', ['id' => $question->category]);
        }

        // ✅ Encore une sécurité au cas où
        if (empty($question->contextid) || !is_numeric($question->contextid)) {
            debugging('❌ contextid manquant ou invalide dans get_question_options', DEBUG_DEVELOPER);
            return false;
        }

        // ✅ Chargement du contexte (on ne l’utilise pas ici, mais peut être utile plus tard)
        $context = context::instance_by_id((int) $question->contextid, IGNORE_MISSING);

        // ✅ Récupère les options personnalisées de qtype_programming
        $options = $DB->get_record('qtype_programming_options', ['questionid' => $question->id]);

        // ✅ Affecte le code du problème à la question
        if ($question instanceof qtype_programming_question) {
            $question->problemcode = $options->problemcode ?? '';
        }

        return true;
    }


    public function delete_question($questionid, $contextid) {
        global $DB;
        $DB->delete_records('qtype_programming_options', ['questionid' => $questionid]);
        parent::delete_question($questionid, $contextid);
    }

    public function make_question($questiondata) {
        global $DB;

        $context = context::instance_by_id($questiondata->contextid ?? 0, IGNORE_MISSING);

        question_bank::load_question_definition_classes($this->name());

        $question = new qtype_programming_question();
        $this->initialise_question_instance($question, $questiondata);

        $options = $DB->get_record('qtype_programming_options', ['questionid' => $question->id]);
        $question->problemcode = $options->problemcode ?? 'NO_CODE';

        return $question;
    }

    public function is_usable_by($component) {
        return true;
    }
}

/**
 * Utility function to extract testcases from ZIP
 *
 * @param string $zipPath
 * @return array
 */
function extract_testcases_from_zip(string $zipPath): array {
    $zip = new ZipArchive();
    $cases = [];

    if ($zip->open($zipPath) === true) {
        $entries = [];
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $entries[] = $zip->getNameIndex($i);
        }

        $input_files = array_filter($entries, fn($f) => substr($f, -3) === '.in');
        $output_files = array_filter($entries, fn($f) => substr($f, -4) === '.out');

        foreach ($input_files as $infile) {
            $basename = pathinfo($infile, PATHINFO_FILENAME);
            $outname = $basename . '.out';

            if (in_array($outname, $output_files)) {
                $cases[] = [
                    'input_file' => $infile,
                    'output_file' => $outname
                ];
            }
        }

        $zip->close();
    }

    return $cases;
}

/**
 * Utility function to get the course module from a context.
 *
 * @param context $context
 * @return stdClass|null
 */
function get_coursemodule_from_context($context) {
    if ($context->contextlevel != CONTEXT_MODULE) {
        return null;
    }
    return get_coursemodule_from_id(false, $context->instanceid, 0, false, IGNORE_MISSING);
}
