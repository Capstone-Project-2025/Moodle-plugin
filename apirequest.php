<?php
// Based on "requests" Python class in the provided code file, but not actually included
class Request {
    public $url;
    public $token;
    // Must be a PHP associative array for the three below:
    public $payload;
    public $headers;

    public function __construct($url, $token = null, $payload = null, $headers = null){
        $this->url = $url;
        $this->token = $token;
        $this->payload = $payload;
        $this->headers = $headers;
    }
    public function get(){
        $curl = curl_init();
        curl_setopt($curl, CURLOPT_URL, $this->url);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        $response = curl_exec($curl);
        $statusCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        $error = curl_error($curl);
        $errorCode = curl_errno($curl);
        curl_close($curl);
        if ($errorCode) {
            return [
                'success' => false,
                'error' => $error,
                'code' => $errorCode
            ];
        }
        return [
            'success' => true,
            'status_code' => $statusCode,
            'response' => json_decode($response, true)
        ];
    }
    public function post(){
        $curl = curl_init($this->url);
        $jsonData = json_encode($this->payload);
        $headers = $this->headers ?? [];
        $headers[] = 'Content-Type: application/json';

        if ($this->token) {
            $headers[] = 'Authorization: Bearer ' . $this->token;
        }

        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl, CURLOPT_POST, true);
        curl_setopt($curl, CURLOPT_POSTFIELDS, $jsonData);
        curl_setopt($curl, CURLOPT_HTTPHEADER, $headers);

        $response = curl_exec($curl);
        $statusCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        $error = curl_error($curl);
        $errorCode = curl_errno($curl);
        curl_close($curl);

        if ($errorCode) {
            return [
                'success' => false,
                'error' => $error,
                'code' => $errorCode
            ];
        }
        return [
            'success' => true,
            'status_code' => $statusCode,
            'response' => json_decode($response, true)
        ];
    }
    public function put(){
        $curl = curl_init($this->url);
        $jsonData = json_encode($this->payload);

        // Set up headers
        $headers = $this->headers ?? [];
        $headers[] = 'Content-Type: application/json';

        if ($this->token) {
            $headers[] = 'Authorization: Bearer ' . $this->token;
        }

        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl, CURLOPT_CUSTOMREQUEST, 'PUT');  // This makes it a PUT request
        curl_setopt($curl, CURLOPT_POSTFIELDS, $jsonData);
        curl_setopt($curl, CURLOPT_HTTPHEADER, $headers);

        $response = curl_exec($curl);
        $statusCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        $error = curl_error($curl);
        $errorCode = curl_errno($curl);
        curl_close($curl);

        if ($errorCode) {
            return [
                'success' => false,
                'error' => $error,
                'code' => $errorCode
            ];
        }
        return [
            'success' => true,
            'status_code' => $statusCode,
            'response' => json_decode($response, true)
        ];
    }
    public function delete() {
        $curl = curl_init($this->url);

        // Prepare headers
        $headers = $this->headers ?? [];
        if ($this->token) {
            $headers[] = 'Authorization: Bearer ' . $this->token;
        }

        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl, CURLOPT_CUSTOMREQUEST, 'DELETE');  // Use DELETE method
        curl_setopt($curl, CURLOPT_HTTPHEADER, $headers);

        $response = curl_exec($curl);
        $statusCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        $error = curl_error($curl);
        $errorCode = curl_errno($curl);
        curl_close($curl);

        if ($errorCode) {
            return [
                'success' => false,
                'error' => $error,
                'code' => $errorCode,
            ];
        }
        return [
            'success' => true,
            'status_code' => $statusCode,
            'response' => json_decode($response, true),
        ];
    }
}

// Temporary, this should be placed in a separate file later
$DOMAIN = "http://139.59.105.152";
$TOKEN_OBTAIN_SECRET = "secret";

class APIRequest {
    public $ACCESS_TOKEN_URL;
    public $REFRESH_TOKEN_URL;
    public $storage;

    public $base_url; // base_url of the base page
    public $method;
    public $headers; // No need to declare this because it's added in the base Request class above
    public $params;
    public $payload;

