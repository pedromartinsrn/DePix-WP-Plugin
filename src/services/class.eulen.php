<?php 

if (!defined('ABSPATH')) { exit; }

require_once DEPIXPLUGIN_PLUGIN_DIR . 'src/helpers/class.requests.php';

class EulenService {
    public $helpers;
    public $token;
    public $database;
    
    private function ensureAuthToken(): void
    {
        if (empty($this->token)) {
            return;
        }
        
        if (preg_match('/^\{"v":\d+.*"ct":/s', $this->token)) {
            $decoded = json_decode($this->token, true);
            if (is_array($decoded) && isset($decoded['alg'],$decoded['ct'])) {
                $plain = EulenPanel::static_decrypt_struct($decoded);
                if (is_string($plain) && trim($plain)!=='') {
                    $this->token = trim($plain);
                } else {
                    error_log('[Depix][Auth][Err] Falha ao decriptar token cifrado.');
                    return;
                }
            } else {
                error_log('[Depix][Auth][Err] Estrutura cifrada inválida ao tentar decriptar.');
                return;
            }
        }

        if (!preg_match('/^\{"v":\d+.*"ct":/s', $this->token)) {
            $this->helpers->setAuthToken($this->token);
        }
    }

    public function __construct() {
        $this->helpers = new EulenRequest();
        $this->token = EulenPanel::get_plain_token();
        if (empty($this->token)) {
            $raw = get_option(EulenPanel::OPTION_NAME, '');
            if (!empty($raw)) {
                error_log('[Depix][Token] Token não pôde ser decriptado (option presente).');
            } else {
                error_log('[Depix][Token] Nenhum token salvo.');
            }
        } 
        
    $this->ensureAuthToken();
        $this->database = new DepixTablesWP();
    }

    public function ping() {
        $url = $this->helpers->api_url . '/ping';
        if (!$url) {
            return new WP_Error('depix_no_base', 'API base não configurada');
        }
        $headers = [
            'Accept' => 'application/json',
        ];
        if (!empty($this->token)) {
            $this->ensureAuthToken();
        } else {
            error_log('[Depix][Ping] Sem token plano disponível; Authorization não enviado.');
        }
        
        $resp = $this->helpers->get('/ping', $headers);
        if (is_wp_error($resp)) {
            error_log('[Depix][Ping] WP_Error: '.$resp->get_error_message());
            return $resp;
        }
        
        return $resp;
    }

    public function deposit($amountInCents) {
        $this->ensureAuthToken();
        if (empty($this->token)) {
            return new WP_Error('depix_no_token', 'Token ausente para deposit');
        }
        $response = $this->helpers->post('/deposit', array(
            'amountInCents' => $amountInCents,
        ), array(
            'Content-Type' => 'application/json'
        ));

        if (is_wp_error($response)) {
            return new WP_Error('deposit_failed', 'Deposit request failed', array('status' => 500));
        }

        $body = is_array($response) ? ($response['body'] ?? '') : '';

        $json = json_decode($body, true);

        if (is_array($json) && isset($json['response']['id'])) {
            $this->database->storeTransaction($json['response'], (bool)($json['async'] ?? false), $amountInCents);
        }
        
        return $body;
    }

    public function depositStatus($transactionId) {
    $this->ensureAuthToken();
        $request = $this->helpers->get('/deposit-status', 
            array(
                'Content-Type' => 'application/json'
            ),
            array(
                'id' => $transactionId
            )
        );
        return $request;
    }
}
