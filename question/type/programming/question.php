<?php
defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/local/prog/classes/api/ProblemSubmission.php');

use local_prog\api\ProblemSubmission;

/**
 * Defines the behavior and grading logic for the programming question type.
 */
class qtype_programming_question extends question_graded_automatically {

    public $problemcode;

    /**
     * Returns the expected structure of user response.
     */
    public function get_expected_data() {
        return [
            'answer' => PARAM_RAW,
            'submission_id' => PARAM_INT
        ];
    }

    /**
     * Generates a short summary of the user's response.
     */
    public function summarise_response(array $response) {
        if (isset($response['answer'])) {
            return shorten_text($response['answer'], 100);
        }
        return null;
    }

    /**
     * Checks whether the response is complete enough to be graded.
     */
    public function is_complete_response(array $response) {
        return isset($response['answer']) && $response['answer'] !== '';
    }

    /**
     * Determines whether the response should be graded.
     */
    public function is_gradable_response(array $response) {
        return $this->is_complete_response($response);
    }

    /**
     * Returns the correct response, if applicable (not used here).
     */
    public function get_correct_response() {
        return null;
    }

    /**
     * Checks whether two responses are the same.
     */
    public function is_same_response(array $prevresponse, array $newresponse) {
        return question_utils::arrays_same_at_key($prevresponse, $newresponse, 'answer');
    }

    /**
     * Provides a validation error if the response is incomplete.
     */
    public function get_validation_error(array $response) {
        if (!$this->is_complete_response($response)) {
            return get_string('pleaseenterananswer', 'qtype_programming');
        }
        return null;
    }

    /**
     * Grades the response using your custom API class.
     */
    public function grade_response(array $response) {
    global $DB;

    $submissionid = $response['submission_id'] ?? null;
    if (!$submissionid) {
        return [0, question_state::$gradedwrong];
    }

   $record = $DB->get_record('qtype_programming_submission', ['submission_id' => $submissionid]);


    if (!$record || !isset($record->point) || !isset($record->total_point)) {
        return [0, question_state::$gradedwrong];
    }

    $points = (float) $record->point;
    $total = (float) $record->total_point;

    if ($total <= 0) {
        return [0, question_state::$gradedwrong];
    }

    $fraction = min(1.0, $points / $total);
    $state = $fraction >= 1.0 ? question_state::$gradedright : question_state::$gradedpartial;

    return [$fraction, $state];
}



}
