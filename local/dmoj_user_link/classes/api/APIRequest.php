<?php
namespace local_dmoj_user_link\api;

defined('MOODLE_INTERNAL') || die();
require_once(__DIR__ . '/requests_to_dmoj.php');

class APIRequest {
    protected string $url;
    protected string $method;
    protected array $headers;
    protected array $params;
    protected array $payload;
    protected string $access_token_url;
    protected string $refresh_token_url;

    protected static array $tokens = [];

    public function __construct(string $url, string $method, array $headers = [], array $params = [], array $payload = []) {
        $this->url = $url;
        $this->method = strtoupper($method);
        $this->headers = $headers;
        $this->params = $params;
        $this->payload = $payload;
        $this->access_token_url = get_dmoj_domain() . '/api/token/';
        $this->refresh_token_url = get_dmoj_domain() . '/api/token/refresh/';
    }

    public function run(): array {
        $this->ensureAccessToken();
        $response = $this->send();

        if ($response['status'] === 401) {
            try {
                $this->refreshToken();
                $response = $this->send();
            } catch (\Exception $e) {
                $this->clearTokens();
                $this->ensureAccessToken();
                $response = $this->send();
            }
        }

        return $response;
    }

    protected function ensureAccessToken(): void {
        if (empty(self::$tokens['access_token'])) {
            $this->fetchAccessToken();
        }
        $this->headers['Authorization'] = 'Bearer ' . self::$tokens['access_token'];
    }

    protected function fetchAccessToken(): void {
        require_login();
        global $USER;

        $payload = [
            "api_secret" => config::TOKEN_SECRET,
            "provider" => "moodle",
            "uid" => $USER->id
        ];

        $response = $this->curlRequest($this->access_token_url, 'POST', $payload);

        if ($response['status'] === 200 && isset($response['body']['access'], $response['body']['refresh'])) {
            self::$tokens = [
                'access_token' => $response['body']['access'],
                'refresh_token' => $response['body']['refresh']
            ];
        } else {
            throw new \Exception("The DMOJ server is currently unavailable. Please try again later.");        }
    }

    protected function refreshToken(): void {
        $payload = ["refresh" => self::$tokens["refresh_token"]];
        $response = $this->curlRequest($this->refresh_token_url, 'POST', $payload);

        if ($response['status'] === 200 && isset($response['body']['access'], $response['body']['refresh'])) {
            self::$tokens = [
                'access_token' => $response['body']['access'],
                'refresh_token' => $response['body']['refresh']
            ];
        } else {
            throw new \Exception("The DMOJ server is currently unavailable. Please try again later.");        }
    }

    protected function clearTokens(): void {
        self::$tokens = [];
    }

    protected function curlRequest(string $url, string $method, array $payload = []): array {
        $ch = curl_init($url);
        $headers = ['Content-Type: application/json'];

        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, strtoupper($method));
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

        if (!empty($payload)) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
        }

        $body = curl_exec($ch);
        $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        return [
            'status' => $status,
            'body' => json_decode($body, true),
            'error' => $error ?: null
        ];
    }

    protected function is_multipart_request(): bool {
        return $this->has_file_in_array($this->payload);
    }

    private function has_file_in_array(array $data): bool {
        foreach ($data as $value) {
            if ($value instanceof \CURLFile) {
                return true;
            } elseif (is_array($value)) {
                if ($this->has_file_in_array($value)) {
                    return true;
                }
            }
        }
        return false;
    }

    public function send(): array {
        $fullurl = $this->url;
        if (!empty($this->params)) {
            $query = http_build_query($this->params);
            $fullurl .= (strpos($this->url, '?') === false ? '?' : '&') . $query;
        }
        $fullurl = html_entity_decode($fullurl, ENT_QUOTES | ENT_HTML5);

        $ch = curl_init($fullurl);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $this->method);

        $formattedHeaders = [];

        // Prepare the data to send
        if (in_array($this->method, ['POST', 'PUT']) && !empty($this->payload)) {
            if ($this->is_multipart_request()) {
                curl_setopt($ch, CURLOPT_POSTFIELDS, $this->payload);
                // Let cURL handle multipart Content-Type automatically
            } else {
                curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($this->payload));
            }
        }

        // Add headers once payload is ready
        foreach ($this->headers as $k => $v) {
            if (strtolower($k) === 'content-type' && $this->is_multipart_request()) {
                continue; // Skip forcing multipart Content-Type
            }
            $formattedHeaders[] = "$k: $v";
        }

        curl_setopt($ch, CURLOPT_HTTPHEADER, $formattedHeaders);

        $body = curl_exec($ch);
        $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        $decodedBody = json_decode($body, true);


        return [
            'status' => $status,
            'body' => $decodedBody,
            'error' => $semanticError ?: ($error ?: null)
        ];
    }



}
