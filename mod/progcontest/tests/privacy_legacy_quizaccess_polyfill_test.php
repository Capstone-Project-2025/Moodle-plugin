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

/**
 * Unit tests for the privacy legacy polyfill for progcontest access rules.
 *
 * @copyright   2018 Andrew Nicols <andrew@nicols.co.uk>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_progcontest;

use progcontest;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/mod/progcontest/attemptlib.php');

/**
 * Unit tests for the privacy legacy polyfill for progcontest access rules.
 *
 * @copyright   2018 Andrew Nicols <andrew@nicols.co.uk>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class privacy_legacy_progcontestaccess_polyfill_test extends \advanced_testcase {
    /**
     * Test that the core_progcontestaccess\privacy\legacy_polyfill works and that the static _export_progcontestaccess_user_data can
     * be called.
     */
    public function test_export_progcontestaccess_user_data() {
        $progcontest = $this->createMock(progcontest::class);
        $user = (object) [];
        $returnvalue = (object) [];

        $mock = $this->createMock(test_privacy_legacy_progcontestaccess_polyfill_mock_wrapper::class);
        $mock->expects($this->once())
            ->method('get_return_value')
            ->with('_export_progcontestaccess_user_data', [$progcontest, $user])
            ->willReturn($returnvalue);

        test_privacy_legacy_progcontestaccess_polyfill_provider::$mock = $mock;
        $result = test_privacy_legacy_progcontestaccess_polyfill_provider::export_progcontestaccess_user_data($progcontest, $user);
        $this->assertSame($returnvalue, $result);
    }

    /**
     * Test the _delete_progcontestaccess_for_context shim.
     */
    public function test_delete_progcontestaccess_for_context() {
        $context = \context_system::instance();

        $progcontest = $this->createMock(progcontest::class);

        $mock = $this->createMock(test_privacy_legacy_progcontestaccess_polyfill_mock_wrapper::class);
        $mock->expects($this->once())
            ->method('get_return_value')
            ->with('_delete_progcontestaccess_data_for_all_users_in_context', [$progcontest]);

        test_privacy_legacy_progcontestaccess_polyfill_provider::$mock = $mock;
        test_privacy_legacy_progcontestaccess_polyfill_provider::delete_progcontestaccess_data_for_all_users_in_context($progcontest);
    }

    /**
     * Test the _delete_progcontestaccess_for_user shim.
     */
    public function test_delete_progcontestaccess_for_user() {
        $context = \context_system::instance();

        $progcontest = $this->createMock(progcontest::class);
        $user = (object) [];

        $mock = $this->createMock(test_privacy_legacy_progcontestaccess_polyfill_mock_wrapper::class);
        $mock->expects($this->once())
            ->method('get_return_value')
            ->with('_delete_progcontestaccess_data_for_user', [$progcontest, $user]);

        test_privacy_legacy_progcontestaccess_polyfill_provider::$mock = $mock;
        test_privacy_legacy_progcontestaccess_polyfill_provider::delete_progcontestaccess_data_for_user($progcontest, $user);
    }

    /**
     * Test the _delete_progcontestaccess_for_users shim.
     */
    public function test_delete_progcontestaccess_for_users() {
        $context = $this->createMock(\context_module::class);
        $user = (object) [];
        $approveduserlist = new \core_privacy\local\request\approved_userlist($context, 'mod_progcontest', [$user]);

        $mock = $this->createMock(test_privacy_legacy_progcontestaccess_polyfill_mock_wrapper::class);
        $mock->expects($this->once())
            ->method('get_return_value')
            ->with('_delete_progcontestaccess_data_for_users', [$approveduserlist]);

        test_privacy_legacy_progcontestaccess_polyfill_provider::$mock = $mock;
        test_privacy_legacy_progcontestaccess_polyfill_provider::delete_progcontestaccess_data_for_users($approveduserlist);
    }
}

/**
 * Legacy polyfill test class for the progcontestaccess_provider.
 *
 * @copyright   2018 Andrew Nicols <andrew@nicols.co.uk>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class test_privacy_legacy_progcontestaccess_polyfill_provider implements
        \core_privacy\local\metadata\provider,
        \mod_progcontest\privacy\progcontestaccess_provider,
        \mod_progcontest\privacy\progcontestaccess_user_provider {

    use \mod_progcontest\privacy\legacy_progcontestaccess_polyfill;
    use \core_privacy\local\legacy_polyfill;

    /**
     * @var test_privacy_legacy_progcontestaccess_polyfill_provider $mock.
     */
    public static $mock = null;

    /**
     * Export all user data for the progcontestaccess plugin.
     *
     * @param \progcontest $progcontest
     * @param \stdClass $user
     */
    protected static function _export_progcontestaccess_user_data($progcontest, $user) {
        return static::$mock->get_return_value(__FUNCTION__, func_get_args());
    }

    /**
     * Deletes all user data for the given context.
     *
     * @param \progcontest $progcontest
     */
    protected static function _delete_progcontestaccess_data_for_all_users_in_context($progcontest) {
        static::$mock->get_return_value(__FUNCTION__, func_get_args());
    }

    /**
     * Delete personal data for the given user and context.
     *
     * @param   \progcontest           $progcontest The progcontest being deleted
     * @param   \stdClass       $user The user to export data for
     */
    protected static function _delete_progcontestaccess_data_for_user($progcontest, $user) {
        static::$mock->get_return_value(__FUNCTION__, func_get_args());
    }

    /**
     * Delete all user data for the specified users, in the specified context.
     *
     * @param   \core_privacy\local\request\approved_userlist   $userlist
     */
    protected static function _delete_progcontestaccess_data_for_users($userlist) {
        static::$mock->get_return_value(__FUNCTION__, func_get_args());
    }

    /**
     * Returns metadata about this plugin.
     *
     * @param   \core_privacy\local\metadata\collection $collection The initialised collection to add items to.
     * @return  \core_privacy\local\metadata\collection     A listing of user data stored through this system.
     */
    protected static function _get_metadata(\core_privacy\local\metadata\collection $collection) {
        return $collection;
    }
}

/**
 * Called inside the polyfill methods in the test polyfill provider, allowing us to ensure these are called with correct params.
 *
 * @copyright   2018 Andrew Nicols <andrew@nicols.co.uk>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class test_privacy_legacy_progcontestaccess_polyfill_mock_wrapper {
    /**
     * Get the return value for the specified item.
     */
    public function get_return_value() {
    }
}
