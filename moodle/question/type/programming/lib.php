<?php

defined('MOODLE_INTERNAL') || die();

// Inclusion de la classe principale.
require_once($CFG->dirroot . '/question/type/programming/questiontype.php');

/**
 * Retourne une instance du type de question.
 */
function qtype_programming_questiontype() {
    return new qtype_programming();
}
