<?php
defined('MOODLE_INTERNAL') || die();
require_once($CFG->dirroot.'/course/moodleform_mod.php');

class mod_programmingassign_mod_form extends moodleform_mod {
    function definition() {
        $mform = $this->_form;

        $mform->addElement('text', 'name', get_string('name'), ['size' => '64']);
        $mform->setType('name', PARAM_TEXT);
        $mform->addRule('name', null, 'required', null, 'client');

        $mform->addElement('editor', 'description_editor', get_string('description', 'mod_programmingassign'), null, null);
        $mform->setType('description_editor', PARAM_RAW);
        $mform->addRule('description_editor', null, 'required', null, 'client');

        $mform->addElement('text', 'language', get_string('language', 'mod_programmingassign'));
        $mform->setType('language', PARAM_TEXT);
        $mform->addRule('language', null, 'required', null, 'client');

        $mform->addElement('textarea', 'testcases', get_string('testcases', 'mod_programmingassign'), 'wrap="virtual" rows="10" cols="60"');
        $mform->setType('testcases', PARAM_RAW);
        $mform->addRule('testcases', null, 'required', null, 'client');

        $mform->addElement('date_time_selector', 'allowsubmissionsfromdate', get_string('allowsubmissionsfromdate', 'assign'), array('optional' => true));
        $mform->addElement('date_time_selector', 'duedate', get_string('duedate', 'assign'), array('optional' => true));
        $mform->addElement('date_time_selector', 'cutoffdate', get_string('cutoffdate', 'assign'), array('optional' => true));
        $mform->addElement('date_time_selector', 'gradingduedate', get_string('gradingduedate', 'assign'), array('optional' => true));

        $this->standard_coursemodule_elements();
        $this->add_action_buttons();
    }
    
    function set_data($default_values) {
        if (isset($default_values->description)) {
            $context = $this->context;
    
            $draftid = file_get_submitted_draft_itemid('description');
            file_prepare_standard_editor(
                $default_values,
                'description',
                ['subdirs' => 0, 'maxfiles' => 0, 'context' => $context],
                $context,
                'mod_programmingassign',
                'description',
                0,
                ['noclean' => true]
            );
    
            $default_values->description_editor['text'] = $default_values->description;
            $default_values->description_editor['format'] = $default_values->descriptionformat;
            $default_values->description_editor['itemid'] = $draftid;
        }
    
        parent::set_data($default_values);
    }
}