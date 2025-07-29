<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

namespace progcontestaccess_seb;

defined('MOODLE_INTERNAL') || die();

require_once(__DIR__ . '/test_helper_trait.php');

/**
 * PHPUnit tests for backup and restore functionality.
 *
 * @package   progcontestaccess_seb
 * @author    Dmitrii Metelkin <dmitriim@catalyst-au.net>
 * @copyright 2020 Catalyst IT
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class backup_restore_test extends \advanced_testcase {
    use \progcontestaccess_seb_test_helper_trait;


    /** @var template $template A test template. */
    protected $template;

    /**
     * Called before every test.
     */
    public function setUp(): void {
        global $USER;

        parent::setUp();

        $this->resetAfterTest();
        $this->setAdminUser();

        $this->course = $this->getDataGenerator()->create_course();
        $this->template = $this->create_template();
        $this->user = $USER;
    }

    /**
     * A helper method to create a progcontest with template usage of SEB.
     *
     * @return progcontest_settings
     */
    protected function create_progcontest_with_template() {
        $this->progcontest = $this->create_test_progcontest($this->course, settings_provider::USE_SEB_CONFIG_MANUALLY);
        $progcontestsettings = progcontest_settings::get_record(['progcontestid' => $this->progcontest->id]);
        $progcontestsettings->set('requiresafeexambrowser', settings_provider::USE_SEB_TEMPLATE);
        $progcontestsettings->set('templateid', $this->template->get('id'));
        $progcontestsettings->save();

        return $progcontestsettings;
    }

    /**
     * A helper method to emulate backup and restore of the progcontest.
     *
     * @return \cm_info|null
     */
    protected function backup_and_restore_progcontest() {
        return duplicate_module($this->course, get_fast_modinfo($this->course)->get_cm($this->progcontest->cmid));
    }

    /**
     * A helper method to backup test progcontest.
     *
     * @return mixed A backup ID ready to be restored.
     */
    protected function backup_progcontest() {
        global $CFG;

        // Get the necessary files to perform backup and restore.
        require_once($CFG->dirroot . '/backup/util/includes/backup_includes.php');
        require_once($CFG->dirroot . '/backup/util/includes/restore_includes.php');

        $backupid = 'test-seb-backup-restore';

        $bc = new \backup_controller(\backup::TYPE_1ACTIVITY, $this->progcontest->coursemodule, \backup::FORMAT_MOODLE,
            \backup::INTERACTIVE_NO, \backup::MODE_GENERAL, $this->user->id);
        $bc->execute_plan();

        $results = $bc->get_results();
        $file = $results['backup_destination'];
        $fp = get_file_packer('application/vnd.moodle.backup');
        $filepath = $CFG->dataroot . '/temp/backup/' . $backupid;
        $file->extract_to_pathname($fp, $filepath);
        $bc->destroy();

        return $backupid;
    }

    /**
     * A helper method to restore provided backup.
     *
     * @param string $backupid Backup ID to restore.
     */
    protected function restore_progcontest($backupid) {
        $rc = new \restore_controller($backupid, $this->course->id,
            \backup::INTERACTIVE_NO, \backup::MODE_GENERAL, $this->user->id, \backup::TARGET_CURRENT_ADDING);
        $this->assertTrue($rc->execute_precheck());
        $rc->execute_plan();
        $rc->destroy();
    }

    /**
     * A helper method to emulate restoring to a different site.
     */
    protected function change_site() {
        set_config('siteidentifier', random_string(32) . 'not the same site');
    }

    /**
     * A helper method to validate backup and restore results.
     *
     * @param cm_info $newcm Restored course_module object.
     */
    protected function validate_backup_restore(\cm_info $newcm) {
        $this->assertEquals(2, progcontest_settings::count_records());
        $actual = progcontest_settings::get_record(['progcontestid' => $newcm->instance]);

        $expected = progcontest_settings::get_record(['progcontestid' => $this->progcontest->id]);
        $this->assertEquals($expected->get('templateid'), $actual->get('templateid'));
        $this->assertEquals($expected->get('requiresafeexambrowser'), $actual->get('requiresafeexambrowser'));
        $this->assertEquals($expected->get('showsebdownloadlink'), $actual->get('showsebdownloadlink'));
        $this->assertEquals($expected->get('allowuserquitseb'), $actual->get('allowuserquitseb'));
        $this->assertEquals($expected->get('quitpassword'), $actual->get('quitpassword'));
        $this->assertEquals($expected->get('allowedbrowserexamkeys'), $actual->get('allowedbrowserexamkeys'));

        // Validate specific SEB config settings.
        foreach (settings_provider::get_seb_config_elements() as $name => $notused) {
            $name = preg_replace("/^seb_/", "", $name);
            $this->assertEquals($expected->get($name), $actual->get($name));
        }
    }

    /**
     * Test backup and restore when no seb.
     */
    public function test_backup_restore_no_seb() {
        $this->progcontest = $this->create_test_progcontest($this->course, settings_provider::USE_SEB_NO);
        $this->assertEquals(0, progcontest_settings::count_records());

        $this->backup_and_restore_progcontest();
        $this->assertEquals(0, progcontest_settings::count_records());
    }

    /**
     * Test backup and restore when manually configured.
     */
    public function test_backup_restore_manual_config() {
        $this->progcontest = $this->create_test_progcontest($this->course, settings_provider::USE_SEB_CONFIG_MANUALLY);

        $expected = progcontest_settings::get_record(['progcontestid' => $this->progcontest->id]);
        $expected->set('showsebdownloadlink', 0);
        $expected->set('quitpassword', '123');
        $expected->save();

        $this->assertEquals(1, progcontest_settings::count_records());

        $newcm = $this->backup_and_restore_progcontest();
        $this->validate_backup_restore($newcm);
    }

    /**
     * Test backup and restore when using template.
     */
    public function test_backup_restore_template_config() {
        $this->progcontest = $this->create_test_progcontest($this->course, settings_provider::USE_SEB_CONFIG_MANUALLY);

        $expected = progcontest_settings::get_record(['progcontestid' => $this->progcontest->id]);
        $template = $this->create_template();
        $expected->set('requiresafeexambrowser', settings_provider::USE_SEB_TEMPLATE);
        $expected->set('templateid', $template->get('id'));
        $expected->save();

        $this->assertEquals(1, progcontest_settings::count_records());

        $newcm = $this->backup_and_restore_progcontest();
        $this->validate_backup_restore($newcm);
    }

    /**
     * Test backup and restore when using uploaded file.
     */
    public function test_backup_restore_uploaded_config() {
        $this->progcontest = $this->create_test_progcontest($this->course, settings_provider::USE_SEB_CONFIG_MANUALLY);

        $expected = progcontest_settings::get_record(['progcontestid' => $this->progcontest->id]);
        $expected->set('requiresafeexambrowser', settings_provider::USE_SEB_UPLOAD_CONFIG);
        $xml = file_get_contents(__DIR__ . '/fixtures/unencrypted.seb');
        $this->create_module_test_file($xml, $this->progcontest->cmid);
        $expected->save();

        $this->assertEquals(1, progcontest_settings::count_records());

        $newcm = $this->backup_and_restore_progcontest();
        $this->validate_backup_restore($newcm);

        $expectedfile = settings_provider::get_module_context_sebconfig_file($this->progcontest->cmid);
        $actualfile = settings_provider::get_module_context_sebconfig_file($newcm->id);

        $this->assertEquals($expectedfile->get_content(), $actualfile->get_content());
    }

    /**
     * No new template should be restored if restoring to a different site,
     * but the template with  the same name and content exists..
     */
    public function test_restore_template_to_a_different_site_when_the_same_template_exists() {
        $this->create_progcontest_with_template();
        $backupid = $this->backup_progcontest();

        $this->assertEquals(1, progcontest_settings::count_records());
        $this->assertEquals(1, template::count_records());

        $this->change_site();
        $this->restore_progcontest($backupid);

        // Should see additional setting record, but no new template record.
        $this->assertEquals(2, progcontest_settings::count_records());
        $this->assertEquals(1, template::count_records());
    }

    /**
     * A new template should be restored if restoring to a different site, but existing template
     * has the same content, but different name.
     */
    public function test_restore_template_to_a_different_site_when_the_same_content_but_different_name() {
        $this->create_progcontest_with_template();
        $backupid = $this->backup_progcontest();

        $this->assertEquals(1, progcontest_settings::count_records());
        $this->assertEquals(1, template::count_records());

        $this->template->set('name', 'New name for template');
        $this->template->save();

        $this->change_site();
        $this->restore_progcontest($backupid);

        // Should see additional setting record, and new template record.
        $this->assertEquals(2, progcontest_settings::count_records());
        $this->assertEquals(2, template::count_records());
    }

    /**
     * A new template should be restored if restoring to a different site, but existing template
     * has the same name, but different content.
     */
    public function test_restore_template_to_a_different_site_when_the_same_name_but_different_content() {
        global $CFG;

        $this->create_progcontest_with_template();
        $backupid = $this->backup_progcontest();

        $this->assertEquals(1, progcontest_settings::count_records());
        $this->assertEquals(1, template::count_records());

        $newxml = file_get_contents($CFG->dirroot . '/mod/progcontest/accessrule/seb/tests/fixtures/simpleunencrypted.seb');
        $this->template->set('content', $newxml);
        $this->template->save();

        $this->change_site();
        $this->restore_progcontest($backupid);

        // Should see additional setting record, and new template record.
        $this->assertEquals(2, progcontest_settings::count_records());
        $this->assertEquals(2, template::count_records());
    }

}
