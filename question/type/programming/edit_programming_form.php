<?php
defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/question/type/edit_question_form.php');

class qtype_programming_edit_form extends question_edit_form {

    protected function definition() {
        $mform = $this->_form;

        // 1. Add dropdown list
        $mform->addElement('select', 'problemlist', get_string('problemname', 'qtype_programming'), []);
        $mform->addRule('problemlist', null, 'required', null, 'client');

        // 2. Just below: Problem code
        $mform->addElement('text', 'problemcode', get_string('problemcode', 'qtype_programming'));
        $mform->setType('problemcode', PARAM_TEXT);
        $mform->addRule('problemcode', null, 'required', null, 'client');

        $mform->addElement('hidden', 'problemid');
        $mform->setType('problemid', PARAM_INT);

        // 3. Then the classic Moodle fields
        parent::definition();

        // Hide the "name" field
        global $PAGE;
        $PAGE->requires->js_init_code("
            document.addEventListener('DOMContentLoaded', function() {
                const select = document.querySelector('[name=\"problemlist\"]');
                const nameField = document.querySelector('[name=\"name\"]');
                const codeField = document.querySelector('[name=\"problemcode\"]');
                const nameFieldWrapper = document.querySelector('[id^=\"fitem_id_name\"]');

                if (nameFieldWrapper) nameFieldWrapper.style.display = 'none';

                fetch(M.cfg.wwwroot + '/question/type/programming/fetchproblems.php')
                    .then(response => response.json())
                    .then(data => {
                        data.forEach(p => {
                            const option = document.createElement('option');
                            option.value = p.code;
                            option.textContent = p.name;
                            select.appendChild(option);
                        });
                    });

                select.addEventListener('change', function() {
                    const code = this.value;
                    codeField.value = code;

                    fetch(M.cfg.wwwroot + '/question/type/programming/fetchproblem.php?code=' + encodeURIComponent(code))
                        .then(response => response.json())
                        .then(data => {
                            if (nameField) nameField.value = data.name || '';
                            if (codeField) codeField.value = data.code || '';

                            const problemIdField = document.querySelector('[name=\\\"problemid\\\"]');
                            if (problemIdField) problemIdField.value = data.id || '';

                            // Fill the question text editor (TinyMCE or Atto)
                            const questionTextEditorId = 'id_questiontext';

                            // TinyMCE (classic)
                            if (typeof tinyMCE !== 'undefined' && tinyMCE.get(questionTextEditorId)) {
                                tinyMCE.get(questionTextEditorId).setContent(data.description || '');
                            }

                            // Atto
                            if (typeof Y !== 'undefined' && Y.one('#' + questionTextEditorId + '_editable')) {
                                Y.one('#' + questionTextEditorId + '_editable').setHTML(data.description || '');
                            }

                            // If no editor is active (fallback)
                            const rawTextarea = document.getElementById(questionTextEditorId);
                            if (rawTextarea) rawTextarea.value = data.description || '';
                        });
                });
            });
        ");
    }

    public function qtype() {
        return 'programming';
    }

    public function set_data($question) {
        if (isset($question->options)) {
            $question->problemcode = $question->options->problemcode;
            $question->problemid = $question->options->problem_id;
        }
        parent::set_data($question);
    }

}
