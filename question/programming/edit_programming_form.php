<?php
define('PROBLEM_CODE_CHAR_LIMIT', 20);

defined('MOODLE_INTERNAL') || die();
require_once($CFG->dirroot . '/question/type/edit_question_form.php');

class qtype_programming_edit_form extends question_edit_form {

    protected function definition() {
        global $PAGE, $DB;
        $mform = $this->_form;

        // === MODE SELECTION ===
        $mform->addElement('select', 'problem_mode', get_string('problem_mode', 'qtype_programming'), [
            'existing' => 'Use existing problem',
            'new' => 'Create new problem'
        ]);
        $mform->setDefault('problem_mode', 'existing');
        $mform->setType('problem_mode', PARAM_ALPHA);

        // === EXISTING PROBLEM ===
        $mform->addElement('header', 'existing_problem_section', 'Select Existing Problem');

        $mform->addElement('select', 'problemlist', get_string('problemname', 'qtype_programming'), ['' => '-- undefined --']);
        $mform->setType('problemlist', PARAM_TEXT);

        $mform->addElement('text', 'problemcode_display', get_string('problemcode', 'qtype_programming'), ['readonly' => 'readonly']);
        $mform->setType('problemcode_display', PARAM_TEXT);

        $mform->addElement('hidden', 'problemcode');
        $mform->setType('problemcode', PARAM_TEXT);

        $mform->addElement('hidden', 'problemid');
        $mform->setType('problemid', PARAM_INT);

        $mform->addElement('textarea', 'description_display', get_string('description', 'qtype_programming'), [
            'readonly' => 'readonly', 'rows' => 6, 'style' => 'width:100%; background:#f9f9f9;'
        ]);
        $mform->setType('description_display', PARAM_RAW);



        // === NEW PROBLEM ===
        $mform->addElement('header', 'new_problem_section', 'Create New Problem');

        $mform->addElement('text', 'new_code', 'Problem Code', ['maxlength' => PROBLEM_CODE_CHAR_LIMIT]);
        $mform->setType('new_code', PARAM_TEXT);
        $mform->addRule('new_code', get_string('maximumchars', '', PROBLEM_CODE_CHAR_LIMIT), 'maxlength', PROBLEM_CODE_CHAR_LIMIT, 'server');

        $mform->addElement('text', 'new_name', 'Problem Name');
        $mform->setType('new_name', PARAM_TEXT);

        $mform->addElement('editor', 'new_description', 'Description');
        $mform->setType('new_description', PARAM_RAW);


        $mform->addElement('select', 'new_difficulty', 'Difficulty', [
            'easy' => 'Easy',
            'medium' => 'Medium',
            'hard' => 'Hard'
        ]);
        $mform->setType('new_difficulty', PARAM_TEXT);

        $mform->addElement('advcheckbox', 'new_is_public', 'Public');
        $mform->setType('new_is_public', PARAM_BOOL);


        $mform->addElement('text', 'new_time_limit', 'Time Limit (sec)');
        $mform->setType('new_time_limit', PARAM_FLOAT);
        $mform->setDefault('new_time_limit', 2.5);

        $mform->addElement('text', 'new_memory_limit', 'Memory Limit (KB)');
        $mform->setType('new_memory_limit', PARAM_FLOAT);
        $mform->setDefault('new_memory_limit', 25000);

        // === TYPES & LANGUAGES ===
        $types = $DB->get_records_menu('programming_type', null, '', 'id, name');
        $languages = $DB->get_records_menu('programming_language', null, '', 'id, name');

        $mform->addElement('html', '<div><strong>Problem Types</strong></div>');
        $mform->addElement('html', '<div class="checkbox-grid">');

        foreach ($types as $id => $typename) {
            $mform->addElement('advcheckbox', "new_types[$id]", '', $typename);
            $mform->setType("new_types[$id]", PARAM_BOOL);
        }

        $mform->addElement('html', '</div>');


        $mform->addElement('html', '<div><strong>Allowed Languages</strong></div>');
        $mform->addElement('html', '<div class="checkbox-grid">');

        foreach ($languages as $id => $langname) {
            $mform->addElement('advcheckbox', "new_languages[$id]", '', $langname);
            $mform->setType("new_languages[$id]", PARAM_BOOL);
        }

        $mform->addElement('html', '</div>');


        // === TEST CASE OPTIONS ===
        $mform->addElement('header', 'testcase_section', 'Test Case Settings');

        $maxsize = 200 * 1024 * 1024;
        $mform->addElement('filepicker', 'zipfile', 'Test cases ZIP (.zip)', null, [
            'accepted_types' => ['.zip'],
            'maxbytes' => $maxsize
        ]);

        $mform->addElement('static', 'zipfile_info', '', '
            <div style="margin-top: 1em; line-height: 1.5;">
                📁 Max file size: <strong>' . display_size($maxsize) . '</strong><br>
                📄 Accepted formats:
                <span style="font-family: monospace; font-size: 100%; color: #000;">.in.(suffix)</span>,
                <span style="font-family: monospace; font-size: 100%; color: #000;">.out.(suffix)</span>,
                <span style="font-family: monospace; font-size: 100%; color: #000;">.txt(suffix)</span><br>
                <small style="color: #444;">e.g. testcase.in.1</small>
            </div>
        ');

        $mform->addElement('html', '<div id="zip-feedback" style="margin-top:10px; font-weight:bold; color:green;"></div>');





        $mform->addElement('select', 'checker', 'Checker', [
            '' => '---',
            'standard' => 'Standard',
            'floats' => 'Floats (epsilon)',
            'floatsabs' => 'Floats (absolute)',
            'floatsrel' => 'Floats (relative)',
            'identical' => 'Identical (strict)',
            'linecount' => 'Line-by-line',
            'sorted' => 'Sorted lines'
        ]);
        $mform->setType('checker', PARAM_TEXT);

        $mform->addElement('text', 'checker_precision', 'Precision (for floats)');
        $mform->setType('checker_precision', PARAM_INT);
        $mform->setDefault('checker_precision', 6);

        $mform->addElement('text', 'output_limit', 'Output Limit Length');
        $mform->setType('output_limit', PARAM_INT);

        $mform->addElement('text', 'output_prefix', 'Output Prefix Length');
        $mform->setType('output_prefix', PARAM_INT);

        $mform->addElement('advcheckbox', 'unicode', 'Enable Unicode');
        $mform->setType('unicode', PARAM_BOOL);

        $mform->addElement('advcheckbox', 'nobigmath', 'Disable BigInteger/BigDecimal');
        $mform->setType('nobigmath', PARAM_BOOL);

        $mform->addElement('html', '
            <div class="form-container">
                <h4>✏️ Test Cases</h4>
                <table class="generaltable" id="testcase-table" style="width: 100%; margin-bottom: 20px;">
                  <thead>
                    <tr>
                      <th>Type</th><th>Input File</th><th>Output File</th><th>Points</th><th>Action</th>
                    </tr>
                  </thead>
                  <tbody id="testcase-body"></tbody>
                </table>
                <button type="button" class="btn btn-secondary" onclick="addTestCase()">➕ Add Test Case</button>
            </div>
        ');
        $mform->addElement('hidden', 'test_cases_json');
        $mform->setType('test_cases_json', PARAM_RAW);


        // ... TOUTE TA DÉFINITION EST INCHANGÉE AVANT JS ...

// === MOODLE FIELDS ===
        parent::definition();

        // === Champs requis pour toute question ===
        if (!$mform->elementExists('name')) {
            $mform->addElement('text', 'name', get_string('name', 'question'));
            $mform->setType('name', PARAM_TEXT);
        }


        $mform->setType('name', PARAM_TEXT);
        $qtWrapper = $mform->getElement('questiontext');
        if ($qtWrapper) {
            $mform->setType('questiontext', PARAM_RAW);
        }

// ✅ URL vers parsezip.php
        $parsezipurl = new moodle_url('/question/type/programming/parsezip.php');
        $parsezipurljs = $parsezipurl->out(false);

// ✅ JS: prêt à coller
        $PAGE->requires->js_init_code('
const parseZipUrl = "' . $parsezipurljs . '";
let latestInputOptions = "";
let latestOutputOptions = "";

document.addEventListener("DOMContentLoaded", function () {
    var modeSelect = document.querySelector("[name=\"problem_mode\"]");
    var existingSection = document.getElementById("id_existing_problem_section");
    var newSection = document.getElementById("id_new_problem_section");
    var testcaseSection = document.getElementById("id_testcase_section");
    var feedbackBox = document.getElementById("zip-feedback");

    var select = document.querySelector("[name=\"problemlist\"]");
    var nameField = document.querySelector("[name=\"name\"]");
    var codeField = document.querySelector("[name=\"problemcode\"]");
    var problemIdField = document.querySelector("[name=\"problemid\"]");
    var codeDisplay = document.querySelector("[name=\"problemcode_display\"]");
    var descriptionDisplay = document.querySelector("[name=\"description_display\"]");
    var qtTextField = document.querySelector("[name=\"questiontext[text]\"]");
    var editor = document.querySelector("[name=\"new_description[text]\"]");

    var qtWrapper = document.querySelector("[id^=\"fitem_id_questiontext\"]");
    var nameWrapper = document.querySelector("[id^=\"fitem_id_name\"]");
    if (qtWrapper) qtWrapper.style.display = "none";
    if (nameWrapper) nameWrapper.style.display = "none";

    function toggleSections() {
        var mode = modeSelect ? modeSelect.value : "existing";
        if (existingSection) existingSection.style.display = (mode === "existing") ? "" : "none";
        if (newSection) newSection.style.display = (mode === "new") ? "" : "none";
        if (testcaseSection) testcaseSection.style.display = (mode === "new") ? "" : "none";
    }

    toggleSections();
    if (modeSelect) {
        modeSelect.addEventListener("change", toggleSections);
    }

    // Remplit la liste des problèmes existants
    fetch(M.cfg.wwwroot + "/question/type/programming/fetchproblems.php")
        .then(function (r) { return r.json(); })
        .then(function (data) {
            if (!select) return;
            data.forEach(function (p) {
                var opt = document.createElement("option");
                opt.value = p.code;
                opt.textContent = p.name;
                select.appendChild(opt);
            });
        });

    if (select) {
        select.addEventListener("change", function () {
            var code = this.value;
            if (!code) return;
            fetch(M.cfg.wwwroot + "/question/type/programming/fetchproblem.php?code=" + encodeURIComponent(code))
                .then(function (r) { return r.json(); })
                .then(function (data) {
                    var desc = data.description || "";
                    if (nameField) nameField.value = data.name || "";
                    if (codeField) codeField.value = data.code || "";
                    if (problemIdField) problemIdField.value = data.id || "";
                    if (codeDisplay) codeDisplay.value = data.code || "";
                    if (descriptionDisplay) descriptionDisplay.value = desc;
                    if (qtTextField) qtTextField.value = desc;
                });
        });
    }

    // Sauvegarde : hydrate les champs cachés
    var form = document.querySelector("form");
    if (form) {
        form.addEventListener("submit", function () {
            var mode = modeSelect ? modeSelect.value : "existing";
            if (!qtTextField || !nameField) return;

            if (mode === "existing") {
                qtTextField.value = descriptionDisplay ? descriptionDisplay.value : "";
                if (select && select.selectedIndex >= 0) {
                    nameField.value = select.options[select.selectedIndex].textContent;
                } else {
                    nameField.value = "Unnamed existing problem";
                }
            } else {
                if (editor) {
                    var tempDiv = document.createElement("div");
                    tempDiv.innerHTML = editor.value;
                    qtTextField.value = tempDiv.textContent || tempDiv.innerText || "";
                }
                var newNameInput = document.querySelector("[name=\"new_name\"]");
                if (newNameInput) {
                    nameField.value = newNameInput.value || "Unnamed new problem";
                }

                var testCases = [];
                var rows = document.querySelectorAll("#testcase-body tr");
                for (var i = 0; i < rows.length; i++) {
                    var row = rows[i];
                    var typeEl   = row.querySelector("[name$=\"[type]\"]");
                    var inputEl  = row.querySelector("[name$=\"[input_file]\"]");
                    var outputEl = row.querySelector("[name$=\"[output_file]\"]");
                    var pointsEl = row.querySelector("[name$=\"[points]\"]");
                    
                    var p = parseInt(pointsEl && pointsEl.value !== "" ? pointsEl.value : "2", 10);
                    if (isNaN(p) || p < 1) { p = 1; }
                    

                    testCases.push({
                        type:  typeEl  ? typeEl.value  : "C",
                        input_file:  inputEl  ? inputEl.value  : "",
                        output_file: outputEl ? outputEl.value : "",
                        points: p,
                        order: i + 1
                    });
                }

                var hidden = document.querySelector("[name=\"test_cases_json\"]");
                if (hidden) {
                    try { hidden.value = JSON.stringify(testCases); } catch (e) { hidden.value = "[]"; }
                }
            }
        });
    }

    // Fonctions utilitaires pour la table des test cases
    window.addTestCase = function () {
        var caseIndex = document.querySelectorAll("#testcase-body tr").length;
        var tbody = document.getElementById("testcase-body");
        if (!tbody) return;

        var row = document.createElement("tr");
        var html = "";
        html += "<td>";
        html +=   "<select name=\"test_cases[" + caseIndex + "][type]\" class=\"form-control\" onchange=\"handleTypeChange(this)\">";
        html +=     "<option value=\"C\" selected>Normal case</option>";
        html +=     "<option value=\"S\">Batch start</option>";
        html +=     "<option value=\"E\">Batch end</option>";
        html +=   "</select>";
        html += "</td>";
        html += "<td><select name=\"test_cases[" + caseIndex + "][input_file]\" class=\"form-control\">" + latestInputOptions + "</select></td>";
        html += "<td><select name=\"test_cases[" + caseIndex + "][output_file]\" class=\"form-control\">" + latestOutputOptions + "</select></td>";
        html += "<td><input type=\"number\" name=\"test_cases[" + caseIndex + "][points]\" value=\"2\" min=\"1\" step=\"1\" class=\"form-control\"></td>";
        html += "<td><button type=\"button\" onclick=\"removeRow(this)\">❌</button></td>";
        row.innerHTML = html;
        tbody.appendChild(row);
    };

    window.removeRow = function (btn) {
        var tr = btn ? btn.closest("tr") : null;
        if (tr && tr.parentNode) tr.parentNode.removeChild(tr);
    };

    window.handleTypeChange = function (selectEl) {
        var row = selectEl ? selectEl.closest("tr") : null;
        if (!row) return;
        var type = selectEl.value;
        var disable = (type !== "C");

        var fields = ["input_file", "output_file", "points"];
        for (var i = 0; i < fields.length; i++) {
            var el = row.querySelector("[name$=\"[" + fields[i] + "]\"]");
            if (el) {
                el.disabled = disable;
                if (disable) { el.classList.add("disabled"); }
                else { el.classList.remove("disabled"); }
            }
        }
    };

    // ========= Re-parse du ZIP à chaque changement dans le filepicker =========
    function updateAllSelects() {
        var ins = document.querySelectorAll("select[name$=\"[input_file]\"]");
        var outs = document.querySelectorAll("select[name$=\"[output_file]\"]");
        for (var i = 0; i < ins.length; i++)  { ins[i].innerHTML  = latestInputOptions; }
        for (var j = 0; j < outs.length; j++) { outs[j].innerHTML = latestOutputOptions; }
    }

    function parseZipBlob(blob, fileName) {
        var formData = new FormData();
        formData.append("zipfile", blob, fileName);
        formData.append("sesskey", M.cfg.sesskey);

        return fetch(parseZipUrl, {
            method: "POST",
            body: formData,
            credentials: "same-origin"
        })
        .then(function (res) { return res.json(); })
        .then(function (data) {
            if (data.error) {
                if (feedbackBox) { feedbackBox.textContent = "❌ ZIP error: " + data.error; feedbackBox.style.color = "red"; }
                latestInputOptions = "";
                latestOutputOptions = "";
                updateAllSelects();
                return;
            }
            if (feedbackBox) { feedbackBox.textContent = ""; feedbackBox.style.color = "green"; }

            // Pas de templates littéraux pour compat PHP 7.3 (pas d\'${...} interprété par PHP)
            latestInputOptions  = data.input_files.map(function (f) {
                return "<option value=\"" + f + "\">" + f + "</option>";
            }).join("");

            latestOutputOptions = data.output_files.map(function (f) {
                return "<option value=\"" + f + "\">" + f + "</option>";
            }).join("");

            updateAllSelects();
        })
        .catch(function (err) {
            if (feedbackBox) { feedbackBox.textContent = "❌ AJAX error: " + err.message; feedbackBox.style.color = "red"; }
            if (window.console && console.error) console.error(err);
        });
    }

    function processCurrentZipFromPicker() {
        // Essaie l\'ID standard du filepicker créé par MoodleQuickForm (adapter si thème diffère)
        var link = document.querySelector("#id_zipfile_filepicker .filepicker-filename a");
        if (!link) {
            // fallback : chercher n\'importe quel lien de filename dans le fitem du zipfile
            var fitem = document.getElementById("fitem_id_zipfile") || document;
            link = fitem.querySelector(".filepicker-filename a");
        }

        if (!link || !/\\.zip(\\?|$)/i.test(link.href)) {
            latestInputOptions = "";
            latestOutputOptions = "";
            updateAllSelects();
            if (feedbackBox) feedbackBox.textContent = "";
            return;
        }

        // cache-buster pour éviter un blob mis en cache
        var fileUrl = link.href + (link.href.indexOf("?") >= 0 ? "&" : "?") + "t=" + Date.now();
        var fileName = link.textContent || "testcases.zip";

        fetch(fileUrl, { cache: "no-store" })
            .then(function (res) { return res.blob(); })
            .then(function (blob) { return parseZipBlob(blob, fileName); });
    }

    // Observe les changements (ajout/remplacement/suppression) du filepicker
    (function initZipObserver() {
        var fpContainer = document.querySelector("#id_zipfile_filepicker");
        if (!fpContainer) {
            // fallback : le conteneur fitem
            fpContainer = document.getElementById("fitem_id_zipfile");
        }
        if (!fpContainer) {
            // dernier fallback : le document complet (moins optimal)
            fpContainer = document;
        }

        var target = fpContainer.querySelector(".filepicker-filename") || fpContainer;
        var observer = new MutationObserver(function () { processCurrentZipFromPicker(); });
        observer.observe(target, { childList: true, subtree: true, characterData: true });

        // premier essai si un ZIP est déjà présent
        processCurrentZipFromPicker();
    })();

    // Astérisques pour champs requis (sans optional chaining)
    var requiredFields = ["new_code", "new_name", "new_description", "new_types[]", "new_languages[]"];
    for (var r = 0; r < requiredFields.length; r++) {
        var input = document.querySelector("[name=\"" + requiredFields[r] + "\"]");
        if (input) {
            var fitem = input.closest(".fitem");
            var label = fitem ? fitem.querySelector("label") : null;
            if (label && label.innerHTML.indexOf("<abbr") === -1) {
                label.innerHTML += " <abbr class=\"required\" title=\"required\">*</abbr>";
            }
        }
    }
});
');


    }

    public function qtype() {
        return 'programming';
    }

    public function set_data($question) {
        if (isset($question->options)) {
            $question->problemcode = $question->options->problemcode;
            $question->problemid = $question->options->problem_id;
        }

        if (empty($question->new_description)) {
            $question->new_description = [
                'text' => '',
                'format' => FORMAT_HTML,
                'itemid' => 0
            ];
        }

        if (!empty($question->problem_mode) && $question->problem_mode === 'new') {
            if (!empty($question->new_name)) {
                $question->name = $question->new_name;
            }
        } else if (!empty($question->problem_mode) && $question->problem_mode === 'existing') {
            if (!empty($question->problemlist)) {
                $question->name = $question->problemlist;
            }
        }

        if (empty($question->name)) {
            $question->name = 'Auto-generated name';
        }

        $question->checker = $question->checker ?? '';
        $question->checker_precision = $question->checker_precision ?? 6;
        $question->output_limit = $question->output_limit ?? '';
        $question->output_prefix = $question->output_prefix ?? '';
        $question->unicode = $question->unicode ?? 0;
        $question->nobigmath = $question->nobigmath ?? 0;

        parent::set_data($question);
    }

    public function validation($data, $files) {

        error_log('validationnnn ');
        $errors = parent::validation($data, $files);
        error_log('QUESTION NAME = ' . $_POST['name']);
        error_log('QUESTION TEXT = ' . $_POST['questiontext']['text']);

        if ($data['problem_mode'] === 'new') {
            error_log('present dans mode new ');

            if (empty($data['new_code'])) {
                $errors['new_code'] = get_string('required');
            }
            if (empty($data['new_name'])) {
                $errors['new_name'] = get_string('required');
            }
            if (empty($data['new_description']['text'])) {
                $errors['new_description'] = get_string('required');
            }
            if (empty($data['new_types'])) {
                $errors['new_types'] = get_string('required');
            }
            if (empty($data['new_languages'])) {
                $errors['new_languages'] = get_string('required');
            }

            if (!empty($data['new_time_limit']) && strpos($data['new_time_limit'], ',') !== false) {
                $errors['new_time_limit'] = 'Please use a dot (.) as the decimal separator.';
            }

            if (!empty($data['new_memory_limit']) && strpos($data['new_memory_limit'], ',') !== false) {
                $errors['new_memory_limit'] = 'Please use a dot (.) as the decimal separator.';
            }


            // Récupère les test_cases depuis le champ JSON
            $rawjson = $data['test_cases_json'] ?? '';
            $cases = json_decode($rawjson, true);

            $hasZip = !empty($_FILES['zipfile']['tmp_name']);
            $hasCases = is_array($cases) && count($cases) > 0;

            if (!$hasZip && !$hasCases) {
                $errors['zipfile'] = 'You must upload a zip file and define at least one test case.';
            }


        }

        if ($data['problem_mode'] === 'existing') {
            if (empty($data['problemid']) || empty($data['problemcode'])) {
                $errors['problemlist'] = get_string('required');
            }
        }

        return $errors;
    }
}
