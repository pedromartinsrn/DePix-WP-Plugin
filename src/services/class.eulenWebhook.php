<?php

include_once ABSPATH . 'wp-load.php';

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
            'permission_callback' => '__return_true',
        ));
    }

    public function handleRequest(WP_REST_Request $request) {
        $rawData = $request->get_body();
        $data = json_decode($rawData, true);
        if(!is_array($data)) {
            return new WP_REST_Response(['error' => 'invalid_payload'], 400);
        }

        // Normaliza identificador: alguns webhooks enviam qrId em vez de id
        if (empty($data['id']) && !empty($data['qrId'])) {
            $data['id'] = $data['qrId'];
        }
        if (empty($data['id'])) {
            return new WP_REST_Response(['error' => 'missing_id'], 400);
        }

        // Log básico (pode ser removido em produção)
        error_log('[Depix][Webhook] Payload: ' . substr($rawData,0,1000));

        $token = EulenPanel::get_plain_token();
        if ($token) {
            $provided = $request->get_header('x-depix-signature');
            $calc = base64_encode(hash_hmac('sha256', $rawData, $token, true));
            if (!$provided || !hash_equals($calc, $provided)) {
                return new WP_REST_Response(['error' => 'invalid_signature'], 401);
            }
        }

        $updated = $this->database->updateTransaction($data);
        if (!$updated) {
            // Tenta inserção se não existir (caso webhook chegue antes do registro local)
            $this->database->storeTransaction($data, 0, $data['valueInCents'] ?? ($data['amountInCents'] ?? 0));
            $updated = true;
        }

        return new WP_REST_Response([
            'ok' => (bool)$updated,
        ], 200);
    }

}
