<?php
namespace local_programming\api;

defined('MOODLE_INTERNAL') || die();

class ProblemList extends APIRequest {

    /**
     * GET /api/v2/problems
     * Récupère la liste des problèmes accessibles
     */
    public function get($params = []) {
        $this->url = config::DOMAIN . "/api/v2/problems";
        $this->method = "GET";
        $this->headers = ['Accept' => 'application/json'];
        $this->params = $params;
        return $this->run();
    }

    /**
     * POST /api/v2/problems
     * Crée un nouveau problème avec les données spécifiées
     */
    public function create(array $probleminfo) {
        $this->url = config::DOMAIN . "/api/v2/problems";
        $this->method = "POST";
        $this->headers = ['Content-Type' => 'application/json'];
        $this->payload = $probleminfo;
        return $this->run();
    }

    public static function get_by_type(string $type): array {
        $url = config::DOMAIN . "/api/v2/problems";
        $params = ['type' => $type];
        $request = new self($url, "GET", [], $params);
        return $request->run();
    }
    
    public static function get_all(): array {
    $url = config::DOMAIN . "/api/v2/problems";
    return (new self($url, "GET", ['Accept' => 'application/json']))->run();
}

}
