<?php
defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/question/type/edit_question_form.php');

class qtype_programming_edit_form extends question_edit_form {

    protected function definition() {
        $mform = $this->_form;

        // 1. Ajout de la liste déroulante
        $mform->addElement('select', 'problemlist', get_string('problemname', 'qtype_programming'), []);
        $mform->addRule('problemlist', null, 'required', null, 'client');

        // 2. Juste en dessous : Problem code
        $mform->addElement('text', 'problemcode', get_string('problemcode', 'qtype_programming'));
        $mform->setType('problemcode', PARAM_TEXT);
        $mform->addRule('problemcode', null, 'required', null, 'client');

// 3. Ensuite seulement : champs Moodle classiques
parent::definition();

        // Masquer le champ "name"
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

                            if (window.tinyMCE) {
                                const editor = tinyMCE.get('id_questiontext');
                                if (editor) editor.setContent(data.description || '');
                            } else {
                                const editorFrame = document.querySelector('[id^=\"id_questiontexteditable\"]');
                                if (editorFrame) editorFrame.innerHTML = data.description || '';
                            }
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
        }
        parent::set_data($question);
    }
}
