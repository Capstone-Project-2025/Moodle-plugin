<?php
defined('MOODLE_INTERNAL') || die();

$observers = [
    [
        'eventname' => '\core\event\user_enrolment_created',
        'callback'  => 'mod_dmojorganize_observer::user_enrolment_created',
        'includefile' => '/mod/dmojorganize/classes/observer.php',
        'priority'  => 9999,
        'internal'  => false,
    ],
];
?>