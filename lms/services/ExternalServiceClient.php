<?php
interface ExternalServiceClient {
    public function request($operation, array $payload = []);
}

final class LocalServiceClient implements ExternalServiceClient {
    public function request($operation, array $payload = []) {
        return ['operation' => $operation, 'status' => 'not_configured', 'data' => $payload];
    }
}

final class HttpServiceClient implements ExternalServiceClient {
    private $baseUrl;
    private $apiKey;
    private $timeout;

    public function __construct($baseUrl, $apiKey = '', $timeout = 10) {
        $this->baseUrl = rtrim($baseUrl, '/');
        $this->apiKey = $apiKey;
        $this->timeout = max(1, (int)$timeout);
    }

    public function request($operation, array $payload = []) {
        if (!function_exists('curl_init')) {
            throw new RuntimeException('cURL is required for external service calls.');
        }

        $operationPath = trim((string)$operation, '/');
        $operationPath = implode('/', array_map('rawurlencode', explode('/', $operationPath)));
        $url = $this->baseUrl . '/' . $operationPath;
        $headers = ['Content-Type: application/json', 'Accept: application/json'];
        if ($this->apiKey !== '') {
            $headers[] = 'Authorization: Bearer ' . $this->apiKey;
        }

        $handle = curl_init($url);
        curl_setopt_array($handle, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($payload),
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => $this->timeout,
            CURLOPT_TIMEOUT => $this->timeout,
        ]);
        $body = curl_exec($handle);
        $error = curl_error($handle);
        $status = (int)curl_getinfo($handle, CURLINFO_HTTP_CODE);
        curl_close($handle);

        if ($body === false) {
            throw new RuntimeException('External service request failed: ' . $error);
        }
        $decoded = json_decode($body, true);
        if (!is_array($decoded)) {
            throw new RuntimeException('External service returned invalid JSON.');
        }
        if ($status < 200 || $status >= 300) {
            throw new RuntimeException('External service returned HTTP ' . $status . '.');
        }
        return $decoded;
    }
}

function service_client() {
    static $client;
    if (!$client) {
        // Use the local ML service by default; deployments can override this URL.
        $baseUrl = getenv('LMS_SERVICE_URL') ?: 'http://127.0.0.1:8091';
        if ($baseUrl === '') {
            $client = new LocalServiceClient();
        } else {
            $client = new HttpServiceClient(
                $baseUrl,
                getenv('LMS_SERVICE_API_KEY') ?: '',
                getenv('LMS_SERVICE_TIMEOUT') ?: 10
            );
        }
    }
    return $client;
}
