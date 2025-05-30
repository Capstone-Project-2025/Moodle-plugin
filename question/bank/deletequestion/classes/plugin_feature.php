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
 * Plugin entrypoint for columns.
 *
 * @package    qbank_deletequestion
 * @copyright  2021 Catalyst IT Australia Pty Ltd
 * @author     Safat Shahin <safatshahin@catalyst-au.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace qbank_deletequestion;

use core_question\local\bank\bulk_action_base;
use core_question\local\bank\plugin_features_base;
use core_question\local\bank\view;

/**
 * Class columns is the entrypoint for the columns.
 *
 * @package    qbank_deletequestion
 * @copyright  2021 Catalyst IT Australia Pty Ltd
 * @author     Safat Shahin <safatshahin@catalyst-au.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class plugin_feature extends plugin_features_base {
    public function get_question_actions($qbank): array {
        return [
            new delete_action($qbank),
        ];
    }

    #[\Override]
    public function get_bulk_actions(?view $qbank = null): array {
        return [
            new bulk_delete_action($qbank),
        ];
    }

    public function get_question_filters(?view $qbank = null): array {
        return [
            new hidden_condition($qbank),
        ];
    }
}
