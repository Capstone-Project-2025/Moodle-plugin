<?php
defined('MOODLE_INTERNAL') || die();

function programmingassign_add_instance($data) {
    global $DB;

    $data = file_postupdate_standard_editor(
        $data,
        'description',
        ['subdirs' => 0, 'maxfiles' => 0, 'context' => context_module::instance($data->coursemodule)],
        context_module::instance($data->coursemodule)
    );

    $data->timecreated = time();
    $data->timemodified = time();

    return $DB->insert_record('programmingassign', $data);
}

function programmingassign_update_instance($data, $mform = null) {
    global $DB;

    $data = file_postupdate_standard_editor(
        $data,
        'description',
        ['subdirs' => 0, 'maxfiles' => 0, 'context' => context_module::instance($data->coursemodule)],
        context_module::instance($data->coursemodule)
    );

    $data->timemodified = time();
    $data->id = $data->instance;

    return $DB->update_record('programmingassign', $data);
}


function programmingassign_delete_instance($id) {
    global $DB;

    if (!$instance = $DB->get_record('programmingassign', ['id' => $id])) {
        return false;
    }

    // Supprimer les fichiers associés à l'éditeur
    $context = context_module::instance($instance->coursemodule);
    $fs = get_file_storage();
    $fs->delete_area_files($context->id, 'mod_programmingassign', 'description');

    return $DB->delete_records('programmingassign', ['id' => $id]);
}


function programmingassign_supports($feature) {
    switch ($feature) {
        case FEATURE_BACKUP_MOODLE2:
            return true;
        default:
            return null;
    }
}

function programmingassign_extend_navigation_course($navigation, $course, $context) {
    if (is_siteadmin() && has_capability('mod/programmingassign:view', $context)) {
        $url = new moodle_url('/mod/programmingassign/list.php', ['id' => $course->id]);
        $navigation->add(
            get_string('pluginname', 'mod_programmingassign'),
            $url,
            navigation_node::TYPE_CUSTOM,
            null,
            null,
            new pix_icon('i/report', '')
        );
    }
}
