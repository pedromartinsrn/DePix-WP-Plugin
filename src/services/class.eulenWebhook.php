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
        
    $debugActive = defined('DEPIX_WEBHOOK_DEBUG') && constant('DEPIX_WEBHOOK_DEBUG');
    $secret = null;
    $secretSource = '';

        if (defined('DEPIX_WEBHOOK_SECRET')) {
            $val = constant('DEPIX_WEBHOOK_SECRET');
            if (is_string($val) && $val !== '') {
                if (class_exists('EulenPanel')) {
                    $plain = EulenPanel::extract_plain_token_from_option_value($val);
                    if (is_string($plain) && $plain !== '') { $secret = $plain; }
                }
                if (!$secret) { $secret = $val; }
                $secretSource = 'CONST';
            }
        }
        
        if (!$secret && class_exists('EulenPanel')) {
            $plain = EulenPanel::get_plain_webhook_secret();
            if (is_string($plain) && $plain !== '') { $secret = $plain; $secretSource = 'OPTION'; }
        }

    if ($secret) {
            $orig = $secret;
            $changed = false;
            
            if (preg_match('/^Basic\s+/i', $secret)) {
                $secret = trim(substr($secret, 6));
                $changed = true;
            }

            $b64dec = base64_decode($secret, true);
            if (is_string($b64dec) && strpos($b64dec, 'partner:') === 0) {
                $secret = substr($b64dec, 8);
                $changed = true;
            }

            $jsonScalar = json_decode($secret, true);
            if (is_string($jsonScalar) && $jsonScalar !== '') {
                $secret = $jsonScalar;
                $changed = true;
            }

            if (is_string($secret) && strpos($secret, '{') === 0 && class_exists('EulenPanel')) {
                $again = EulenPanel::extract_plain_token_from_option_value($secret);
                if (is_string($again) && $again !== '') {
                    $secret = $again;
                    $changed = true;
                }
            }
            $secret = trim((string)$secret);
            if ($changed && $debugActive) {
                error_log('[Depix][Webhook][debug] secret source=' . ($secretSource ?: 'unknown') . ' normalized from=' . strlen((string)$orig) . ' to=' . strlen((string)$secret));
            } elseif ($debugActive && $secretSource) {
                error_log('[Depix][Webhook][debug] secret source=' . $secretSource . ' len=' . strlen((string)$secret));
            }
        }

        if (!$secret) {
            return new WP_Error('depix_webhook_unconfigured', 'webhook secret missing', array('status' => 503));
        }

        // Opcional: logar secret em claro e base64('partner:secret') SOMENTE se explicitamente autorizado
    if ($debugActive && defined('DEPIX_ALLOW_SECRET_LOG') && constant('DEPIX_ALLOW_SECRET_LOG')) {
            error_log('[Depix][Webhook][secret][plain] ' . $secret);
            error_log('[Depix][Webhook][secret][b64_partner] ' . base64_encode('partner:' . $secret));
        }


    $auth = '';
        $authSrc = '';
        if (is_object($request) && method_exists($request, 'get_header')) {
            $auth = trim((string) $request->get_header('authorization'));
            if ($auth !== '') { $authSrc = 'request:get_header'; }
        }
    if ($auth === '' && isset($_SERVER['HTTP_AUTHORIZATION'])) {
            $auth = trim((string) $_SERVER['HTTP_AUTHORIZATION']);
            if ($auth !== '') { $authSrc = 'SERVER:HTTP_AUTHORIZATION'; }
        }
        if ($auth === '' && isset($_SERVER['REDIRECT_HTTP_AUTHORIZATION'])) {
            $auth = trim((string) $_SERVER['REDIRECT_HTTP_AUTHORIZATION']);
            if ($auth !== '') { $authSrc = 'SERVER:REDIRECT_HTTP_AUTHORIZATION'; }
        }
        if ($auth === '' && function_exists('getallheaders')) {
            $all = getallheaders();
            if (is_array($all)) {
                if (isset($all['Authorization']) && trim($all['Authorization']) !== '') {
                    $auth = trim($all['Authorization']); $authSrc = 'getallheaders:Authorization';
                } elseif (isset($all['authorization']) && trim($all['authorization']) !== '') {
                    $auth = trim($all['authorization']); $authSrc = 'getallheaders:authorization';
                }
            }
        }

        $valid = false;
        $allowLegacy = defined('DEPIX_WEBHOOK_ALLOW_LEGACY') && constant('DEPIX_WEBHOOK_ALLOW_LEGACY');
        if ($auth !== '' && stripos($auth, 'Basic ') === 0) {
            $token = trim(substr($auth, 6));
            $decoded = base64_decode($token, true);
            if ($debugActive) {
                $tokShow = $token;
                if (strlen($tokShow) > 256) { $tokShow = substr($tokShow, 0, 256) . '…'; }
                error_log('[Depix][Webhook][debug] basic_token_b64=' . $tokShow);
            }
            if ($decoded !== false && $decoded !== '') {
                // Normalizar quebras de linha e espaços ao final vindos de emissores
                $decodedNorm = rtrim((string)$decoded, "\r\n\t ");
                if ($debugActive) {
                    $hex = bin2hex(substr($decodedNorm, 0, 64));
                    error_log('[Depix][Webhook][debug] decoded_hex_prefix=' . $hex . ' decoded_len=' . strlen((string)$decodedNorm));
                }
                // Comparar pelos componentes para robustez
                $colonPos = strpos($decodedNorm, ':');
                if ($colonPos !== false) {
                    if ($debugActive) { error_log('[Depix][Webhook][debug] colon_pos=' . $colonPos); }
            $user = trim(substr($decodedNorm, 0, $colonPos));
            $pwd  = substr($decodedNorm, $colonPos + 1);
            if ($debugActive) {
                        $pwdLen = strlen((string)$pwd);
                        error_log('[Depix][Webhook][debug] auth src=' . ($authSrc ?: 'n/a') . ' user=' . $user . ' pwd_len=' . $pwdLen . ' secret_len=' . strlen((string)$secret));
                        $userHex = bin2hex(substr($user, 0, 32));
                        $pwdHex  = bin2hex(substr($pwd, 0, 32));
                        error_log('[Depix][Webhook][debug] user_hex_prefix=' . $userHex . ' pwd_hex_prefix=' . $pwdHex);
                    }
                    if (strtolower($user) === 'partner') {
                        if (function_exists('hash_equals')) {
                            $valid = hash_equals($secret, $pwd);
                        } else {
                            $valid = ($secret === $pwd);
                        }
                    } elseif ($allowLegacy) {
                        // Compat: aceitar qualquer usuário desde que a senha corresponda ao secret
                        if (function_exists('hash_equals')) {
                            $valid = hash_equals($secret, $pwd);
                        } else {
                            $valid = ($secret === $pwd);
                        }
                    }
                }
                // Compat extra: aceitar quando o decodificado for exatamente o secret (sem username)
                if (!$valid && $allowLegacy) {
                    if ($decodedNorm === $secret || $decodedNorm === (':' . $secret) || $decodedNorm === ($secret . ':')) {
                        $valid = true;
                    }
                }
            }
            // Compat: aceitar literal "Basic <secret>"
            if (!$valid && $allowLegacy) {
                if ($auth === ('Basic ' . $secret)) { $valid = true; }
            }
            // Compat: aceitar quando o emissor envia Base64(secret) ao invés de Base64('partner:secret')
            if (!$valid && $allowLegacy) {
                if ($token === base64_encode($secret) || $token === base64_encode(':' . $secret) || $token === base64_encode($secret . ':')) {
                    $valid = true;
                }
            }
            // Dica: token parece ser o secret direto, mas modo compat desligado
            if (!$valid && !$allowLegacy && $debugActive) {
                if ($token === $secret) {
                    error_log('[Depix][Webhook][hint] Emissor enviou "Basic <secret>". Ative DEPIX_WEBHOOK_ALLOW_LEGACY temporariamente ou ajuste para Basic base64("partner:[secret]").');
                }
            }
        }

        if (!$valid) {
            error_log('[Depix][Webhook] invalid Authorization format. Expected Basic base64("partner:[secret]")');
            return new WP_Error('depix_webhook_forbidden', 'invalid signature', array('status' => 401));
        }
        if ($allowLegacy && $debugActive) {
            error_log('[Depix][Webhook][compat] legacy Authorization accepted');
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
