<?php

if (!defined('ABSPATH')) { exit; }

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
