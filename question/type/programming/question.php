<?php
defined('MOODLE_INTERNAL') || die();

class qtype_programming_question extends question_graded_automatically {

    public $problemcode;

    public function get_expected_data() {
        return ['answer' => PARAM_RAW];
    }

    public function summarise_response(array $response) {
        if (isset($response['answer'])) {
            return shorten_text($response['answer'], 100);
        }
        return null;
    }

    public function is_complete_response(array $response) {
        return isset($response['answer']) && $response['answer'] !== '';
    }

    public function is_gradable_response(array $response) {
        return $this->is_complete_response($response);
    }

    public function grade_response(array $response) {
        return [0, question_state::$gradedwrong];
    }

    public function get_correct_response() {
        return null;
    }

    public function is_same_response(array $prevresponse, array $newresponse) {
        return question_utils::arrays_same_at_key($prevresponse, $newresponse, 'answer');
    }

    public function get_validation_error(array $response) {
        if (!$this->is_complete_response($response)) {
            return get_string('pleaseenterananswer', 'qtype_programming');
        }
        return null;
    }
}
