<?php
defined('MOODLE_INTERNAL') || die();

function xmldb_qtype_programming_upgrade($oldversion) {
    global $DB;

    $dbman = $DB->get_manager();

    if ($oldversion < 20250702010) {

        $table = new xmldb_table('programming_problem');

        // Ajouter le champ time_limit.
        $timefield = new xmldb_field('time_limit', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, 1);
        if (!$dbman->field_exists($table, $timefield)) {
            $dbman->add_field($table, $timefield);
        }

        // Ajouter le champ memory_limit.
        $memfield = new xmldb_field('memory_limit', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, 64);
        if (!$dbman->field_exists($table, $memfield)) {
            $dbman->add_field($table, $memfield);
        }

        // Enregistrer le point de sauvegarde.
        upgrade_plugin_savepoint(true, 20250702010, 'qtype', 'programming');
    }

    return true;
}
