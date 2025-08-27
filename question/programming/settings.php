<?php
defined('MOODLE_INTERNAL') || die();

if ($hassiteconfig) {
    $settings = new admin_settingpage(
        'qtype_programming_settings',
        get_string('pluginname', 'qtype_programming')
    );

    // Add a "Danger zone" link to delete all programming questions
    $deleteurl = new moodle_url('/question/type/programming/cleandelete.php');
    $settings->add(new admin_setting_heading(
        'qtype_programming_dangerzone',
        'Danger Zone',
        html_writer::link($deleteurl, 'Delete all programming questions', [
            'class' => 'btn btn-danger',
            'style' => 'color:white; padding: 6px 12px; text-decoration:none;'
        ])
    ));

}
