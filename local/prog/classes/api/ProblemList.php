<?php
namespace local_prog\api;

defined('MOODLE_INTERNAL') || die();

class ProblemList extends APIRequest {

    /**
     * Constructor for listing problems with optional query parameters.
     */
    public function __construct(array $params = []) {
        parent::__construct(
            config::DOMAIN . "/api/v2/problems",
            "GET",
            ['Accept' => 'application/json'],
            $params
        );
    }

    /**
     * Static method to retrieve all problems.
     */
    public static function get_all(): array {
        return (new self())->run();
    }

    /**
     * Static method to retrieve problems filtered by type.
     */
    public static function get_by_type(string $type): array {
        return (new self(['type' => $type]))->run();
    }

    /**
     * Static method to create a new problem.
     */
    public static function create(array $probleminfo): array {
        $request = new APIRequest(
            config::DOMAIN . "/api/v2/problems",
            "POST",
            ['Content-Type' => 'application/json'],
            [],
            $probleminfo
        );
        return $request->run();
    }
}
