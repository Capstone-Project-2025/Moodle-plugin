<?php
defined('MOODLE_INTERNAL') || die();

$observers = [
    [
        'eventname' => '\core\event\user_created',
        'callback'  => 'mod_dmojorganize_observer::user_created'
    ],
    [
        'eventname' => '\core\event\course_created',
        'callback'  => 'mod_dmojorganize_observer::course_created'
    ],
    [
        'eventname' => '\core\event\course_updated',
        'callback'  => 'mod_dmojorganize_observer::course_updated'
    ],
    [
        'eventname' => '\core\event\course_deleted',
        'callback'  => 'mod_dmojorganize_observer::course_deleted'
    ],
];
?>