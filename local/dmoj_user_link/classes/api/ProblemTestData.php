<?php
namespace local_dmoj_user_link\api;

defined('MOODLE_INTERNAL') || die();

use curl;

class ProblemTestData extends APIRequest {

    protected $problemcode;

    /**
     * Constructor - stores the problem code
     */
    public function __construct(string $problemcode) {
        $this->problemcode = $problemcode;
        parent::__construct('', '', [], [], []);
    }

    /**
     * Retrieves the test data for a problem (static).
     */
    public static function get_data(string $code): array {
        $api = new self($code);
        return $api->get();
    }

    /**
     * Updates the test data for a problem (static).
     */
    public static function update_data(string $code, array $testdata): array {
        $api = new self($code);
        return $api->put($testdata);
    }

    /**
     * Recursively removes null or empty values from an array.
     */
    private function filter_null_values(array $array): array {
        foreach ($array as $key => $value) {
            if (is_array($value)) {
                $array[$key] = $this->filter_null_values($value);
                if (empty($array[$key])) {
                    unset($array[$key]);
                }
            } elseif ($value === '' || $value === null) {
                unset($array[$key]);
            }
        }
        return $array;
    }

    /**
     * GET /api/v2/problem/{ProblemCode}/test_data
     * Fetches the test data of a problem.
     */
    public function get(): array {
        $this->url = config::DOMAIN . "/api/v2/problem/{$this->problemcode}/test_data";
        $this->method = "GET";
        $this->headers = ['Accept' => 'application/json'];

        return $this->run();
    }

    /**
     * PUT /api/v2/problem/{ProblemCode}/test_data
     * Updates the test data of a problem.
     */
    public function put(array $testdata): array {
        $this->url = config::DOMAIN . "/api/v2/problem/{$this->problemcode}/test_data";
        $this->method = "PUT";

        // Aplatir le tableau imbriqué pour cURL (multipart/form-data)
        $this->payload = $this->flatten_payload($testdata);



        $this->headers = []; // Let APIRequest handle headers like Authorization

        return $this->run();
    }

    /**
     * Securely downloads a ZIP file with bearer authentication and returns it as a CURLFile.
     */
    public static function download_zip_protected(string $url): ?\CURLFile {
        // Retrieve an access token if not already available
        if (empty(APIRequest::$tokens['access_token'])) {
            $dummy = new self('dummy');
            $dummy->ensureAccessToken();
        }
        $token = APIRequest::$tokens['access_token'];

        $tmp = tempnam(sys_get_temp_dir(), 'zip_');
        $fp = fopen($tmp, 'w+');
        $ch = curl_init($url);

        curl_setopt($ch, CURLOPT_FILE, $fp);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        curl_setopt($ch, CURLOPT_FAILONERROR, true);

        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            "Authorization: Bearer {$token}"
        ]);

        curl_exec($ch);
        $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        fclose($fp);

        if ($status !== 200) {
            unlink($tmp);
            return null;
        }

        return new \CURLFile(
            $tmp,
            'application/zip',
            basename(parse_url($url, PHP_URL_PATH))
        );
    }

    public static function flatten_payload(array $data, string $prefix = ''): array {
        $result = [];

        foreach ($data as $key => $value) {

            // Cas spécial pour test_cases[] : utiliser la notation avec []
            if ($prefix === 'test_cases') {
                $new_key = $prefix . '[' . $key . ']';
            } else {
                $new_key = $prefix === '' ? $key : $prefix . '.' . $key;
            }

            if (is_array($value)) {
                $result += self::flatten_payload($value, $new_key);
            } else {
                $result[$new_key] = $value;
            }
        }

        return $result;
    }





}
