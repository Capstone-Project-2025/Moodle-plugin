<?php
defined('MOODLE_INTERNAL') || die();

/**
 * Upgrade script for local_dmoj_user_link plugin
 */
function xmldb_local_dmoj_user_link_upgrade($oldversion) {
    global $DB;

    // Update step to create all missing tables from install.xml (version bump to 2025061904)
    if ($oldversion < 2025061909) {
/*
        // Table: local_dmoj_user_linklanguage
        $table = new xmldb_table('local_dmoj_user_linklanguage');
        if (!$DB->get_manager()->table_exists($table)) {
            $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE);
            $table->add_field('language_id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL);
            $table->add_field('name', XMLDB_TYPE_TEXT, null, null, XMLDB_NOTNULL);

            $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
            $table->add_key('uniq_languageid', XMLDB_KEY_UNIQUE, ['language_id']);

            $DB->get_manager()->create_table($table);
        }

        // Table: local_dmoj_user_linkproblem
        $table = new xmldb_table('local_dmoj_user_linkproblem');
        if (!$DB->get_manager()->table_exists($table)) {
            $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE);
            $table->add_field('code', XMLDB_TYPE_CHAR, '100', null, XMLDB_NOTNULL);
            $table->add_field('name', XMLDB_TYPE_TEXT, null, null, XMLDB_NOTNULL);
            $table->add_field('description', XMLDB_TYPE_TEXT, null, null, null);
            $table->add_field('userid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL);
            $table->add_field('ispublic', XMLDB_TYPE_INTEGER, '1', null, XMLDB_NOTNULL, null, '0');
            $table->add_field('points', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, 0);
            $table->add_field('difficulty', XMLDB_TYPE_CHAR, '10', null, null, null, null);

            $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
            $table->add_key('uniq_code', XMLDB_KEY_UNIQUE, ['code']);
            $table->add_key('fk_user', XMLDB_KEY_FOREIGN, ['userid'], 'user', ['id']);

            $DB->get_manager()->create_table($table);
        }

        // Table: local_dmoj_user_linkproblem_language
        $table = new xmldb_table('local_dmoj_user_linkproblem_language');
        if (!$DB->get_manager()->table_exists($table)) {
            $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE);
            $table->add_field('problem_id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL);
            $table->add_field('language_id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL);

            $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
            $table->add_key('uniq_pair', XMLDB_KEY_UNIQUE, ['problem_id', 'language_id']);
            $table->add_key('fk_problem', XMLDB_KEY_FOREIGN, ['problem_id'], 'local_dmoj_user_linkproblem', ['id']);
            $table->add_key('fk_language', XMLDB_KEY_FOREIGN, ['language_id'], 'local_dmoj_user_linklanguage', ['id']);

            $DB->get_manager()->create_table($table);
        }

        // Table: local_dmoj_user_linktype
        $table = new xmldb_table('local_dmoj_user_linktype');
        if (!$DB->get_manager()->table_exists($table)) {
            $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE);
            $table->add_field('type_id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL);
            $table->add_field('name', XMLDB_TYPE_TEXT, null, null, XMLDB_NOTNULL);

            $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
            $table->add_key('uniq_typeid', XMLDB_KEY_UNIQUE, ['type_id']);

            $DB->get_manager()->create_table($table);
        }

        // Table: local_dmoj_user_linktype
        $table = new xmldb_table('local_dmoj_user_linktype');
        if (!$DB->get_manager()->table_exists($table)) {
            $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE);
            $table->add_field('problem_id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL);
            $table->add_field('type_id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL);

            $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
            $table->add_key('uniq_pair', XMLDB_KEY_UNIQUE, ['problem_id', 'type_id']);
            $table->add_key('fk_problem', XMLDB_KEY_FOREIGN, ['problem_id'], 'local_dmoj_user_linkproblem', ['id']);
            $table->add_key('fk_type', XMLDB_KEY_FOREIGN, ['type_id'], 'local_dmoj_user_linktype', ['id']);

            $DB->get_manager()->create_table($table);
        }

        $table = new xmldb_table('local_dmoj_user_linktestcase');

    if (!$DB->get_manager()->table_exists($table)) {
        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE);
        $table->add_field('problem_id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL);
        $table->add_field('points', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, 0);

        $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
        $table->add_key('fk_problem', XMLDB_KEY_FOREIGN, ['problem_id'], 'local_dmoj_user_linkproblem', ['id']);

        $DB->get_manager()->create_table($table);
    }

        // Insert default dmoj_user_link languages
    // Insert default dmoj_user_link languages
    /*
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
        $DB->insert_record('local_dmoj_user_linklanguage', $record);
    }

    // Insert default dmoj_user_link problem types
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
        $DB->insert_record('local_dmoj_user_linktype', $record);
    }
*/
        // ✅ Mark the upgrade as successful
        upgrade_plugin_savepoint(true, 2025061909, 'local', 'dmoj_user_link');
    }

    return true;
}
