<?php
namespace local_programming\api;

defined('MOODLE_INTERNAL') || die();

class ProblemDetail extends APIRequest {

    protected $problemcode;

    public function __construct(string $problemcode) {
    $this->problemcode = $problemcode;
    $url = config::DOMAIN . "/api/v2/problem/" . $problemcode;
    parent::__construct($url, "GET", ['Accept' => 'application/json']);
}


    public function get() {
        $this->method = "GET";
        return $this->run();
    }

    public function update(array $probleminfo) {
        $this->method = "PUT";
        $this->payload = $probleminfo;
        return $this->run();
    }

    public function delete() {
        $this->method = "DELETE";
        return $this->run();
    }

    public static function get_by_code(string $problemcode): array {
    return (new self($problemcode))->get();
}

}

