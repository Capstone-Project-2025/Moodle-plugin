<?php
defined('MOODLE_INTERNAL') || die();

function xmldb_qtype_programming_upgrade($oldversion) {
    global $DB;

    $dbman = $DB->get_manager();

    if ($oldversion < 20250702010) {


        // Enregistrer le point de sauvegarde.
        upgrade_plugin_savepoint(true, 20250702010, 'qtype', 'programming');
    }

    return true;
}
