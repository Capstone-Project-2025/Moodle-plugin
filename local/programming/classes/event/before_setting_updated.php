<?php
/**
 * local_myplugin_setting_updated
 *
 * Class for event to be triggered when a new blog entry is associated with a context.
 *
 * @property-read array $other {
 *      Extra information about event.
 * }
 *
 * @package    local_myplugin
 * @since      Moodle 3.11
 * @copyright  2025 Dinh
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
namespace local_myplugin\event;

defined('MOODLE_INTERNAL') || die();

class before_setting_updated extends \core\event\base {
    protected function init() {
        $this->data['crud'] = 'u';
        $this->data['edulevel'] = self::LEVEL_OTHER;
        $this->context = \context_system::instance();
    }

    public static function get_name() {
        return get_string('event_setting_before_updated', 'local_myplugin');
    }

    public function get_description() {
        return "The setting '{$this->other['name']}' is about to be updated from '{$this->other['oldvalue']}' to '{$this->other['newvalue']}'.";
    }

    public function get_url() {
        return new \moodle_url('/admin/settings.php', ['section' => 'local_myplugin']);
    }
}