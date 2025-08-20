<?php
namespace local_dmoj_user_link\api;

defined('MOODLE_INTERNAL') || die();

class ProblemDetail extends APIRequest {

    protected $problemcode;

    public function __construct(string $problemcode) {
        $this->problemcode = $problemcode;
        $url = get_dmoj_domain() . "/api/v2/problem/" . $problemcode;
        parent::__construct($url, "GET", ['Accept' => 'application/json']);
    }

    /**
     * GET /api/v2/problem/{code}
     * Retrieves the details of a problem.
     */
    public function get() {
        $this->method = "GET";
        return $this->run();
    }

    /**
     * PUT /api/v2/problem/{code}
     * Updates the problem details.
     */
    public function update(array $probleminfo) {
        $this->method = "PUT";
        $this->payload = $probleminfo;
        return $this->run();
    }

    /**
     * DELETE /api/v2/problem/{code}
     * Deletes the problem.
     */
    public function delete() {
        $this->method = "DELETE";
        return $this->run();
    }

    /**
     * Static helper method to fetch a problem by its code.
     */
    public static function get_by_code(string $problemcode): array {
        return (new self($problemcode))->get();
    }
}
