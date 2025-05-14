<?php
defined('MOODLE_INTERNAL') || die();

function programmingquizz_add_instance($data, $mform = null) {
    global $DB;
    $data->timecreated = time();
    $data->timemodified = time();
    return $DB->insert_record('programmingquizz', $data);
}

function programmingquizz_update_instance($data, $mform = null) {
    global $DB;
    $data->id = $data->instance;
    $data->timemodified = time();
    return $DB->update_record('programmingquizz', $data);
}

function programmingquizz_delete_instance($id) {
    global $DB;
    return $DB->delete_records('programmingquizz', ['id' => $id]);
}
