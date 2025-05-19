<?php
defined('MOODLE_INTERNAL') || die();

/**
 * Classe principale du type de question programming.
 */

 require_once($CFG->dirroot . '/question/type/questiontypebase.php');

class qtype_programming extends question_type {

    /**
     * Enregistrer les options spécifiques à une question de type programming.
     */
    public function save_question_options($question) {
        global $DB;
    
        if ($existing = $DB->get_record('qtype_programming_options', ['questionid' => $question->id])) {
            $options = $existing;
        } else {
            $options = new stdClass();
            $options->questionid = $question->id;
        }
    
        $options->problemcode = $question->problemcode;

        // Sécurité si problemname est utilisé
        if (property_exists($question, 'problemname')) {
            $options->problemname = $question->problemname;
        }

        if (isset($options->id)) {
            $DB->update_record('qtype_programming_options', $options);
        } else {
            $DB->insert_record('qtype_programming_options', $options);
        }
    
        return true;
    }

    
    /**
     * Charger les options spécifiques d'une question programming.
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
     * Supprimer les options spécifiques si la question est supprimée.
     */
    public function delete_question($questionid, $contextid) {
        global $DB;
        $DB->delete_records('qtype_programming_options', ['questionid' => $questionid]);
        parent::delete_question($questionid, $contextid);
    }

    public function make_question($questiondata) {
        global $DB;
    
        // Chargement classique de la classe de question.
        question_bank::load_question_definition_classes($this->name());
        $question = new qtype_programming_question();
        $this->initialise_question_instance($question, $questiondata);
    
        // Injection propre des options supplémentaires.
        $options = $DB->get_record('qtype_programming_options', ['questionid' => $question->id]);
        $question->problemcode = $options->problemcode ?? 'NO_CODE';
    
        return $question;
    }
    
    
}
