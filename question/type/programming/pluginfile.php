<?php
defined('MOODLE_INTERNAL') || die();
require_once($CFG->dirroot . '/question/engine/lib.php');
require_once($CFG->dirroot . '/question/type/programming/question.php');

function qtype_programming_pluginfile($course, $cm, $context, $filearea, $args, $forcedownload, array $options = array()) {
    return false;
}
