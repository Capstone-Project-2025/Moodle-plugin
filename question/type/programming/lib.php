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

/*
function qtype_programming_before_http_headers() {
    global $PAGE;

    $PAGE->requires->css('/question/type/programming/thirdparty/codemirror/lib/codemirror.css');
    $PAGE->requires->css('/question/type/programming/thirdparty/codemirror/theme/material-darker.css');
    $PAGE->requires->css('/question/type/programming/thirdparty/codemirror/theme/eclipse.css');

    // CodeMirror is AMD-incompatible, so we load it manually
    $PAGE->requires->js(new moodle_url('/question/type/programming/thirdparty/codemirror/lib/codemirror.js'), true);
    $PAGE->requires->js(new moodle_url('/question/type/programming/thirdparty/codemirror/mode/clike/clike.js'), true);

    // ✅ Modes selon les langages supportés
    $PAGE->requires->js(new moodle_url('/question/type/programming/thirdparty/codemirror/mode/python/python.js'), true);
    $PAGE->requires->js(new moodle_url('/question/type/programming/thirdparty/codemirror/mode/pascal/pascal.js'), true);
    $PAGE->requires->js(new moodle_url('/question/type/programming/thirdparty/codemirror/mode/perl/perl.js'), true);
    $PAGE->requires->js(new moodle_url('/question/type/programming/thirdparty/codemirror/mode/gas/gas.js'), true);
    $PAGE->requires->js(new moodle_url('/question/type/programming/thirdparty/codemirror/mode/shell/shell.js'), true);
    $PAGE->requires->js(new moodle_url('/question/type/programming/thirdparty/codemirror/mode/brainfuck/brainfuck.js'), true);
    $PAGE->requires->js(new moodle_url('/question/type/programming/thirdparty/codemirror/mode/awk/awk.js'), true);
}
*/

