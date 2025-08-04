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
 * Readme file for local customisations
 *
 * @package    local_programming
 * @copyright  Dinh
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
namespace local_prog\api;

require_once(__DIR__ . '/APIRequest.php');

function get_dmoj_domain(): string {
    return get_config('local_programming', 'dmoj_domain') ?: 'http://example.com';
}

class PrepareDownloadData extends APIRequest {
    public function __construct($payload = []) {
        $url = get_dmoj_domain() . "/api/v2/user/download-data";
        $method = "POST";
        parent::__construct($url, $method, [], [], $payload);
    }
}

class GetDownloadURL extends APIRequest {
    public function __construct() {
        $url = get_dmoj_domain() . "/api/v2/user/download-data";
        $method = "GET";
        parent::__construct($url, $method);
    }
}

class FetchZipFile extends APIRequest {
    public function __construct($url) {
        $method = "GET";
        parent::__construct($url, $method);
    }
}

class GetUserDMOJId extends APIRequest {
    public function __construct($ids = []) {
        $url = get_dmoj_domain() . "/api/v2/moodle-to-dmoj/";
        $method = "POST";
        $payload = [
            "provider" => "moodle",
            "id" => $ids,
        ];
        parent::__construct($url, $method, [], [], $payload);
    }
}

class ForceCreateDMOJAccount extends APIRequest {
    public function __construct($payload = []) {
        $url = get_dmoj_domain() . "/api/v2/users/create";
        $method = "POST";
        parent::__construct($url, $method, [], [], $payload);
    }
}

class DeleteDMOJAccount extends APIRequest {
    public function __construct($params = []) {
        $url = get_dmoj_domain() . "/api/v2/users/create";
        $method = "DELETE";
        parent::__construct($url, $method, [], $params, []);
    }
}