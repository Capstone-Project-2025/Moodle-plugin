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
 * Restore instructions for the seb (Safe Exam Browser) progcontest access subplugin.
 *
 * @package    progcontestaccess_seb
 * @category   backup
 * @author     Andrew Madden <andrewmadden@catalyst-au.net>
 * @copyright  2020 Catalyst IT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use progcontestaccess_seb\progcontest_settings;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/mod/progcontest/backup/moodle2/restore_mod_progcontest_access_subplugin.class.php');

/**
 * Restore instructions for the seb (Safe Exam Browser) progcontest access subplugin.
 *
 * @copyright  2020 Catalyst IT
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class restore_progcontestaccess_seb_subplugin extends restore_mod_progcontest_access_subplugin {

    /**
     * Provides path structure required to restore data for seb progcontest access plugin.
     *
     * @return array
     */
    protected function define_progcontest_subplugin_structure() {
        $paths = [];

        // Programmingcontest settings.
        $path = $this->get_pathfor('/seb_settings'); // Subplugin root path.
        $paths[] = new restore_path_element('seb_settings', $path);

        // Template settings.
        $path = $this->get_pathfor('/seb_settings/seb_template');
        $paths[] = new restore_path_element('seb_template', $path);

        return $paths;
    }

    /**
     * Process the restored data for the seb_settings table.
     *
     * @param stdClass $data Data for seb_settings retrieved from backup xml.
     */
    public function process_seb_settings($data) {
        global $DB, $USER;

        // Process progcontestsettings.
        $data = (object) $data;
        $data->progcontestid = $this->get_new_parentid('progcontest'); // Update progcontestid with new reference.
        $data->cmid = $this->task->get_moduleid();

        unset($data->id);
        $data->timecreated = $data->timemodified = time();
        $data->usermodified = $USER->id;
        $DB->insert_record(progcontestaccess_seb\progcontest_settings::TABLE, $data);

        // Process attached files.
        $this->add_related_files('progcontestaccess_seb', 'filemanager_sebconfigfile', null);
    }

    /**
     * Process the restored data for the seb_template table.
     *
     * @param stdClass $data Data for seb_template retrieved from backup xml.
     */
    public function process_seb_template($data) {
        global $DB;

        $data = (object) $data;

        $progcontestid = $this->get_new_parentid('progcontest');

        $template = null;
        if ($this->task->is_samesite()) {
            $template = \progcontestaccess_seb\template::get_record(['id' => $data->id]);
        } else {
            // In a different site, try to find existing template with the same name and content.
            $candidates = \progcontestaccess_seb\template::get_records(['name' => $data->name]);
            foreach ($candidates as $candidate) {
                if ($candidate->get('content') == $data->content) {
                    $template = $candidate;
                    break;
                }
            }
        }

        if (empty($template)) {
            unset($data->id);
            $template = new \progcontestaccess_seb\template(0, $data);
            $template->save();
        }

        // Update the restored progcontest settings to use restored template.
        $DB->set_field(\progcontestaccess_seb\progcontest_settings::TABLE, 'templateid', $template->get('id'), ['progcontestid' => $progcontestid]);
    }

}

