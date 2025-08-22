<?php

defined('MOODLE_INTERNAL') || die();

$observers = [
    [
        'eventname' => '\local_dmoj_user_link\event\before_setting_updated',
        'callback'  => 'local_dmoj_user_link_observer::unlink_dmoj',
    ],
    [
        'eventname' => '\local_dmoj_user_link\event\after_setting_updated',
        'callback'  => 'local_dmoj_user_link_observer::link_moodle_admin_with_dmoj',
    ],
    [
        'eventname' => '\mod_progcontest\event\progcontest_first_created',
        'callback'  => 'local_dmoj_user_link_observer::course_org_syncing',
    ],
    [
        'eventname' => '\core\event\user_enrolment_created',
        'callback'  => 'local_dmoj_user_link_observer::course_org_syncing',
    ],
    [
        'eventname' => '\core\event\user_enrolment_deleted',
        'callback'  => 'local_dmoj_user_link_observer::course_org_syncing',
    ],
    [
        'eventname' => '\core\event\role_assigned',
        'callback'  => 'local_dmoj_user_link_observer::course_org_syncing',
    ],
    [
        'eventname' => '\core\event\role_unassigned',
        'callback'  => 'local_dmoj_user_link_observer::course_org_syncing',
    ],
];
