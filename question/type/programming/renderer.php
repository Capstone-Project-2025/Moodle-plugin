<?php 
defined('MOODLE_INTERNAL') || die();

class qtype_programming_renderer extends qtype_renderer
{

    public function head_code(question_attempt $qa)
    {
        global $PAGE;

        $baseurl = '/question/type/programming/thirdparty/codemirror';

        // ✅ CSS
        $PAGE->requires->css("$baseurl/lib/codemirror.css");
        $PAGE->requires->css("$baseurl/theme/material-darker.css");
        $PAGE->requires->css("$baseurl/theme/eclipse.css");

        // ✅ JS core
        $PAGE->requires->js(new moodle_url("$baseurl/lib/codemirror.js"), true);

        // ✅ Modes nécessaires
        $PAGE->requires->js(new moodle_url("$baseurl/mode/clike/clike.js"), true);
        $PAGE->requires->js(new moodle_url("$baseurl/mode/python/python.js"), true);
        $PAGE->requires->js(new moodle_url("$baseurl/mode/pascal/pascal.js"), true);
        $PAGE->requires->js(new moodle_url("$baseurl/mode/perl/perl.js"), true);
        $PAGE->requires->js(new moodle_url("$baseurl/mode/gas/gas.js"), true);
        $PAGE->requires->js(new moodle_url("$baseurl/mode/shell/shell.js"), true);
        $PAGE->requires->js(new moodle_url("$baseurl/mode/brainfuck/brainfuck.js"), true);
        $PAGE->requires->js(new moodle_url("$baseurl/mode/awk/awk.js"), true);

        // (optionnel) récupérer les options d'affichage si nécessaire
        // $options = $qa->get_display_options();

        return parent::head_code($qa);
    }


    public function formulation_and_controls(question_attempt $qa, question_display_options $options)
    {
        global $DB, $PAGE;

        $question = $qa->get_question();
        $response = $qa->get_last_qt_data();
        $answer = $response['answer'] ?? '';
        $questionid = $question->id;

        // 📦 Retrieve settings for the programming question
        $questionProgramming = $DB->get_record('qtype_programming_options', ['questionid' => $questionid]);
        $problemid = $questionProgramming->problem_id ?? null;

        $problemcode = '(no code)';
        $name = '(no name)';
        $description = '(no description)';

        if ($problemid) {
            $problem = $DB->get_record(
                'programming_problem',
                ['id' => $problemid],
                'id, code, name, description',
                IGNORE_MISSING
            );

            if ($problem) {
                $problemcode = $problem->code;
                $name = $problem->name;
                $description = $problem->description;
            }
        }

        $inputname = $qa->get_qt_field_name('answer');

        // 🔁 Retrieve allowed programming languages for this problem
        $languages = [];
        if ($problemid) {
            $sql = "SELECT l.id, l.name
                    FROM {programming_language} l
                    JOIN {programming_problem_language} pl ON pl.language_id = l.id
                    WHERE pl.problem_id = :problemid
                    ORDER BY l.name ASC";

            $languages = $DB->get_records_sql_menu($sql, ['problemid' => $problemid]);

            // Format languages for template rendering
            $languages = array_map(function ($id, $name) {
                return ['id' => $id, 'name' => $name];
            }, array_keys($languages), $languages);
        }

        // 🧩 UI element IDs
        $slot = $qa->get_slot();
        $selectid = 'language_select_' . $slot;
        $themebtnid = 'theme_toggle_' . $slot;
        $submitbuttonid = 'submitbtn_' . $slot;
        $editorid = 'code_editor_' . $slot;
        $resultcontainerid = 'submission_result_' . $slot;

        // 🌐 URLs
        $submiturl = new moodle_url('/question/type/programming/submission/submit.php');
        $getsubmissionurl = new moodle_url('/question/type/programming/submission/getsubmission.php');
        $getsubmissionListUrl = new moodle_url('/question/type/programming/submission/getsubmissionslist.php');

        // 📘 Get course info based on attempt
        $attemptid = required_param('attempt', PARAM_INT);

        $sql = "
            SELECT qa.questionid, c.id AS courseid, c.fullname
            FROM {quiz_attempts} qza
            JOIN {question_attempts} qa ON qa.questionusageid = qza.uniqueid
            JOIN {quiz} qz ON qz.id = qza.quiz
            JOIN {course} c ON c.id = qz.course
            WHERE qza.id = :attemptid
            LIMIT 1
        ";

        $params = ['attemptid' => $attemptid];
        $record = $DB->get_record_sql($sql, $params);

        // 💡 Load CodeMirror only once
        if (!defined('QTYPE_PROGRAMMING_CODEMIRROR_LOADED')) {
            define('QTYPE_PROGRAMMING_CODEMIRROR_LOADED', true);


            $output = '';


            $PAGE->requires->js_call_amd('qtype_programming/codemirror_loader', 'init', [
                $editorid,
                strtolower($languages[0]['name'] ?? 'text'),  // ex: 'cpp17', 'python3'
                'material-darker',
                $themebtnid,
                $selectid  // ID du <select> pour écouter les changements
            ]);


            // 📦 Load JavaScript for submission handler
            $PAGE->requires->js_call_amd('qtype_programming/submission', 'init', [[
                'inputId' => $editorid,
                'selectId' => $selectid,
                'submitButtonId' => $submitbuttonid,
                'themeButtonId' => $themebtnid,
                'resultContainerId' => $resultcontainerid,
                'submitUrl' => $submiturl->out(false),
                'resultUrl' => $getsubmissionurl->out(false),
                'submissionListUrl' => $getsubmissionListUrl->out(false),
                'problemCode' => $problemcode,
                'sesskey' => sesskey(),
                'questionId' => $questionProgramming->id,
                'showSubmissionsButtonId' => 'showsubmissionsbtn_' . $slot,
                'submissionListContainerId' => 'submissionlist_' . $slot,
                'submissionIdName' => $qa->get_qt_field_name('submission_id'),
                'slot' => $slot,
                'attemptid' => $attemptid,
            ]]);

            // 🖥️ Render the HTML using the Mustache template
            return $output . $this->render_from_template('qtype_programming/renderer', [
                    'problemcode' => $problemcode,
                    'name' => $name,
                    'description' => format_text($description, FORMAT_MARKDOWN),
                    'inputname' => $inputname,
                    'editorid' => $editorid,
                    'selectId' => $selectid,
                    'submitButtonId' => $submitbuttonid,
                    'themeButtonId' => $themebtnid,
                    'resultContainerId' => $resultcontainerid,
                    'showSubmissionsButtonId' => 'showsubmissionsbtn_' . $slot,
                    'submissionListContainerId' => 'submissionlist_' . $slot,
                    'answer' => $answer,
                    'languages' => $languages,
                    'submissionidname' => $qa->get_qt_field_name('submission_id'),
                    'submissionid' => $response['submission_id'] ?? ''
                ]);
        }
    }
}
