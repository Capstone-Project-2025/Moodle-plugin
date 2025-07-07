<?php
namespace local_programming\api;

defined('MOODLE_INTERNAL') || die();

class OrganizationList extends APIRequest {

    /**
     * GET /api/v2/organizations
     * Retrieves the list of organizations.
     */
    public function get($params = []) {
        $this->url = config::DOMAIN . "/api/v2/organizations";
        $this->method = "GET";
        $this->headers = ['Accept' => 'application/json'];
        $this->params = $params;

        return $this->run();
    }

    /**
     * POST /api/v2/organizations
     * Creates a new organization (if the user has permission).
     */
    public function create(array $orgdata) {
        $this->url = config::DOMAIN . "/api/v2/organizations";
        $this->method = "POST";
        $this->headers = ['Content-Type' => 'application/json'];
        $this->payload = $orgdata;

        return $this->run();
    }
}
