<?php
$observers = [
    [
        'eventname' => '\local_myplugin\event\before_setting_updated',
        'callback'  => 'local_myplugin_observer::unlink_dmoj',
    ],
    [
        'eventname' => '\local_myplugin\event\after_setting_updated',
        'callback'  => 'local_myplugin_observer::link_dmoj',
    ],
    [
        'eventname' => 'core\event\user_created',
        'callback'  => 'local_myplugin_observer::link_dmoj',
    ],
    [
        'eventname' => 'core\event\user_deleted',
        'callback'  => 'local_myplugin_observer::unlink_dmoj',
    ]
];
