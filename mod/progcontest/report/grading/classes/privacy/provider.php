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
 * Privacy subsystem implementation for progcontest_grading.
 *
 * @package   progcontest_grading
 * @copyright 2020 The Open University
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace progcontest_grading\privacy;

use core_privacy\local\metadata\collection;
use core_privacy\local\request\writer;

defined('MOODLE_INTERNAL') || die();

/**
 * Privacy subsystem for progcontest_grading.
 */
class provider implements
        \core_privacy\local\metadata\provider,
        \core_privacy\local\request\user_preference_provider {

    /**
     * Returns meta data about this system.
     *
     * @param   collection     $collection The initialised collection to add items to.
     * @return  collection     A listing of user data stored through this system.
     */
    public static function get_metadata(collection $collection) : collection {
        $collection->add_user_preference('progcontest_grading_pagesize', 'privacy:preference:pagesize');
        $collection->add_user_preference('progcontest_grading_order', 'privacy:preference:order');

        return $collection;
    }

    /**
     * Export all user preferences for the plugin.
     *
     * @param   int         $userid The userid of the user whose data is to be exported.
     */
    public static function export_user_preferences(int $userid) {

        // Page size.
        $pagesize = get_user_preferences("progcontest_grading_pagesize", null, $userid);
        if ($pagesize !== null) {
            writer::export_user_preference('progcontest_grading', 'pagesize', $pagesize,
                    get_string('privacy:preference:pagesize', 'progcontest_grading'));
        }

        // Attempt order.
        $order = get_user_preferences("progcontest_grading_order", null, $userid);
        if ($order !== null) {
            switch ($order) {
                case 'random':
                    $order = get_string('randomly', 'progcontest_grading');
                    break;
                case 'date':
                    $order = get_string('bydate', 'progcontest_grading');
                    break;
                case 'studentfirstname':
                    $order = get_string('studentfirstname', 'progcontest_grading');
                    break;
                case 'studentlastname':
                    $order = get_string('studentlastname', 'progcontest_grading');
                    break;
                case 'idnumber':
                    $order = get_string('bystudentidnumber', 'progcontest_grading');
                    break;
            }
            writer::export_user_preference('progcontest_grading', 'order', $order,
                    get_string('privacy:preference:order', 'progcontest_grading'));
        }
    }
}
