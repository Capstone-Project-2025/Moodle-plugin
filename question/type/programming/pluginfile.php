<?php
defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/question/engine/lib.php');
require_once($CFG->dirroot . '/question/type/programming/question.php');

/**
 * Handles serving of files used in programming questions.
 *
 * Currently, this function blocks all file access. If in the future you allow
 * files (e.g., test input files, assets, etc.), you will need to handle them here.
 *
 * @param stdClass $course The course object.
 * @param stdClass $cm The course module.
 * @param context $context The context.
 * @param string $filearea The name of the file area.
 * @param array $args Remaining file path arguments.
 * @param bool $forcedownload Whether the file should be downloaded.
 * @param array $options Additional options affecting file serving.
 * @return bool Always returns false (no file access allowed).
 */
function qtype_programming_pluginfile($course, $cm, $context, $filearea, $args, $forcedownload, array $options = array()) {
    return false;
}
