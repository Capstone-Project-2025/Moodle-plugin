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
 * Behat data generator for mod_progcontest.
 *
 * @package   mod_progcontest
 * @category  test
 * @copyright 2019 The Open University
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();


/**
 * Behat data generator for mod_progcontest.
 *
 * @copyright 2019 The Open University
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class behat_mod_progcontest_generator extends behat_generator_base {

    protected function get_creatable_entities(): array {
        return [
            'group overrides' => [
                'singular' => 'group override',
                'datagenerator' => 'override',
                'required' => ['progcontest', 'group'],
                'switchids' => ['progcontest' => 'progcontest', 'group' => 'groupid'],
            ],
            'user overrides' => [
                'singular' => 'user override',
                'datagenerator' => 'override',
                'required' => ['progcontest', 'user'],
                'switchids' => ['progcontest' => 'progcontest', 'user' => 'userid'],
            ],
        ];
    }

    /**
     * Look up the id of a progcontest from its name.
     *
     * @param string $progcontestname the progcontest name, for example 'Test progcontest'.
     * @return int corresponding id.
     */
    protected function get_progcontest_id(string $progcontestname): int {
        global $DB;

        if (!$id = $DB->get_field('progcontest', 'id', ['name' => $progcontestname])) {
            throw new Exception('There is no progcontest with name "' . $progcontestname . '" does not exist');
        }
        return $id;
    }
}
