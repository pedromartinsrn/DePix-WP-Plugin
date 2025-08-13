<?php
if (!defined('ABSPATH')) { exit; }

// For example and debugging purpose only
class DepixShortcodes {

	public static function init(): void
	{
		add_shortcode('depix_test', [__CLASS__, 'render_test']);
		add_action('wp_ajax_depix_tx_status', [__CLASS__, 'ajax_tx_status']);
		add_action('wp_ajax_nopriv_depix_tx_status', [__CLASS__, 'ajax_tx_status']);
	}

	public static function render_test($atts = [], $content = ''): string
	{
		if (!class_exists('EulenService')) {
			return '<p>Dependências não carregadas.</p>';
		}

		$out = '';
		$created = null;
		$error = '';

		if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['depix_amount']) && isset($_POST['depix_nonce'])) {
			if (!wp_verify_nonce(sanitize_text_field($_POST['depix_nonce']), 'depix_deposit')) {
				$error = 'Nonce inválido.';
			} else {
				$amount_raw = sanitize_text_field($_POST['depix_amount']);
				$normalized = str_replace(['.', ','], ['', '.'], preg_replace('/[^0-9,\.]/', '', $amount_raw));
				if (!is_numeric($normalized)) {
					$error = 'Valor inválido.';
				} else {
					$value_float = (float)$normalized;
					if ($value_float <= 0) {
						$error = 'Valor deve ser maior que zero.';
					} else {
						$amount_cents = (int) round($value_float * 100);
						$service = new EulenService();
						$resp = $service->deposit($amount_cents);
						if (is_wp_error($resp)) {
							$error = 'Falha ao criar depósito.';
						} else {
							$json = json_decode($resp, true);
							if (isset($json['response']['id'])) {
								$created = [
									'id' => sanitize_text_field($json['response']['id']),
									'qrCopyPaste' => $json['response']['qrCopyPaste'] ?? '',
									'qrImageUrl' => esc_url($json['response']['qrImageUrl'] ?? ''),
									'amount_cents' => $amount_cents,
								];
							} else {
								$error = 'Resposta inesperada da API.';
                                error_log('Depix deposit API response: ' . $resp);
							}
						}
					}
				}
			}
		}

		$out .= '<div class="depix-test-wrapper">';
		$out .= '<h3>Teste Depix</h3>';
		if ($error) {
			$out .= '<div class="depix-error" style="color:#b00">' . esc_html($error) . '</div>';
		}
		if (!$created) {
			$out .= '<form method="post" class="depix-form" style="margin:1em 0">';
			$out .= '<label>Valor (R$): <input type="text" name="depix_amount" required pattern="^[0-9]+([\.,][0-9]{1,2})?$" placeholder="10,00" /></label> ';
			$out .= wp_nonce_field('depix_deposit', 'depix_nonce', true, false);
			$out .= '<button type="submit">Gerar PIX</button>';
			$out .= '</form>';
		}

		if ($created) {
			$amount_fmt = number_format($created['amount_cents'] / 100, 2, ',', '.');
			$out .= '<div class="depix-transaction" data-depix-status data-tx-id="' . esc_attr($created['id']) . '">';
			$out .= '<p><strong>Transação:</strong> ' . esc_html($created['id']) . '</p>';
			$out .= '<p><strong>Valor:</strong> R$ ' . esc_html($amount_fmt) . '</p>';
			if ($created['qrImageUrl']) {
				$out .= '<div><img alt="QR Code" src="' . $created['qrImageUrl'] . '" style="max-width:260px;border:1px solid #ccc;padding:4px;background:#fff" /></div>';
			}
			if (!empty($created['qrCopyPaste'])) {
				$out .= '<p><small>Copia e Cola:</small><br><textarea readonly style="width:100%;height:90px;">' . esc_textarea($created['qrCopyPaste']) . '</textarea></p>';
			}
			$out .= '<p>Status: <span class="depix-status-text">aguardando...</span></p>';
			$out .= '</div>';
			$ajax_url = esc_url(admin_url('admin-ajax.php'));
			$out .= '<script>(function(){var box=document.querySelector("[data-depix-status]");if(!box)return;var tx=box.getAttribute("data-tx-id");var stEl=box.querySelector(".depix-status-text");var finals={paid:1,completed:1,confirmed:1,success:1};function poll(){fetch("'.$ajax_url.'?action=depix_tx_status&tx_id="+encodeURIComponent(tx)).then(r=>r.json()).then(function(j){if(j.status){stEl.textContent=j.status;if(!j.final){setTimeout(poll,3000);} else {stEl.style.color="#060";} } else {setTimeout(poll,4000);} }).catch(function(){setTimeout(poll,5000);});}poll();})();</script>';
		}
		$out .= '</div>';
		return $out;
	}

	public static function ajax_tx_status(): void
	{
		$tx_id = isset($_GET['tx_id']) ? sanitize_text_field($_GET['tx_id']) : '';
		if (!$tx_id) {
			wp_send_json(['error' => 'missing_tx_id'], 400);
		}
		$db = new DepixTablesWP();
		$status = $db->getTransactionStatus($tx_id);
		if (!$status) {
			wp_send_json(['status' => null]);
		}
		$finals = ['paid','completed','confirmed','success', 'depix_sent'];
		wp_send_json([
			'status' => $status,
			'final' => in_array($status, $finals, true),
		]);
	}
}

