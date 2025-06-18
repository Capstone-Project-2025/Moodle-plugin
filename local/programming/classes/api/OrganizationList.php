<?php
namespace local_programming\api;

defined('MOODLE_INTERNAL') || die();

class OrganizationList extends APIRequest {

    /**
     * GET /api/v2/organizations
     * Récupère la liste des organisations
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
     * Crée une organisation (si l'utilisateur a la permission)
     */
    public function create(array $orgdata) {
        $this->url = config::DOMAIN . "/api/v2/organizations";
        $this->method = "POST";
        $this->headers = ['Content-Type' => 'application/json'];
        $this->payload = $orgdata;

        return $this->run();
    }
}
