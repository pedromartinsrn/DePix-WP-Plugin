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
        // Verifica o header Authorization: Basic <secret>
        $secret = null;
        if (defined('DEPIX_WEBHOOK_SECRET') && is_string(DEPIX_WEBHOOK_SECRET) && DEPIX_WEBHOOK_SECRET !== '') {
            $secret = DEPIX_WEBHOOK_SECRET;
        }
        if (!$secret) {
            $opt = get_option('depix_webhook_secret_enc_v1', '');
            if (is_string($opt) && $opt !== '') {
                $plain = EulenPanel::extract_plain_token_from_option_value($opt);
                if (is_string($plain) && $plain !== '') {
                    $secret = $plain;
                }
            }
        }

        // Sem secret configurado: aceitar provisoriamente, mas alertar em log
        if (!$secret) {
            error_log('[Depix][Webhook] Nenhum secret configurado (DEPIX_WEBHOOK_SECRET ou option depix_webhook_secret). Aceitando provisoriamente.');
            return true;
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
            return new WP_Error('depix_webhook_forbidden', 'invalid signature', array('status' => 403));
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
