<?php
$observers = [
    [
        'eventname' => '\local_dmoj_user_link\event\before_setting_updated',
        'callback'  => 'local_dmoj_user_linkobserver::unlink_dmoj',
    ],
    [
        'eventname' => '\local_dmoj_user_link\event\after_setting_updated',
        'callback'  => 'local_dmoj_user_linkobserver::link_dmoj',
    ],
    [
        'eventname' => 'core\event\user_created',
        'callback'  => 'local_dmoj_user_linkobserver::link_dmoj',
    ],
    [
        'eventname' => 'core\event\user_deleted',
        'callback'  => 'local_dmoj_user_linkobserver::unlink_dmoj',
    ]
];
