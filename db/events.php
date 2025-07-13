<?php
defined('MOODLE_INTERNAL') || die();

$observers = array(
    array(
        'eventname' => '\core\event\enrol_instance_created',
        'callback'  => 'mod_dmojorganize_observer::enrol_instance_created',
        'priority'  => 9999,
        'internal'  => false,
    ),
);

?>