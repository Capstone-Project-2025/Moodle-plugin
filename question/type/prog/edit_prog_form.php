<?php

defined('MOODLE_INTERNAL') || die();
require_once($CFG->dirroot . '/question/type/edit_question_form.php');

class qtype_prog_edit_form extends question_edit_form {

    protected function definition() {
        global $PAGE, $DB;
        $mform = $this->_form;

        // === MODE SELECTION ===
        $mform->addElement('select', 'problem_mode', get_string('problem_mode', 'qtype_prog'), [
            'existing' => 'Use existing problem',
            'new' => 'Create new problem'
        ]);
        $mform->setDefault('problem_mode', 'existing');
        $mform->setType('problem_mode', PARAM_ALPHA);

        // === EXISTING PROBLEM ===
        $mform->addElement('header', 'existing_problem_section', 'Select Existing Problem');

        $mform->addElement('select', 'problemlist', get_string('problemname', 'qtype_prog'), ['' => '-- undefined --']);
        $mform->setType('problemlist', PARAM_TEXT);

        $mform->addElement('text', 'problemcode_display', get_string('problemcode', 'qtype_prog'), ['readonly' => 'readonly']);
        $mform->setType('problemcode_display', PARAM_TEXT);

        $mform->addElement('hidden', 'problemcode');
        $mform->setType('problemcode', PARAM_TEXT);

        $mform->addElement('hidden', 'problemid');
        $mform->setType('problemid', PARAM_INT);

        $mform->addElement('textarea', 'description_display', get_string('description', 'qtype_prog'), [
            'readonly' => 'readonly', 'rows' => 6, 'style' => 'width:100%; background:#f9f9f9;'
        ]);
        $mform->setType('description_display', PARAM_RAW);



        // === NEW PROBLEM ===
        $mform->addElement('header', 'new_problem_section', 'Create New Problem');

        $mform->addElement('text', 'new_code', 'Problem Code');
        $mform->setType('new_code', PARAM_TEXT);

        $mform->addElement('text', 'new_name', 'Problem Name');
        $mform->setType('new_name', PARAM_TEXT);

        $mform->addElement('editor', 'new_description', 'Description');
        $mform->setType('new_description', PARAM_RAW);

        $mform->addElement('text', 'new_points', 'Points');
        $mform->setType('new_points', PARAM_INT);
        $mform->setDefault('new_points', 100);

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
        $mform->setDefault('new_time_limit', 2.0);

        $mform->addElement('text', 'new_memory_limit', 'Memory Limit (KB)');
        $mform->setType('new_memory_limit', PARAM_INT);
        $mform->setDefault('new_memory_limit', 262144);

        // === TYPES & LANGUAGES ===
        $types = $DB->get_records_menu('local_prog_type', null, '', 'id, name');
        $languages = $DB->get_records_menu('local_prog_language', null, '', 'id, name');

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

        $mform->addElement('filepicker', 'zipfile', 'Test cases ZIP (.zip)', null, [
            'accepted_types' => ['.zip'],
            'maxbytes' => 0
        ]);

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
                      <th>Type</th><th>Input File</th><th>Output File</th><th>Points</th><th>Order</th><th>Action</th>
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

// ✅ MOD : ajoute l’URL vers parsezip.php
        $parsezipurl = new moodle_url('/question/type/prog/parsezip.php'); // ✅ PAS qtype_prog

// === JS ===

        $PAGE->requires->css(new moodle_url('/question/type/prog/styles.css'));


        $PAGE->requires->js_init_code("
const parseZipUrl = '$parsezipurl';
let latestInputOptions = '';
let latestOutputOptions = '';

document.addEventListener('DOMContentLoaded', function () {
    const modeSelect = document.querySelector('[name=\"problem_mode\"]');
    const existingSection = document.getElementById('id_existing_problem_section');
    const newSection = document.getElementById('id_new_problem_section');
    const testcaseSection = document.getElementById('id_testcase_section');
    const feedbackBox = document.getElementById('zip-feedback');

    const select = document.querySelector('[name=\"problemlist\"]');
    const nameField = document.querySelector('[name=\"name\"]');
    const codeField = document.querySelector('[name=\"problemcode\"]');
    const problemIdField = document.querySelector('[name=\"problemid\"]');
    const codeDisplay = document.querySelector('[name=\"problemcode_display\"]');
    const descriptionDisplay = document.querySelector('[name=\"description_display\"]');
    const qtTextField = document.querySelector('[name=\"questiontext[text]\"]');
    const editor = document.querySelector('[name=\"new_description[text]\"]');

    const qtWrapper = document.querySelector('[id^=\"fitem_id_questiontext\"]');
    const nameWrapper = document.querySelector('[id^=\"fitem_id_name\"]');
    if (qtWrapper) qtWrapper.style.display = 'none';
    if (nameWrapper) nameWrapper.style.display = 'none';

    function toggleSections() {
        const mode = modeSelect.value;
        existingSection.style.display = (mode === 'existing') ? '' : 'none';
        newSection.style.display = (mode === 'new') ? '' : 'none';
        if (testcaseSection) testcaseSection.style.display = (mode === 'new') ? '' : 'none';
    }

    toggleSections();
    modeSelect.addEventListener('change', toggleSections);

    fetch(M.cfg.wwwroot + '/question/type/prog/fetchproblems.php')
        .then(r => r.json())
        .then(data => {
            data.forEach(p => {
                const opt = document.createElement('option');
                opt.value = p.code;
                opt.textContent = p.name;
                select.appendChild(opt);
            });
        });

    select.addEventListener('change', function () {
        const code = this.value;
        if (!code) return;
        fetch(M.cfg.wwwroot + '/question/type/prog/fetchproblem.php?code=' + encodeURIComponent(code))
            .then(r => r.json())
            .then(data => {
                const desc = data.description || '';
                if (nameField) nameField.value = data.name || '';
                if (codeField) codeField.value = data.code || '';
                if (problemIdField) problemIdField.value = data.id || '';
                if (codeDisplay) codeDisplay.value = data.code || '';
                if (descriptionDisplay) descriptionDisplay.value = desc;
                if (qtTextField) qtTextField.value = desc;
            });
    });

    const form = document.querySelector('form');
    if (form) {
        form.addEventListener('submit', function () {
            const mode = modeSelect.value;
            if (!qtTextField || !nameField) return;

            if (mode === 'existing') {
                qtTextField.value = descriptionDisplay ? descriptionDisplay.value : '';
                nameField.value = select && select.selectedIndex >= 0 ? select.options[select.selectedIndex].textContent : 'Unnamed existing problem';
            } else {
                if (editor) {
                    const tempDiv = document.createElement('div');
                    tempDiv.innerHTML = editor.value;
                    qtTextField.value = tempDiv.textContent || tempDiv.innerText || '';
                }
                const newNameInput = document.querySelector('[name=\"new_name\"]');
                if (newNameInput) {
                    nameField.value = newNameInput.value || 'Unnamed new problem';
                }

                const testCases = [];
                document.querySelectorAll('#testcase-body tr').forEach((row, index) => {
                    const type = row.querySelector('[name$=\"[type]\"]')?.value;
                    const input = row.querySelector('[name$=\"[input_file]\"]')?.value;
                    const output = row.querySelector('[name$=\"[output_file]\"]')?.value;
                    const points = row.querySelector('[name$=\"[points]\"]')?.value;
                    const order = row.querySelector('[name$=\"[order]\"]')?.value;

                    testCases.push({
                        type: type ?? 'C',
                        input_file: input ?? '',
                        output_file: output ?? '',
                        points: parseInt(points ?? '0'),
                        order: parseInt(order ?? index + 1),
                    });
                });

                const hidden = document.querySelector('[name=\"test_cases_json\"]');
                if (hidden) {
                    hidden.value = JSON.stringify(testCases);
                }
            }
        });
    }

    window.addTestCase = function () {
        let caseIndex = document.querySelectorAll('#testcase-body tr').length;
        const tbody = document.getElementById('testcase-body');

        const row = document.createElement('tr');
        row.innerHTML =
            '<td>' +
                '<select name=\"test_cases[' + caseIndex + '][type]\" class=\"form-control\" onchange=\"handleTypeChange(this)\">' +
                    '<option value=\"C\" selected>Normal case</option>' +
                    '<option value=\"S\">Batch start</option>' +
                    '<option value=\"E\">Batch end</option>' +
                '</select>' +
            '</td>' +
            '<td><select name=\"test_cases[' + caseIndex + '][input_file]\" class=\"form-control\">' + latestInputOptions + '</select></td>' +
            '<td><select name=\"test_cases[' + caseIndex + '][output_file]\" class=\"form-control\">' + latestOutputOptions + '</select></td>' +
            '<td><input type=\"number\" name=\"test_cases[' + caseIndex + '][points]\" value=\"0\" class=\"form-control\"></td>' +
            '<td><input type=\"number\" name=\"test_cases[' + caseIndex + '][order]\" value=\"' + (caseIndex + 1) + '\" class=\"form-control\"></td>' +
            '<td><button type=\"button\" onclick=\"removeRow(this)\">❌</button></td>';
        tbody.appendChild(row);
    };

    window.removeRow = function (btn) {
        btn.closest('tr').remove();
    };

    window.handleTypeChange = function (select) {
        const row = select.closest('tr');
        const type = select.value;
        const disable = type !== 'C';

        ['input_file', 'output_file', 'points', 'order'].forEach(function(field) {
            const el = row.querySelector('[name$=\"[' + field + ']\"]');
            if (el) {
                el.disabled = disable;
                if (disable) {
                    el.classList.add('disabled');
                } else {
                    el.classList.remove('disabled');
                }
            }
        });
    };

    // ✅ Nouveau : surveiller le filepicker (après upload)
    const filepickerInterval = setInterval(function () {
        const preview = document.querySelector('.filepicker-filename a');
        if (preview && preview.href.endsWith('.zip')) {
            clearInterval(filepickerInterval);

            const fileUrl = preview.href;
            const fileName = preview.textContent;

            fetch(fileUrl)
                .then(res => res.blob())
                .then(blob => {
                    const formData = new FormData();
                    formData.append('zipfile', blob, fileName);
                    formData.append('sesskey', M.cfg.sesskey);

                    return fetch(parseZipUrl, {
                        method: 'POST',
                        body: formData,
                        credentials: 'same-origin'
                    });
                })
                .then(res => res.json())
                .then(data => {
                    if (data.error) {
                        if (feedbackBox) {
                            feedbackBox.textContent = '❌ ZIP error: ' + data.error;
                            feedbackBox.style.color = 'red';
                        }
                        return;
                    }

                    if (feedbackBox) {
                        feedbackBox.style.color = 'green';
                    }

                    latestInputOptions = data.input_files.map(f => '<option value=\"' + f + '\">' + f + '</option>').join('');
                    latestOutputOptions = data.output_files.map(f => '<option value=\"' + f + '\">' + f + '</option>').join('');

                    document.querySelectorAll('select[name$=\"[input_file]\"]').forEach(select => {
                        select.innerHTML = latestInputOptions;
                    });
                    document.querySelectorAll('select[name$=\"[output_file]\"]').forEach(select => {
                        select.innerHTML = latestOutputOptions;
                    });
                })
                .catch(error => {
                    if (feedbackBox) {
                        feedbackBox.textContent = '❌ AJAX error: ' + error.message;
                        feedbackBox.style.color = 'red';
                    }
                    console.error(error);
                });
        }
    }, 1000); // Vérifie toutes les secondes jusqu’à ce que le fichier zip soit là

    // ✅ Ajouter les astérisques rouges aux champs obligatoires
    const requiredFields = [
        'new_code', 'new_name', 'new_description', 'new_points',
        'new_types[]', 'new_languages[]'
    ];

    requiredFields.forEach(name => {
        const input = document.querySelector('[name=\"' + name + '\"]');
        if (input) {
            const label = input.closest('.fitem')?.querySelector('label');
            if (label && !label.innerHTML.includes('<abbr')) {
                label.innerHTML += ' <abbr class=\"required\" title=\"required\">*</abbr>';
            }
        }
    });
});
");




    }

    public function qtype() {
        return 'prog';
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