    public function __construct($base_url, $method, $headers = null, $params = null, $payload = null) {
        global $DOMAIN;
        $this->ACCESS_TOKEN_URL = $DOMAIN . "/api/token/";
        $this->REFRESH_TOKEN_URL = $DOMAIN . "/api/token/refresh/";
        $this->storage = [];

        $this->base_url = $base_url;
        $this->method = $method;
        $this->headers = $headers ?? [];
        $this->params = $params ?? [];
        $this->payload = $payload ?? [];
    }

    public function send() {
        $access_token = $this->storage["access_token"];
        if ($access_token !== null){
            $this->headers["Authorization"] = "Bearer " . $access_token;
        }

        $request = new Request($this->base_url, $access_token, $this->payload, $this->headers);
        if ($this->method == "GET"){
            $response = $request->get();
        } else if ($this->method == "POST"){
            $response = $request->post();
        } else if ($this->method == "PUT"){
            $response = $request->put();
        } else if ($this->method == "DELETE"){
            $response = $request->delete();
        } else {
            echo "Unsupported HTTP method: " . $this->method;
            exit;
        }
        return $response;
    }

    public function GetAccessToken() {
        global $TOKEN_OBTAIN_SECRET; // Temporary
        require_login();
        global $USER;
        echo "User ID: " . $USER->id . "<br>";
        $payload = [
            "api_secret" => $TOKEN_OBTAIN_SECRET,
            "provider" => "moodle",
            "uid" => $USER->id
        ];
        $request = new Request($this->ACCESS_TOKEN_URL, null, $payload, $this->headers);
        $response = $request->post();
        if ($response["success"] && $response["status_code"] == 200){
            $this->storage["access_token"] = $response["response"]["access"];
            $this->storage["refresh_token"] = $response["response"]["refresh"];
            return $response;
        } else {
            echo "Failed to obtain access token";
            exit;
        }
    }

    public function TryRefreshToken() {
        $payload = [
            "refresh" => $this->storage["refresh_token"]
        ];
        $request = new Request($this->REFRESH_TOKEN_URL, null, $payload, $this->headers);
        $response = $request->post();
        if ($response["success"] && $response["status_code"] == 200){
            $this->storage["access_token"] = $response["response"]["access"];
            $this->storage["refresh_token"] = $response["response"]["refresh"];
            return $response;
        } else {
            echo "Failed to refresh access token";
            exit;
        }
    }

    public function ClearTokens() {
        unset($this->storage["access_token"]);
        unset($this->storage["refresh_token"]);
    }

    public function run() {
        $this->GetAccessToken(); // We'll resolve this later
        $response = $this->send();
        if ($response["status_code"] == 401){
            try {
                $this->TryRefreshToken();
                $response = $this->send(); # Retry with access token obtained from refresh token
            } catch (Exception $e) {
                echo "Error refreshing token: " . $e;
                $this->ClearTokens();
                try {
                    $this->GetAccessToken();
                    $response = $this->send(); # Retry with new access token, if refresh token is invalid
                } catch (Exception $e) {
                    echo "Error obtaining new access token: " . $e;
                    if ($this->method == "GET"){
                        $response = $this->send();
                    } else {
                        throw new Exception("Cannot send authenticated request without valid token");
                    }
                }
            }
        }
        return $response;
    }
    public function get_all_accessible_problems(){
        $request_url = $this->base_url . "/api/v2/problems";
        $access_token = $this->storage["access_token"]; // Must take from the stored access token, not getting a new token from GetAccessToken()
        echo "Access token: " . $this->storage["access_token"] . "<br>";
        $request = new Request($request_url, $access_token);
        $response = $request->get();
        if ($response["success"] && $response["status_code"] == 200){
            return $response;
        } else {
            echo "Request failed";
            exit;
        }
    }
}
// END OF APIREQUEST
class GetProblemList extends APIRequest {
    public function __construct($base_url, $method, $headers = null, $params = null, $payload = null) {
        global $DOMAIN;
        $this->ACCESS_TOKEN_URL = $DOMAIN . "/api/token/";
        $this->REFRESH_TOKEN_URL = $DOMAIN . "/api/token/refresh/";
        $this->storage = [];

        $this->base_url = $base_url;
        $this->method = $method;
        $this->headers = $headers ?? [];
        $this->params = $params ?? [];
        $this->payload = $payload ?? [];
    }
}
?>