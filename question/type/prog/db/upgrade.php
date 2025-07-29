<?php
defined('MOODLE_INTERNAL') || die();

/**
 * Upgrade script for the prog question type plugin.
 *
 * @param int $oldversion The version we are upgrading from.
 * @return bool
 */
function xmldb_qtype_prog_upgrade($oldversion) {
    global $DB;

    $dbman = $DB->get_manager();

    if ($oldversion < 2025070205) {

        // === Create qtype_prog_options table ===
        /*
        $table = new xmldb_table('qtype_prog_options');

        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $table->add_field('questionid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL);
        $table->add_field('problem_id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL);

        $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
        $table->add_key('questionidfk', XMLDB_KEY_FOREIGN, ['questionid'], 'question', ['id']);
        $table->add_key('problemidfk', XMLDB_KEY_FOREIGN, ['problem_id'], 'local_prog_problem', ['id']);

        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }
        */
        // === Create qtype_prog_submission table ===
        $table = new xmldb_table('qtype_prog_submission');

        $table->add_field('submission_id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL);
        $table->add_field('question_id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL);
        $table->add_field('user_id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL);
        $table->add_field('result', XMLDB_TYPE_CHAR, '30', null, XMLDB_NOTNULL);
        $table->add_field('point', XMLDB_TYPE_NUMBER, '10,2', null, XMLDB_NOTNULL);
        $table->add_field('total_point', XMLDB_TYPE_NUMBER, '10,2', null, XMLDB_NOTNULL);

        $table->add_field('language_id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL);
        $table->add_field('course_id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL);
        $table->add_field('code', XMLDB_TYPE_TEXT, null, null, XMLDB_NOTNULL);
  
        $table->add_key('primary', XMLDB_KEY_PRIMARY, ['submission_id']);
        $table->add_key('questionfk', XMLDB_KEY_FOREIGN, ['question_id'], 'qtype_prog_options', ['id']);
        $table->add_key('languagefk', XMLDB_KEY_FOREIGN, ['language_id'], 'local_prog_language', ['id']);
        $table->add_key('userfk', XMLDB_KEY_FOREIGN, ['user_id'], 'user', ['id']);
        $table->add_key('coursefk', XMLDB_KEY_FOREIGN, ['course_id'], 'course', ['id']);


        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }

        // === Savepoint unique ===
        upgrade_plugin_savepoint(true, 2025070205, 'qtype', 'prog');
    }

    return true;
}
