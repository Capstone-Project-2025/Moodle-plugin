<?php

defined('MOODLE_INTERNAL') || die();

// Include the main question type class.
require_once($CFG->dirroot . '/question/type/prog/questiontype.php');

/**
 * Returns an instance of the prog question type.
 *
 * @return qtype_prog
 */
function qtype_prog_questiontype() {
    return new qtype_prog();
}
