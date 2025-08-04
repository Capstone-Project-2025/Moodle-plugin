<?php
$observers = [
    [
        'eventname' => '\local_prog\event\before_setting_updated',
        'callback'  => 'local_prog_observer::unlink_dmoj',
    ],
    [
        'eventname' => '\local_prog\event\after_setting_updated',
        'callback'  => 'local_prog_observer::link_dmoj',
    ],
    [
        'eventname' => 'core\event\user_created',
        'callback'  => 'local_prog_observer::link_dmoj',
    ],
    [
        'eventname' => 'core\event\user_deleted',
        'callback'  => 'local_prog_observer::unlink_dmoj',
    ]
];
