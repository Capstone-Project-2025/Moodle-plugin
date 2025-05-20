<?php
defined('MOODLE_INTERNAL') || die();

class qtype_programming_renderer extends qtype_renderer {

    private function fetch_problem_data($problemcode) {
        $apiurl = 'http://139.59.105.152';
        $username = 'admin';
        $password = 'admin';

        $curl = curl_init("$apiurl/api/token/");
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl, CURLOPT_POST, true);
        curl_setopt($curl, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
        curl_setopt($curl, CURLOPT_POSTFIELDS, json_encode([
            'username' => $username,
            'password' => $password
        ]));
        $response = curl_exec($curl);
        curl_close($curl);
        $data = json_decode($response, true);
        $token = $data['access'] ?? null;

        if (!$token) {
            return null;
        }

        $curl = curl_init("$apiurl/api/v2/problem/$problemcode");
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl, CURLOPT_HTTPHEADER, [
            "Authorization: Bearer $token"
        ]);
        $response = curl_exec($curl);
        curl_close($curl);

        $problem = json_decode($response, true);
        return $problem['data']['object'] ?? null;
    }

    public function formulation_and_controls(question_attempt $qa, question_display_options $options) {
        static $alreadydisplayed = false;

        $question = $qa->get_question();
        $codeOfProblem = $question->problemcode ?? '';
        $response = $qa->get_last_qt_data();
        $answer = $response['answer'] ?? '';

        $output = '';

        if (!$alreadydisplayed && !empty($codeOfProblem)) {
            $apidata = $this->fetch_problem_data($codeOfProblem);
            $name = $apidata['name'] ?? '(no name)';
            $description = $apidata['description'] ?? '(no description)';

            $output .= html_writer::div(
                "<h3>Programming Problem</h3>" .
                "<strong>Code:</strong> " . s($codeOfProblem) . "<br>" .
                "<strong>Name:</strong> " . s($name) . "<br><br>" .
                "<strong>Description:</strong><br>" .
                format_text($description, FORMAT_MARKDOWN),
                'global-problem-info',
                ['style' => 'border: 2px solid blue; padding: 10px; margin-bottom: 20px; background: #eef;']
            );

            $alreadydisplayed = true;
        }

        $inputname = $qa->get_qt_field_name('answer');
        $langs = [
            3 => 'AWK', 17 => 'Brain****', 4 => 'C', 16 => 'C11', 5 => 'C++03',
            6 => 'C++11', 13 => 'C++14', 15 => 'C++17', 18 => 'C++20',
            11 => 'Assembly (x86)', 2 => 'Assembly (x64)', 9 => 'Java 8',
            14 => 'Pascal', 7 => 'Perl', 1 => 'Python 2', 8 => 'Python 3',
            12 => 'Sed', 10 => 'Text'
        ];

        $selectid = 'language_select_' . $qa->get_slot();
        $themebtnid = 'theme_toggle_' . $qa->get_slot();
        $output .= html_writer::tag('button', 'Switch Theme', [
            'type' => 'button',
            'id' => $themebtnid,
            'style' => 'margin: 10px 5px; background-color: #ccc; padding: 5px 10px; font-weight: bold;'
        ]);
        $submitbuttonid = 'submitbtn_' . $qa->get_slot();
        $problemcode = $question->problemcode;
        $submiturl = (new moodle_url('/question/type/programming/submission/submit.php'))->out(false);
        $getsubmissionurl = (new moodle_url('/question/type/programming/submission/getsubmission.php'))->out(false);

        $output .= html_writer::tag('textarea', s($answer), [
            'name' => $inputname,
            'id' => $inputname,
            'rows' => 10,
            'cols' => 80,
            'style' => 'width: 100%; margin-top: 15px;',
        ]);

        $output .= html_writer::start_tag('label', ['for' => $selectid]) . 'Choose language:' . html_writer::end_tag('label');
        $output .= html_writer::start_tag('select', ['id' => $selectid, 'style' => 'margin-top: 10px; margin-bottom: 10px;']);
        foreach ($langs as $id => $name) {
            $output .= html_writer::tag('option', $name, ['value' => $id]);
        }
        $output .= html_writer::end_tag('select');

        $output .= html_writer::empty_tag('br');
        $output .= html_writer::tag('button', 'Submit', [
            'type' => 'button',
            'id' => $submitbuttonid,
            'style' => 'margin-top: 10px; background-color: yellow; padding: 5px 10px; font-weight: bold;',
        ]);
        $output .= html_writer::div('', 'submission-result', ['id' => 'submission_result_' . $qa->get_slot(), 'style' => 'margin-top: 10px;']);

        // CodeMirror + JS
        $output .= '
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.16/codemirror.min.css">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.16/theme/material-darker.min.css">
<script src="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.16/codemirror.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.16/mode/meta.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.16/mode/python/python.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.16/mode/clike/clike.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.16/mode/javascript/javascript.min.js"></script>


<script>
const editor = CodeMirror.fromTextArea(document.getElementById("' . $inputname . '"), {
    lineNumbers: true,
    mode: "python", // valeur par défaut
    theme: "material-darker",
    indentUnit: 4,
    tabSize: 4,
    indentWithTabs: false
});

let currentTheme = "material-darker";

const themeBtn = document.getElementById("' . $themebtnid . '");
themeBtn.addEventListener("click", function () {
    currentTheme = currentTheme === "material-darker" ? "default" : "material-darker";
    editor.setOption("theme", currentTheme);
});

const langMap = {
    1: "python", 8: "python",
    4: "text/x-csrc",
    5: "text/x-c++src", 6: "text/x-c++src", 13: "text/x-c++src", 15: "text/x-c++src", 18: "text/x-c++src",
    9: "text/x-java",
    10: "text/plain"
};

const langSelect = document.getElementById("' . $selectid . '");
langSelect.addEventListener("change", function () {
    const selectedLang = parseInt(langSelect.value);
    const mode = langMap[selectedLang] || "text/plain";
    editor.setOption("mode", mode);
});

document.getElementById("' . $submitbuttonid . '").addEventListener("click", function () {
    const code = editor.getValue();
    const languageId = parseInt(langSelect.value);
    const resultDiv = document.getElementById("submission_result_' . $qa->get_slot() . '");
    resultDiv.innerHTML = "Submitting...";

    fetch("' . $submiturl . '", {
        method: "POST",
        headers: {
            "Content-Type": "application/json"
        },
        body: JSON.stringify({
            code: code,
            problemcode: "' . $problemcode . '",
            language: languageId
        })
    })
    .then(response => response.text())
    .then(text => {
        try {
            const data = JSON.parse(text);
            if (data.submission_id) {
                resultDiv.innerHTML = "<strong>Submission ID:</strong> " + data.submission_id + "<br>Checking result...";
                fetch("' . $getsubmissionurl . '", {
                    method: "POST",
                    headers: {
                        "Content-Type": "application/json"
                    },
                    body: JSON.stringify({ submission_id: data.submission_id })
                })
                .then(resp => resp.json())
                .then(res => {
                    let explanation = "";
                    if (res.status === "AC") {
                        explanation = "<span style=\'color:green; font-weight:bold;\'>✅ Accepted</span><br>Your solution is correct.";
                    } else if (res.status === "CE") {
                        explanation = "<span style=\'color:red; font-weight:bold;\'>❌ Compilation Error</span><br>Your code failed to compile.";
                    } else if (res.status === "TLE") {
                        explanation = "<span style=\'color:orange; font-weight:bold;\'>⏱️ Time Limit Exceeded</span><br>Your code took too long to run.";
                    } else if (res.status === "WA") {
                        explanation = "<span style=\'color:red; font-weight:bold;\'>❌ Wrong Answer</span><br>Your code gave incorrect output.";
                    } else if (res.status === "IR") {
                        explanation = "<span style=\'color:red; font-weight:bold;\'>💥 Internal Error</span><br>Server-side problem during evaluation.";
                    } else {
                        explanation = "<span style=\'color:gray;\'>Status: " + res.status + ", Result: " + res.result + "</span>";
                    }

                    resultDiv.innerHTML += "<br><strong>Language:</strong> " + res.language +
                                           "<br><strong>Time:</strong> " + (res.time ?? "-") +
                                           "<br><strong>Memory:</strong> " + (res.memory ?? "-") +
                                           "<br>" + explanation;
                })
                .catch(err => {
                    resultDiv.innerHTML += "<br><span style=\'color:red;\'>Error fetching result: " + err + "</span>";
                });
            } else {
                resultDiv.innerHTML = "<span style=\\"color:red;\\">Error: " + (data.error || "Unknown error") + "</span>";
            }
        } catch (e) {
            resultDiv.innerHTML = "<span style=\\"color:red;\\">Invalid JSON returned</span>";
            console.error("JSON parse error:", e);
        }
    })
    .catch(err => {
        resultDiv.innerHTML = "<span style=\\"color:red;\\">Submission failed: " + err + "</span>";
    });
});
</script>';

        return $output;
    }
}
