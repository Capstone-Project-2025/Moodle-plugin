<?php 
defined('MOODLE_INTERNAL') || die();

class qtype_prog_renderer extends qtype_renderer {

    public function formulation_and_controls(question_attempt $qa, question_display_options $options) {
        global $DB, $PAGE;

        $question = $qa->get_question();
        $response = $qa->get_last_qt_data();
        $answer = $response['answer'] ?? '';
        $questionid = $question->id;

        // 📦 Retrieve settings for the prog question
        $questionProgramming = $DB->get_record('qtype_prog_options', ['questionid' => $questionid]);
        $problemid = $questionProgramming->problem_id ?? null;

        $problemcode = '(no code)';
        $name = '(no name)';
        $description = '(no description)';

        if ($problemid) {
            $problem = $DB->get_record(
                'local_prog_problem',
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

        // 🔁 Retrieve allowed prog languages for this problem
        $languages = [];
        if ($problemid) {
            $sql = "SELECT l.id, l.name
                    FROM {local_prog_language} l
                    JOIN {local_prog_problem_language} pl ON pl.language_id = l.id
                    WHERE pl.problem_id = :problemid
                    ORDER BY l.name ASC";

            $languages = $DB->get_records_sql_menu($sql, ['problemid' => $problemid]);

            // Format languages for template rendering
            $languages = array_map(function($id, $name) {
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
        $submiturl = new moodle_url('/question/type/prog/submission/submit.php');
        $getsubmissionurl = new moodle_url('/question/type/prog/submission/getsubmission.php');
        $getsubmissionListUrl = new moodle_url('/question/type/prog/submission/getsubmissionslist.php');

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

            $output = '
<script>
document.addEventListener("DOMContentLoaded", function () {
    const editorElement = document.getElementById("' . $editorid . '");
    if (!editorElement || typeof CodeMirror === "undefined") return;

    const langMap = {
        1: "python", 8: "python",
        4: "text/x-csrc",
        5: "text/x-c++src", 6: "text/x-c++src", 13: "text/x-c++src", 15: "text/x-c++src", 18: "text/x-c++src",
        9: "text/x-java",
        10: "text/plain"
    };

    const editor = CodeMirror.fromTextArea(editorElement, {
        lineNumbers: true,
        mode: langMap[parseInt(document.getElementById("' . $selectid . '").value, 10)] || "text/plain",
        theme: "material-darker",
        indentUnit: 4,
        tabSize: 4,
        indentWithTabs: false
    });

    window["codemirrorEditor_' . $slot . '"] = editor;

    let currentTheme = "material-darker";
    const themeBtn = document.getElementById("' . $themebtnid . '");
    if (themeBtn) {
        themeBtn.addEventListener("click", function () {
            currentTheme = currentTheme === "material-darker" ? "eclipse" : "material-darker";
            editor.setOption("theme", currentTheme);
        });
    }

    const select = document.getElementById("' . $selectid . '");
    if (select) {
        select.addEventListener("change", function () {
            const selected = parseInt(this.value, 10);
            const mode = langMap[selected] || "text/plain";
            editor.setOption("mode", mode);
        });
    }
});
</script>
';
        } else {
            $output = '';
        }

        // 📦 Load JavaScript for submission handler
        $PAGE->requires->js_call_amd('qtype_prog/submission', 'init', [[
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
        return $output . $this->render_from_template('qtype_prog/renderer', [
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
