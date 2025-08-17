<?php

if (!defined('ABSPATH')) { exit; }

class EulenWebhook {

    private $database;

    public function __construct() {
        $this->database = new DepixTablesWP();
    }

    public function init() {
        add_action('rest_api_init', array($this, 'registerRoutes'));
    }

    public function registerRoutes() {
        register_rest_route('depix/v1', '/webhook', array(
            'methods' => 'POST',
            'callback' => array($this, 'handleRequest'),
            'permission_callback' => array($this, 'verifyWebhookSignature'),
        ));
    }

    public function verifyWebhookSignature( $request ) {
        $secret = null;
        if (defined('DEPIX_WEBHOOK_SECRET')) {
            $val = constant('DEPIX_WEBHOOK_SECRET');
            if (is_string($val) && $val !== '') {
                $secret = $val;
            }
        }
        if (!$secret) {
            $opt = get_option('depix_webhook_secret_enc_v1', '');
            if (is_string($opt) && $opt !== '' && class_exists('EulenPanel')) {
                $plain = EulenPanel::extract_plain_token_from_option_value($opt);
                if (is_string($plain) && $plain !== '') {
                    $secret = $plain;
                }
            }
        }

        // Sem secret configurado: aceitar provisoriamente, mas alertar em log
        if (!$secret) {
            return new WP_Error('depix_webhook_unconfigured', 'webhook secret missing', array('status' => 503));
        }

        $auth = '';
        if (is_object($request) && method_exists($request, 'get_header')) {
            $auth = trim((string) $request->get_header('authorization'));
        }
        if ($auth === '' && isset($_SERVER['HTTP_AUTHORIZATION'])) {
            $auth = trim((string) $_SERVER['HTTP_AUTHORIZATION']);
        }
        if ($auth === '' && isset($_SERVER['REDIRECT_HTTP_AUTHORIZATION'])) {
            $auth = trim((string) $_SERVER['REDIRECT_HTTP_AUTHORIZATION']);
        }

        $expected = 'Basic ' . $secret;
        if (!function_exists('hash_equals')) {
            $valid = ($auth === $expected);
        } else {
            $valid = hash_equals($expected, $auth);
        }

        if (!$valid) {
            return new WP_Error('depix_webhook_forbidden', 'invalid signature', array('status' => 401));
        }
        return true;
    }

    public function handleRequest(WP_REST_Request $request) {
    $rawData = $request->get_body();
        $data = json_decode($rawData, true);
        if(!is_array($data)) {
            return new WP_REST_Response(['error' => 'invalid_payload'], 400);
        }

        if (empty($data['id']) && !empty($data['qrId'])) {
            $data['id'] = $data['qrId'];
        }
    if (empty($data['id'])) {
            return new WP_REST_Response(['error' => 'missing_id'], 400);
        }


        $updated = $this->database->updateTransaction($data);
        if (!$updated) {
            $this->database->storeTransaction($data, 0, $data['valueInCents'] ?? ($data['amountInCents'] ?? 0));
            error_log('[Depix][Webhook] Registro inserido pois não existia (tx='.$data['id'].').');
        } else {
            error_log('[Depix][Webhook] Registro atualizado (tx='.$data['id'].').');
        }

        $finals = ['paid','completed','confirmed','success','depix_sent','expired','canceled','error'];
        return new WP_REST_Response([
            'ok' => true,
            'final' => in_array($data['status'] ?? '', $finals, true),
        ], 200);
    }

}
