<?php
defined('MOODLE_INTERNAL') || die();

/**
 * Main class for the Programming question type.
 */

require_once($CFG->dirroot . '/question/type/questiontypebase.php');

class qtype_programming extends question_type {

    /**
     * Saves custom options for the programming question.
     *
     * @param stdClass $question The question object from the form.
     * @return bool
     */
    public function save_question_options($question) {
        global $DB;

        // Retrieve existing options if any.
        if ($existing = $DB->get_record('qtype_programming_options', ['questionid' => $question->id])) {
            $options = $existing;
        } else {
            $options = new stdClass();
            $options->questionid = $question->id;
            $options->problem_id = $question->problemid;
        }

        $options->problemcode = $question->problemcode;

        // Optionally save problemname if present.
        if (property_exists($question, 'problemname')) {
            $options->problemname = $question->problemname;
        }

        // Insert or update record.
        if (isset($options->id)) {
            $DB->update_record('qtype_programming_options', $options);
        } else {
            $DB->insert_record('qtype_programming_options', $options);
        }

        return true;
    }

    /**
     * Loads custom options when a question is loaded from the database.
     *
     * @param object $question The question object.
     * @return bool
     */
    public function get_question_options($question) {
        global $DB;

        $options = $DB->get_record('qtype_programming_options', ['questionid' => $question->id]);

        if ($question instanceof qtype_programming_question) {
            $question->problemcode = $options->problemcode ?? '';
        }

        if (debugging()) {
            echo html_writer::div('<strong>[DEBUG]</strong> get_question_options() called for question ID: ' . $question->id, 'debug');
        }

        return true;
    }

    /**
     * Deletes custom options when the question is deleted.
     *
     * @param int $questionid The question ID.
     * @param int $contextid The context ID.
     * @return void
     */
    public function delete_question($questionid, $contextid) {
        global $DB;
        $DB->delete_records('qtype_programming_options', ['questionid' => $questionid]);
        parent::delete_question($questionid, $contextid);
    }

    /**
     * Constructs the question object for runtime.
     *
     * @param object $questiondata
     * @return qtype_programming_question
     */
    public function make_question($questiondata) {
        global $DB;

        question_bank::load_question_definition_classes($this->name());

        $question = new qtype_programming_question();
        $this->initialise_question_instance($question, $questiondata);

        $options = $DB->get_record('qtype_programming_options', ['questionid' => $question->id]);
        $question->problemcode = $options->problemcode ?? 'NO_CODE';

        return $question;
    }
}
