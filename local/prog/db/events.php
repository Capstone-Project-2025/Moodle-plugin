<?php
$observers = [
    [
        'eventname' => '\local_programming\event\before_setting_updated',
        'callback'  => 'local_programming_observer::unlink_dmoj',
    ],
    [
        'eventname' => '\local_programming\event\after_setting_updated',
        'callback'  => 'local_programming_observer::link_dmoj',
    ],
    [
        'eventname' => 'core\event\user_created',
        'callback'  => 'local_programming_observer::link_dmoj',
    ],
    [
        'eventname' => 'core\event\user_deleted',
        'callback'  => 'local_programming_observer::unlink_dmoj',
    ]
];
