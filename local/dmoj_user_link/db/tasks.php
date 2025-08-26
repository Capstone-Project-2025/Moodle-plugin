<?php
defined('MOODLE_INTERNAL') || die();

$tasks = [
    [
        'classname' => '\local_dmoj_user_link\task\unlink_task',
        'blocking'  => 0,
        'minute'    => '0',
        'hour'      => '4',
        'day'       => '*',
        'dayofweek' => '*',
        'month'     => '*'
    ]
];
