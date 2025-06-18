<?php
namespace local_programming\api;

defined('MOODLE_INTERNAL') || die();

class ProblemTestData extends APIRequest {

    protected $problemcode;

    /**
     * Constructeur - conserve le code du problème
     */
    public function __construct(string $problemcode) {
        $this->problemcode = $problemcode;
    }

    /**
     * GET /api/v2/problem/{ProblemCode}/test_data
     */
    public function get() {
        $this->url = config::DOMAIN . "/api/v2/problem/{$this->problemcode}/test_data";
        $this->method = "GET";
        $this->headers = ['Accept' => 'application/json'];

        return $this->run();
    }

    /**
     * PUT /api/v2/problem/{ProblemCode}/test_data
     */
    public function update(array $testdata) {
        $this->url = config::DOMAIN . "/api/v2/problem/{$this->problemcode}/test_data";
        $this->method = "PUT";
        $this->headers = ['Content-Type' => 'application/json'];
        $this->payload = $testdata;

        return $this->run();
    }
    public static function get_by_problemcode(string $problemcode): array {
    $url = config::DOMAIN . "/api/v2/problem/$problemcode/test_data";
    return (new self($url, "GET"))->run();
}

}
