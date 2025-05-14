<?php
defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/question/type/edit_question_form.php');

class qtype_programming_edit_form extends question_edit_form {

    protected function definition_inner($mform) {
        $mform->addElement('text', 'problemcode', get_string('problemcode', 'qtype_programming'));
        $mform->setType('problemcode', PARAM_TEXT);
        $mform->addRule('problemcode', null, 'required', null, 'client');

        $mform->addHelpButton('problemcode', 'problemcode', 'qtype_programming');
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
