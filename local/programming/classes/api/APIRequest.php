<?php
namespace local_programming\api;

defined('MOODLE_INTERNAL') || die();

class APIRequest {
    public static $ACCESS_TOKEN_URL;
    public static $REFRESH_TOKEN_URL;
    public static $storage = [];

    public $url;
    public $method;
    public $headers;
    public $params;
    public $payload;

    public function __construct($url, $method, array $headers = [], array $params = [], array $payload = []) {
        self::$ACCESS_TOKEN_URL = config::DOMAIN . "/api/token/";
        self::$REFRESH_TOKEN_URL = config::DOMAIN . "/api/token/refresh/";

        $this->url = $url;
        $this->method = strtoupper($method);
        $this->headers = $headers;
        $this->params = $params;
        $this->payload = $payload;
    }

    public function send() {
        if (!isset($this->headers['Authorization'])) {
        $access_token = self::$storage['access_token'] ?? null;
        if ($access_token) {
            $this->headers['Authorization'] = "Bearer {$access_token}";
        }
    }

    if (!isset($this->headers['Content-Type'])) {
        $this->headers['Content-Type'] = 'application/json';
    }

        $formattedHeaders = [];
        foreach ($this->headers as $key => $value) {
            $formattedHeaders[] = "$key: $value";
        }

        if (in_array($this->method, ['GET', 'DELETE']) && !empty($this->params)) {
            $queryString = http_build_query($this->params);
            $this->url .= (strpos($this->url, '?') === false ? '?' : '&') . $queryString;
        }

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $this->url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $this->method);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $formattedHeaders);

        if (in_array($this->method, ['POST', 'PUT'])) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($this->payload));
            $formattedHeaders[] = 'Content-Type: application/json';
        }

        $responseBody = curl_exec($ch);
        $statusCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);

        curl_close($ch);

        return [
            'status' => $statusCode,
            'body' => $responseBody,
            'error' => $error ?: null
        ];
    }

    public function GetAccessToken() {
        require_login();
        global $USER;

        $payload = [
            "api_secret" => config::TOKEN_SECRET,
            "provider" => "moodle",
            "uid" => $USER->id
        ];

        $ch = curl_init(self::$ACCESS_TOKEN_URL);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));

        $responseBody = curl_exec($ch);
        $statusCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($error) {
            throw new \Exception("cURL Error: $error");
        }

        $responseData = json_decode($responseBody, true);

        if ($statusCode === 200 && isset($responseData['access'], $responseData['refresh'])) {
            self::$storage['access_token'] = $responseData['access'];
            self::$storage['refresh_token'] = $responseData['refresh'];
            return $responseData;
        } else {
            throw new \Exception("Failed to obtain access token: HTTP $statusCode");
        }
    }

    public function TryRefreshToken() {
        $payload = ["refresh" => self::$storage["refresh_token"]];

        $ch = curl_init(self::$REFRESH_TOKEN_URL);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));

        $responseBody = curl_exec($ch);
        $statusCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($error) {
            throw new \Exception("cURL Error: $error");
        }

        $responseData = json_decode($responseBody, true);

        if ($statusCode === 200 && isset($responseData['access'], $responseData['refresh'])) {
            self::$storage['access_token'] = $responseData['access'];
            self::$storage['refresh_token'] = $responseData['refresh'];
            return $responseData;
        } else {
            throw new \Exception("Failed to refresh token: HTTP $statusCode");
        }
    }

    public function ClearTokens() {
        unset(self::$storage['access_token']);
        unset(self::$storage['refresh_token']);
    }

    public function run() {
        $this->GetAccessToken();
        $response = $this->send();

        if ($response["status"] == 401) {
            try {
                $this->TryRefreshToken();
                $response = $this->send();
            } catch (\Exception $e) {
                $this->ClearTokens();
                try {
                    $this->GetAccessToken();
                    $response = $this->send();
                } catch (\Exception $e) {
                    if ($this->method == "GET") {
                        $response = $this->send();
                    } else {
                        throw new \Exception("Cannot send authenticated request without valid token");
                    }
                }
            }
        }

        return $response;
    }
}
