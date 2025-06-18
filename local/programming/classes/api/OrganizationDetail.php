<?php
namespace local_programming\api;

defined('MOODLE_INTERNAL') || die();

class OrganizationDetail extends APIRequest {

    protected $orgid;

    /**
     * Constructeur : conserve l’ID pour les appels suivants
     */
    public function __construct(int $orgid) {
        $this->orgid = $orgid;
    }

    /**
     * GET /api/v2/organization/{id}
     * Récupère les détails d’une organisation
     */
    public function get() {
        $this->url = config::DOMAIN . "/api/v2/organization/{$this->orgid}";
        $this->method = "GET";
        $this->headers = ['Accept' => 'application/json'];

        return $this->run();
    }

    /**
     * PUT /api/v2/organization/{id}
     * Met à jour une organisation
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
     * Supprime une organisation
     */
    public function delete() {
        $this->url = config::DOMAIN . "/api/v2/organization/{$this->orgid}";
        $this->method = "DELETE";
        $this->headers = ['Accept' => 'application/json'];

        return $this->run();
    }
}
