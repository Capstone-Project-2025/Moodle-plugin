<?php
require_once($CFG->dirroot . '/course/moodleform_mod.php');

class mod_programmingquizz_mod_form extends moodleform_mod {
    function definition() {
        $mform = $this->_form;

        $mform->addElement('text', 'name', get_string('modulename', 'programmingquizz'), ['size' => '64']);
        $mform->setType('name', PARAM_TEXT);
        $mform->addRule('name', null, 'required', null, 'client');

        $mform->addElement('editor', 'introeditor', get_string('description'), null);
        $mform->setType('introeditor', PARAM_RAW);

        $mform->addElement('textarea', 'code', get_string('code', 'programmingquizz'), 'rows="10" cols="80"');
        $mform->setType('code', PARAM_RAW);

        $this->standard_coursemodule_elements();
        $this->add_action_buttons();
    }
}
