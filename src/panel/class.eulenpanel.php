<?php

if (!defined('ABSPATH')) { exit; }

if (!class_exists('EulenPanel')) {
    class EulenPanel
    {
        const OPTION_NAME = 'depix_plugin_api_token_enc_v1';
        const OPTION_WEBHOOK_SECRET = 'depix_webhook_secret_enc_v1';

        public function init(): void
        {
            add_action('admin_menu', [$this, 'register_menu']);

            add_action('admin_init', [$this, 'register_settings']);
        }

        public function register_menu(): void
        {


            $icon_url = '';
            add_menu_page(
                __('DePix', 'depixplugin'),
                __('DePix', 'depixplugin'),
                'manage_options',
                'depix-settings',
                [$this, 'render_page'],
                $icon_url,
                56
            );

            add_action('admin_head', function(){
                echo '<style>


                </style>';
            });

            add_submenu_page(
                'depix-settings',
                __('Configurações', 'depixplugin'),
                __('Configurações', 'depixplugin'),
                'manage_options',
                'depix-settings',
                [$this, 'render_page']
            );
        }


        public function register_settings(): void
        {
            register_setting(
                'depix_settings_group',
                self::OPTION_NAME,
                [
                    'sanitize_callback' => [$this, 'sanitize_encrypted_token'],
                    'type'              => 'string',
                    'show_in_rest'      => false,
                ]
            );

            register_setting(
                'depix_settings_group',
                self::OPTION_WEBHOOK_SECRET,
                [
                    'sanitize_callback' => [$this, 'sanitize_encrypted_secret'],
                    'type'              => 'string',
                    'show_in_rest'      => false,
                ]
            );

            add_settings_section(
                'depix_main_section',
                __('Configurações de API', 'depixplugin'),
                function () {
                    echo '<p>' . esc_html__('Configure o token de acesso à API Eulen/DePix.', 'depixplugin') . '</p>';
                },
                'depix-settings'
            );

            add_settings_field(
                'depix_api_token_field',
                __('API Token', 'depixplugin'),
                [$this, 'field_api_token'],
                'depix-settings',
                'depix_main_section'
            );

            add_settings_field(
                'depix_webhook_secret_field',
                __('Webhook Secret', 'depixplugin'),
                [$this, 'field_webhook_secret'],
                'depix-settings',
                'depix_main_section'
            );
        }


        public function field_api_token(): void
        {
            $encrypted = get_option(self::OPTION_NAME, '');
            $has_token = !empty($encrypted);
            echo '<input type="password" autocomplete="new-password" style="width:350px" name="' . esc_attr(self::OPTION_NAME) . '" value="" placeholder="' . esc_attr($has_token ? __('•••••••• (já salvo)', 'depixplugin') : __('Cole seu token aqui', 'depixplugin')) . '" />';
            echo '<p class="description">' . esc_html__('Ao salvar, o token é criptografado usando as WP salts. Deixe vazio para manter o token atual.', 'depixplugin') . '</p>';
        }

        public function field_webhook_secret(): void
        {
            $encrypted = get_option(self::OPTION_WEBHOOK_SECRET, '');
            $has_secret = !empty($encrypted);
            echo '<input type="password" autocomplete="new-password" style="width:350px" name="' . esc_attr(self::OPTION_WEBHOOK_SECRET) . '" value="" placeholder="' . esc_attr($has_secret ? __('•••••••• (já salvo)', 'depixplugin') : __('Cole seu webhook secret aqui', 'depixplugin')) . '" />';
            echo '<p class="description">' . esc_html__('Salvamos o webhook secret criptografado (AES‑256‑GCM) usando as WP salts. Deixe vazio para manter o secret atual.', 'depixplugin') . '</p>';
        }


        public function sanitize_encrypted_token($raw)
        {
            if ($raw === null || $raw === '') {
                return get_option(self::OPTION_NAME, '');
            }

            $raw = trim($raw);
            if ($raw === '') {
                return get_option(self::OPTION_NAME, '');
            }

            $encrypted = $this->encrypt($raw);
            if (!$encrypted) {
                add_settings_error(self::OPTION_NAME, 'depix_encrypt_fail', __('Falha ao criptografar token (extensão OpenSSL ausente?).', 'depixplugin'));
                return get_option(self::OPTION_NAME, '');
            }
            return wp_json_encode($encrypted);
        }

        public function sanitize_encrypted_secret($raw)
        {
            if ($raw === null || $raw === '') {
                return get_option(self::OPTION_WEBHOOK_SECRET, '');
            }

            $raw = trim($raw);
            if ($raw === '') {
                return get_option(self::OPTION_WEBHOOK_SECRET, '');
            }

            $encrypted = $this->encrypt($raw);
            if (!$encrypted) {
                add_settings_error(self::OPTION_WEBHOOK_SECRET, 'depix_webhook_encrypt_fail', __('Falha ao criptografar webhook secret (OpenSSL ausente?).', 'depixplugin'));
                return get_option(self::OPTION_WEBHOOK_SECRET, '');
            }
            return wp_json_encode($encrypted);
        }

        public function render_page(): void
        {
            if (!current_user_can('manage_options')) {
                wp_die(__('Sem permissão.', 'depixplugin'));
            }
           if (
                isset($_POST['depix_action'], $_POST['depix_ping_nonce']) &&
                $_POST['depix_action'] === 'ping' &&
                wp_verify_nonce(sanitize_text_field($_POST['depix_ping_nonce']), 'depix_ping')
            ) {
                if (!class_exists('EulenService')) {
                    add_settings_error('depix_ping', 'depix_ping_no_service', __('Serviço da API não carregado.', 'depixplugin'), 'error');
                } else {
                    $service = new EulenService();
                    $resp = $service->ping();
                    if (is_wp_error($resp)) {
                        add_settings_error(
                            'depix_ping',
                            'depix_ping_err',
                            sprintf(__('Ping falhou: %s', 'depixplugin'), esc_html($resp->get_error_message())),
                            'error'
                        );
                    } else {
                        $code = (int) wp_remote_retrieve_response_code($resp);
                        $body_raw = (string) wp_remote_retrieve_body($resp);
                        $body_trim = mb_substr(trim($body_raw), 0, 220);
                        if (mb_strlen($body_raw) > 220) {
                            $body_trim .= '…';
                        }
                        
                        $headers = wp_remote_retrieve_headers($resp);
                        
                        if ($code >= 200 && $code < 300) {
                            add_settings_error(
                                'depix_ping',
                                'depix_ping_ok',
                                sprintf(
                                    __('Ping OK (HTTP %d). Trecho da resposta: %s', 'depixplugin'),
                                    $code,
                                    esc_html($body_trim === '' ? '[vazio]' : $body_trim)
                                ),
                                'updated'
                            );
                        } else {
                            add_settings_error(
                                'depix_ping',
                                'depix_ping_fail',
                                sprintf(
                                    __('Ping falhou (HTTP %d). Corpo: %s', 'depixplugin'),
                                    $code,
                                    esc_html($body_trim === '' ? '[vazio]' : $body_trim)
                                ),
                                'error'
                            );
                        }
                    }
                }
            } elseif (isset($_POST['depix_action']) && $_POST['depix_action'] === 'ping') {
                add_settings_error('depix_ping', 'depix_ping_nonce_fail', __('Nonce inválido no Ping.', 'depixplugin'), 'error');
            }

            echo '<div class="wrap">';
            echo '<h1>' . esc_html__('Depix', 'depixplugin') . '</h1>';

            $active_tab = isset($_GET['tab']) ? sanitize_text_field($_GET['tab']) : 'settings';
            $settings_url = esc_url(admin_url('admin.php?page=depix-settings&tab=settings'));
            $arch_url     = esc_url(admin_url('admin.php?page=depix-settings&tab=arch'));
            echo '<h2 class="nav-tab-wrapper">';
            echo '<a href="' . $settings_url . '" class="nav-tab ' . ($active_tab==='settings'?'nav-tab-active':'') . '">' . esc_html__('Configurações', 'depixplugin') . '</a>';
            echo '<a href="' . $arch_url . '" class="nav-tab ' . ($active_tab==='arch'?'nav-tab-active':'') . '">' . esc_html__('Arquitetura', 'depixplugin') . '</a>';
            echo '</h2>';

            settings_errors();

            if ($active_tab === 'settings') {
                echo '<form method="post" action="options.php">';
                settings_fields('depix_settings_group');
                do_settings_sections('depix-settings');
                submit_button();
                echo '</form>';

                echo '<hr />';
                echo '<h2>' . esc_html__('Status', 'depixplugin') . '</h2>';
                echo '<p>' . esc_html__('Token presente: ', 'depixplugin') . '<strong>' . (self::has_token_saved() ? __('Sim', 'depixplugin') : __('Não', 'depixplugin')) . '</strong></p>';
                $secret_present = !empty(get_option(self::OPTION_WEBHOOK_SECRET, ''));
                echo '<p>' . esc_html__('Webhook secret presente: ', 'depixplugin') . '<strong>' . ($secret_present ? __('Sim', 'depixplugin') : __('Não', 'depixplugin')) . '</strong></p>';
                echo '<p><strong>' . esc_html__('Registre no BOT Telegram da Eulen este webhook:', 'depixplugin') . '</strong> <code>' . esc_html(rest_url('depix/v1/webhook')) . '</code></p>';

                echo '<h2>' . esc_html__('Teste de Conectividade', 'depixplugin') . '</h2>';
                echo '<form method="post">';
                wp_nonce_field('depix_ping', 'depix_ping_nonce');
                echo '<input type="hidden" name="depix_action" value="ping" />';
                echo '<p><button type="submit" class="button button-secondary">' . esc_html__('Fazer Ping na API', 'depixplugin') . '</button></p>';
                echo '<p class="description">' . esc_html__('Executa uma chamada /ping para verificar conectividade e autenticação.', 'depixplugin') . '</p>';
                echo '</form>';
            } else {

                $mmd_path = DEPIXPLUGIN_PLUGIN_DIR . 'docs/architecture.mmd';
                $mmd = '';
                if (is_readable($mmd_path)) {
                    $mmd = file_get_contents($mmd_path);
                } else {
                    $mmd = "graph TD; A[Arquivo docs/architecture.mmd não encontrado];";
                }
                echo '<div class="wrap">';
                echo '<p class="description">' . esc_html__('Diagrama renderizado a partir de docs/architecture.mmd.', 'depixplugin') . '</p>';
                echo '<div class="mermaid" style="background:#fff;padding:12px;border:1px solid #ddd;overflow:auto;">' . "\n" . $mmd . "\n" . '</div>';
                echo '<p><small>' . esc_html__('Edite docs/architecture.mmd e recarregue para atualizar.', 'depixplugin') . '</small></p>';
                echo '<script src="https://cdn.jsdelivr.net/npm/mermaid@10/dist/mermaid.min.js"></script>';
                echo '<script>mermaid.initialize({startOnLoad:true});</script>';
                echo '</div>';
            }

            echo '</div>';
        }


        public static function has_token_saved(): bool
        {
            $opt = get_option(self::OPTION_NAME, '');
            return !empty($opt);
        }

        public static function get_plain_token(): ?string
        {
            $opt = get_option(self::OPTION_NAME, '');
            return self::extract_plain_token_from_option_value($opt);
        }

 
        public static function extract_plain_token_from_option_value($raw): ?string
        {
            if (empty($raw) || !is_string($raw)) {
                return null;
            }

            if (substr_count($raw, '.') === 2 && strpos($raw, '{') === false) {
                return trim($raw);
            }
            $data = json_decode($raw, true);

            if (is_string($data) && str_starts_with($data, '{')) {
                $data = json_decode($data, true);
            }
            if (!is_array($data) || !isset($data['alg'], $data['ct'])) {
                return null;
            }
            $plain = self::static_decrypt_struct($data);
        
            if (is_string($plain) && trim($plain) !== '') {
                return trim($plain);
            }
            return null;
        }

        public static function static_decrypt_struct(array $data): ?string
        {
            if (($data['alg'] ?? '') !== 'aes-256-gcm') {
                return null;
            }
            foreach (['iv','ct','tag'] as $k) {
                if (empty($data[$k])) return null;
            }
            if (!function_exists('openssl_decrypt')) {
                return null;
            }
            $iv  = base64_decode($data['iv'], true);
            $ct  = base64_decode($data['ct'], true);
            $tag = base64_decode($data['tag'], true);
            if ($iv === false || $ct === false || $tag === false) {
                return null;
            }
            $key = self::derive_key_static();
            $pt = openssl_decrypt($ct, 'aes-256-gcm', $key, OPENSSL_RAW_DATA, $iv, $tag);
            return $pt === false ? null : $pt;
        }

        private static function derive_key_static(): string
        {
            $material_parts = [];
            foreach ([ 'AUTH_KEY','SECURE_AUTH_KEY','LOGGED_IN_KEY','NONCE_KEY','AUTH_SALT','SECURE_AUTH_SALT','LOGGED_IN_SALT','NONCE_SALT'] as $c) {
                if (defined($c)) {
                    $material_parts[] = constant($c);
                }
            }
            $material = implode('|', $material_parts) . '|depix-plugin|v1';
            $prk = hash_hmac('sha256', $material, 'depix-hkdf-salt', true);
            $okm = hash_hmac('sha256', $prk . "depix-info", $prk, true);
            return substr($okm, 0, 32);
        }

        private function encrypt(string $plaintext): ?array
        {
            if (!function_exists('openssl_encrypt')) {
                return null;
            }
            $cipher = 'aes-256-gcm';
            if (!in_array($cipher, openssl_get_cipher_methods(), true)) {
                return null;
            }
            $key = $this->derive_key();
            $iv_len = openssl_cipher_iv_length($cipher);
            $iv = random_bytes($iv_len);
            $tag = '';
            $ct = openssl_encrypt($plaintext, $cipher, $key, OPENSSL_RAW_DATA, $iv, $tag, '', 16);
            if ($ct === false || $tag === '') {
                return null;
            }
            return [
                'v' => 1,
                'alg' => $cipher,
                'iv' => base64_encode($iv),
                'tag' => base64_encode($tag),
                'ct' => base64_encode($ct),
            ];
        }


        private function decrypt(array $data): ?string
        {
            if (!function_exists('openssl_decrypt')) {
                return null;
            }
            if (($data['alg'] ?? '') !== 'aes-256-gcm') {
                return null;
            }
            if (empty($data['iv']) || empty($data['ct']) || empty($data['tag'])) {
                return null;
            }
            $iv  = base64_decode($data['iv'], true);
            $ct  = base64_decode($data['ct'], true);
            $tag = base64_decode($data['tag'], true);
            if ($iv === false || $ct === false || $tag === false) {
                return null;
            }
            $key = $this->derive_key();
            $pt = openssl_decrypt($ct, 'aes-256-gcm', $key, OPENSSL_RAW_DATA, $iv, $tag);
            return $pt === false ? null : $pt;
        }

        private function derive_key(): string
        {
            $material_parts = [];
            foreach ([ 'AUTH_KEY','SECURE_AUTH_KEY','LOGGED_IN_KEY','NONCE_KEY','AUTH_SALT','SECURE_AUTH_SALT','LOGGED_IN_SALT','NONCE_SALT'] as $c) {
                if (defined($c)) {
                    $material_parts[] = constant($c);
                }
            }
            $material = implode('|', $material_parts) . '|depix-plugin|v1';
            $prk = hash_hmac('sha256', $material, 'depix-hkdf-salt', true);
            $okm = hash_hmac('sha256', $prk . "depix-info", $prk, true);
            return substr($okm, 0, 32);
        }
    }
}
