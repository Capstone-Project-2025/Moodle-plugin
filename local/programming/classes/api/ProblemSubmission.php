<?php
namespace local_programming\api;

defined('MOODLE_INTERNAL') || die();

class ProblemSubmission extends APIRequest {

    /**
     * Soumettre du code pour un problème donné
     */
    public function submit(string $problemcode, string $sourcecode, int $languageid, string $judge = '') {
        $url = config::DOMAIN . "/api/v2/problem/{$problemcode}/submit";
        $payload = [
            "source" => $sourcecode,
            "language" => $languageid,
            "judge" => $judge
        ];

        $this->url = $url;
        $this->method = "POST";
        $this->headers = ['Content-Type' => 'application/json'];
        $this->payload = $payload;

        return $this->run();
    }

    /**
     * Récupérer les résultats d'une soumission
     */
    public function get_result(int $submissionid) {
        $url = config::DOMAIN . "/api/v2/submission/{$submissionid}";

        $this->url = $url;
        $this->method = "GET";
        $this->headers = ['Accept' => 'application/json'];

        return $this->run();
    }

    public static function fetch_submission_result($submissionid) {
    $url = config::DOMAIN . "/api/v2/submission/" . $submissionid;
    $method = "GET";
    $request = new self($url, $method);
    return $request->run();
    }

public static function get_by_id(int $submissionid): array {
        $url = config::DOMAIN . "/api/v2/submission/" . $submissionid;
        $method = "GET";
        $request = new self($url, $method);
        return $request->run(); // renvoie ['status' => ..., 'body' => ..., 'error' => ...]
    }

}
