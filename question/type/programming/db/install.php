<?php
defined('MOODLE_INTERNAL') || die();

/**
 * Installation script for question_type_programming plugin
 */

function xmldb_qtype_programmingramming_install() {
    global $DB;

    // Insert default programming languages
    $languages = [
        1 => 'Python 2',
        2 => 'Assembly (x64)',
        3 => 'AWK',
        4 => 'C',
        5 => 'C++03',
        6 => 'C++11',
        7 => 'Perl',
        8 => 'Python 3',
        9 => 'Java 8',
        10 => 'Text',
        11 => 'Assembly (x86)',
        12 => 'Sed',
        13 => 'C++14',
        14 => 'Pascal',
        15 => 'C++17',
        16 => 'C11',
        17 => 'Brain****',
        18 => 'C++20'
    ];

    foreach ($languages as $languageid => $name) {
        $record = new stdClass();
        $record->language_id = $languageid;
        $record->name = $name;
        $DB->insert_record('programming_language', $record);
    }

    // Insert default programming problem types (with type_id and name)
    $types = [
        1 => 'Simple Math',
        2 => 'Array',
        3 => 'Vector',
        4 => 'Advanced Math',
        5 => 'Data Structures',
        6 => 'Recursion',
        7 => 'Geometry',
        8 => 'Simulations',
        9 => 'Graph Theory'
    ];

    foreach ($types as $typeid => $fullname) {
        $record = new stdClass();
        $record->type_id = $typeid;
        $record->name = $fullname;
        $DB->insert_record('programming_type', $record);
    }
}

