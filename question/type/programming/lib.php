<?php

defined('MOODLE_INTERNAL') || die();

// Include the main question type class.
require_once($CFG->dirroot . '/question/type/programming/questiontype.php');

/**
 * Returns an instance of the programming question type.
 *
 * @return qtype_programming
 */
function qtype_programming_questiontype() {
    return new qtype_programming();
}
