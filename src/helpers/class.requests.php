<?php
if (!defined('ABSPATH')) { exit; }

class EulenRequest {

    public $api_url;
    private $authToken = null;

    public function __construct() {
        $this->api_url = 'https://depix.eulen.app/api';
    }

    public function setAuthToken(?string $token): void
    {
        $this->authToken = (is_string($token) && trim($token)!=='') ? trim($token) : null;
    }

    private function applyAuth(array $headers): array
    {
        if ($this->authToken && empty($headers['Authorization'])) {
            $headers['Authorization'] = 'Bearer ' . $this->authToken;
        }
        return $headers;
    }

    public function get($path, $headers = array(), $query_params = array()) {
        $url = $this->api_url . $path;
        if (is_array($query_params) && !empty($query_params)) {
            $url = add_query_arg(array_map('rawurlencode', $query_params), $url);
        }
        $headers = $this->applyAuth($headers);
        $response = wp_remote_get($url, array(
            'headers' => $headers,
        ));
        return $response;
    }

    public function post($path, $body = array(), $headers = array()) {
        $headers = $this->applyAuth($headers);
        $args = array(
            'headers' => $headers,
        );

        if (isset($headers['Content-Type']) && stripos($headers['Content-Type'], 'application/json') !== false) {
            $args['body'] = is_string($body) ? $body : wp_json_encode($body);
        } else {
            $args['body'] = $body;
        }
        $response = wp_remote_post($this->api_url . $path, $args);
        return $response;
    }

}
