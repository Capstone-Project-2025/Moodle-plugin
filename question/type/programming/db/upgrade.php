<?php
defined('MOODLE_INTERNAL') || die();

function xmldb_qtype_programming_upgrade($oldversion) {
    global $DB;

    $dbman = $DB->get_manager();

    if ($oldversion < 2025070209) {

        $table = new xmldb_table('qtype_programming_submission');

        $field = new xmldb_field('attempt_id', XMLDB_TYPE_INTEGER, '10', XMLDB_UNSIGNED, null, null, 0);
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        $DB->set_field_select('qtype_programming_submission', 'attempt_id', 0, 'attempt_id IS NULL');

        $fielddefault = new xmldb_field('attempt_id', XMLDB_TYPE_INTEGER, '10', XMLDB_UNSIGNED, null, null, 0);
        $dbman->change_field_default($table, $fielddefault);

        $fieldnotnull = new xmldb_field('attempt_id', XMLDB_TYPE_INTEGER, '10', XMLDB_UNSIGNED, XMLDB_NOTNULL, null, 0);
        $dbman->change_field_notnull($table, $fieldnotnull);

        upgrade_plugin_savepoint(true, 2025070209, 'qtype', 'programming');
    }

    return true;
}
