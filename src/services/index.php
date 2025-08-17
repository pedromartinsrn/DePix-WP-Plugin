<?php
if (!defined('ABSPATH')) { exit; }

if (!function_exists('depix_is_valid_liquid_address')) {
    function depix_is_valid_liquid_address($address) {
        $addr = is_string($address) ? trim($address) : '';
        if ($addr === '') { return false; }

        $low = strtolower($addr);
        if (preg_match('/^lq1[023456789ac-hj-np-z]{20,}$/', $low)) {
            return true;
        }

        if (preg_match('/^[123456789ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnopqrstuvwxyz]{50,}$/', $addr)) {
            return true;
        }
        return false;
    }
}

add_action('rest_api_init', function () {
    register_rest_route('depix/v1', '/deposit', array(
        'methods'  => 'POST',
        'permission_callback' => '__return_true', // TODO: trocar por verificação de nonce
        'callback' => function (WP_REST_Request $req) {
            $data = json_decode($req->get_body(), true);
            $amount = isset($data['amountInCents']) ? (int)$data['amountInCents'] : 0;
            $address = isset($data['liquidAddress']) ? trim((string)$data['liquidAddress']) : '';
            $asset   = isset($data['asset']) ? (string)$data['asset'] : 'DePix';
            $meta    = (isset($data['metadata']) && is_array($data['metadata'])) ? $data['metadata'] : array();

            if ($amount <= 0) {
                return new WP_REST_Response(array('error' => 'invalid_amount'), 400);
            }
            if ($address === '') {
                return new WP_REST_Response(array('error' => 'missing_liquid_address'), 400);
            }

            $service = new EulenService();
            $resp = $service->deposit($amount, array(
                'liquidAddress' => $address,
                'asset' => $asset,
                'metadata' => $meta,
            ));

            if (is_wp_error($resp)) {
                return new WP_REST_Response(array('error' => $resp->get_error_message()), 500);
            }

            $body = is_array($resp) ? ($resp['body'] ?? '') : $resp;
            $json = json_decode($body, true);
            return new WP_REST_Response(array(
                'ok' => true,
                'id' => isset($json['response']['id']) ? $json['response']['id'] : null,
                'qrCopyPaste' => isset($json['response']['qrCopyPaste']) ? $json['response']['qrCopyPaste'] : null,
                'qrImageUrl'  => isset($json['response']['qrImageUrl']) ? $json['response']['qrImageUrl'] : null,
                'raw' => $json,
            ), 200);
        }
    ));
});

add_action('init', function () {
    add_rewrite_tag('%depix_api%', '([^&]+)');
    add_rewrite_rule('^api/pix/start/?$', 'index.php?depix_api=pix_start', 'top');
    add_rewrite_rule('^api/pix/status/?$', 'index.php?depix_api=pix_status', 'top');
});

add_filter('query_vars', function ($vars) {
    $vars[] = 'depix_api';
    return $vars;
});

add_action('template_redirect', function () {
    $action = get_query_var('depix_api');

    if (!$action && isset($_SERVER['REQUEST_URI'])) {
        $uri = $_SERVER['REQUEST_URI'];
        if (preg_match('~/(?:index\.php/)?api/pix/start/?$~', $uri)) {
            $action = 'pix_start';
        } elseif (preg_match('~/(?:index\.php/)?api/pix/status/?$~', $uri)) {
            $action = 'pix_status';
        }
    }
    if (!$action) { return; }

    nocache_headers();
    header('Content-Type: application/json; charset=utf-8');

    if ($action === 'pix_start' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        global $wp_query; if (isset($wp_query)) { $wp_query->is_404 = false; }

        $ct = isset($_SERVER['CONTENT_TYPE']) ? strtolower((string) $_SERVER['CONTENT_TYPE']) : '';
        if ($ct === '' && function_exists('getallheaders')) {
            $h = getallheaders(); $ct = strtolower((string)($h['Content-Type'] ?? $h['content-type'] ?? ''));
        }
        if (strpos($ct, 'application/json') === false) {
            status_header(400); echo wp_json_encode(array('error' => 'invalid_content_type')); exit;
        }

        $ip = isset($_SERVER['HTTP_X_FORWARDED_FOR']) ? explode(',', $_SERVER['HTTP_X_FORWARDED_FOR'])[0] : ($_SERVER['REMOTE_ADDR'] ?? '');
        $ip = trim((string)$ip);
        if ($ip !== '') {
            $rl_key = 'depix_rl_' . md5($ip);
            if (get_transient($rl_key)) { status_header(429); echo wp_json_encode(array('error' => 'rate_limited')); exit; }
            set_transient($rl_key, 1, 3);
        }
        $raw = file_get_contents('php://input');
        $data = json_decode($raw, true);
        $amountBRL = isset($data['amountBRL']) ? (float)$data['amountBRL'] : 0.0;
        $network   = isset($data['network']) ? (string)$data['network'] : '';
        $wallet    = isset($data['wallet']) ? trim((string)$data['wallet']) : '';
        if ($amountBRL <= 0) { status_header(400); echo wp_json_encode(array('error' => 'invalid_amount_brl')); exit; }

        $minBrl = 5; $maxBrl = 20000;
        if ($amountBRL < $minBrl) { status_header(400); echo wp_json_encode(array('error' => 'amount_below_min', 'min' => $minBrl)); exit; }
        if ($amountBRL > $maxBrl) { status_header(400); echo wp_json_encode(array('error' => 'amount_above_max', 'max' => $maxBrl)); exit; }
        $cents = (int) round($amountBRL * 100);
        $opts = array('asset' => 'DePix');
        if ($network === 'liquid' && $wallet !== '') {
            if (!depix_is_valid_liquid_address($wallet)) {
                status_header(400);
                echo wp_json_encode(array('error' => 'invalid_liquid_address'));
                exit;
            }

            $opts['depixAddress'] = $wallet;
        }
        $service = new EulenService();
        $resp = $service->deposit($cents, $opts);
        if (is_wp_error($resp)) { status_header(500); echo wp_json_encode(array('error' => $resp->get_error_message())); exit; }
        $body = is_array($resp) ? ($resp['body'] ?? '') : $resp;
        $json = json_decode($body, true);
        status_header(200);
        echo wp_json_encode(array(
            'txId' => isset($json['response']['id']) ? $json['response']['id'] : null,
            'brcode' => isset($json['response']['qrCopyPaste']) ? $json['response']['qrCopyPaste'] : null,
            'qrImageUrl' => isset($json['response']['qrImageUrl']) ? $json['response']['qrImageUrl'] : null,
        ));
        exit;
    }

    if ($action === 'pix_status') {
        global $wp_query; if (isset($wp_query)) { $wp_query->is_404 = false; }
        $txId = isset($_GET['txId']) ? sanitize_text_field($_GET['txId']) : '';
        if (!$txId) { status_header(400); echo wp_json_encode(array('error' => 'missing_txId')); exit; }
        $db = new DepixTablesWP();
        $status = $db->getTransactionStatus($txId);
        status_header(200);
        echo wp_json_encode(array('status' => $status ?: null));
        exit;
    }
});
