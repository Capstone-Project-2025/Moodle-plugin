<?php
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
        'callback'  => 'local_dmoj_user_link_observer::create_org_and_course_user_with_dmoj',
    ]
];
