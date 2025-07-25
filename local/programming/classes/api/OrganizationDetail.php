<?php
namespace local_programming\api;

defined('MOODLE_INTERNAL') || die();

class OrganizationDetail extends APIRequest {

    protected $orgid;

    /**
     * Constructor: stores the organization ID for subsequent calls.
     */
    public function __construct(int $orgid) {
        $this->orgid = $orgid;
    }

    /**
     * GET /api/v2/organization/{id}
     * Retrieves details of an organization.
     */
    public function get() {
        $this->url = config::DOMAIN . "/api/v2/organization/{$this->orgid}";
        $this->method = "GET";
        $this->headers = ['Accept' => 'application/json'];

        return $this->run();
    }

    /**
     * PUT /api/v2/organization/{id}
     * Updates an organization's details.
     */
    public function update(array $orgdata) {
        $this->url = config::DOMAIN . "/api/v2/organization/{$this->orgid}";
        $this->method = "PUT";
        $this->headers = ['Content-Type' => 'application/json'];
        $this->payload = $orgdata;

        return $this->run();
    }

    /**
     * DELETE /api/v2/organization/{id}
     * Deletes an organization.
     */
    public function delete() {
        $this->url = config::DOMAIN . "/api/v2/organization/{$this->orgid}";
        $this->method = "DELETE";
        $this->headers = ['Accept' => 'application/json'];

        return $this->run();
    }
}
