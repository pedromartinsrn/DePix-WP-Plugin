<?php
if (!defined('ABSPATH')) { exit; }

class DepixShortcodes {

	public static function init(): void
	{
		add_shortcode('depix_test', [__CLASS__, 'render_test']);
		add_shortcode('depix_checkout', [__CLASS__, 'render_checkout']);
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
                                $response_summary = isset($json['response']) ? json_encode(array_keys($json['response'])) : 'No response keys';
                                error_log('Depix deposit API unexpected response. Keys: ' . $response_summary);
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

	public static function render_checkout($atts = [], $content = ''): string
	{

		$plugin_file = DEPIXPLUGIN_PLUGIN_DIR . 'depixplugin.php';
		wp_enqueue_style('depix-checkout-css', plugins_url('assets/checkout/main.css', $plugin_file), [], null);
		wp_enqueue_script('depix-checkout-script', plugins_url('assets/checkout/script.js', $plugin_file), [], null, true);
		wp_enqueue_script('depix-checkout-search', plugins_url('assets/checkout/search.js', $plugin_file), [], null, true);
		wp_localize_script('depix-checkout-script', 'CFTheme', [
			'baseUrl' => home_url('/'),
			'configUrl' => plugins_url('assets/checkout/config.json', $plugin_file)
		]);

		ob_start();
		?>
		<div class="depix-root cf-mode-light">
		<!-- Templates necessários (copiados do tema) -->
		<template id="meeting-consent-template">
			<div class="info-box" style="margin-top: 40px; max-width: 500px; margin-left: auto; margin-right: auto;">
				<div style="text-align: center;">
					<p>Para prosseguir, precisaremos agendar uma reunião. É Feita pelo <strong>Meet</strong> (com câmera) e terá duração de <strong>até 30 minutos</strong>.</p>
				</div>
				<div class="checkbox-group" style="display: flex; justify-content: center; align-items: center; margin-top: 20px;">
					<input type="checkbox" id="consent-checkbox" name="meetingConsent" value="true" required>
					<label for="consent-checkbox">Li e concordo com os termos da reunião.</label>
				</div>
				<div class="error-message" id="consent-error" style="text-align: center;">Você precisa concordar com os termos para prosseguir.</div>
			</div>
		</template>

		<template id="user-info-template">
			<div style="max-width: 400px; margin: 0 auto;margin-top:20px;">
				<label for="fullName">Nome/Apelido*</label>
				<input type="text" id="fullName" name="fullName" required>
				<div class="error-message" id="fullName-error">Por favor, digite seu apelido.</div>

				<label for="email">E-mail*</label>
				<input type="email" id="email" name="email" required>
				<div class="error-message" id="email-error">Por favor, digite um e-mail válido.</div>

				<div class="input-with-icon">
					<label for="telegramUser">Telegram*</label>
					<div class="input-icon-group">
						<span class="input-icon">@</span>
						<input type="text" id="telegramUser" name="telegramUser" required>
					</div>
					<div class="error-message" id="telegramUser-error">Este campo é obrigatório para contato.</div>
				</div>

				<p class="input-hint">Principal meio de contato.</p>
			</div>
		</template>
		<div class="form-container">
		  <div class="progress-bar">
			<div class="progress"></div>
		  </div>

		  <form id="eulen-form">
			<!-- Estado persistente do fluxo -->
			<input type="hidden" name="state_asset" value="">
			<input type="hidden" name="state_category" value="">
			<input type="hidden" name="state_amount_brl" value="">
			<input type="hidden" name="state_amount_out" value="">
			<input type="hidden" name="state_network" value="">
			<input type="hidden" name="state_wallet" value="">
			<input type="hidden" name="state_profileType" value="">
			<!-- STEP 1: Welcome & Category -->
			<div class="form-step active" data-step="1">
			  <div class="step-content-wrapper">
				<h2>Bem-vindo à P2P.APP.BR</h2>
				<p class="category-prompt">O que você quer comprar?</p>
				<div style="margin-top: 50px" class="welcome-content">
				  <div class="category-selection">
					<div class="radio-group category-options">
					  <div class="option-row">
						<input type="radio" id="category-onboarding" name="category" value="onboarding" required autocomplete="off">
						<label for="category-onboarding" class="option-card">
						  <span class="btc-logo--lg" aria-hidden="true">₿</span>
						  <span class="crypto-name">Bitcoin</span>
						</label>
						<input type="radio" id="category-security" name="category" value="security" required autocomplete="off">
						<label for="category-security" class="option-card">
						  <span class="depix-logo--lg" aria-hidden="true">Đ</span>
						  <span class="crypto-name">DePix</span>
						</label>
					  </div>
					  
					  <!-- Step: Amount input (new) -->
					  
					</div>
					<div class="error-message" id="category-error">Por favor, selecione uma opção.</div>
				  </div>
				</div>
			  </div>
			  <div class="button-container">
				<!-- Botão Removido -->
			  </div>
			</div>

			<!-- STEP 2: Amount (BRL -> BTC) -->
			<div class="form-step" data-step="2" data-kind="amount">
			  <div class="step-content-wrapper">
				<h2>Quanto você quer comprar?</h2>
				<div class="step-state-chips"></div>
				<div class="amount-stack">
				  <label for="desiredAmountBRL">Você paga:</label>
				  <div class="amount-row">
					<span class="prefix currency-brl" id="amountPrefix">R$</span>
					<input id="desiredAmountBRL" name="desiredAmountBRL" type="text" inputmode="decimal" placeholder="0" autocomplete="off" required>
				  </div>

				  <label for="convertedAmountBTC">Você recebe:</label>
				  <div class="amount-row">
					<span class="prefix btc-logo--sm" id="convertedPrefix">₿</span>
					<input id="convertedAmountBTC" type="text" placeholder="0.00000000" readonly disabled tabindex="-1" aria-readonly="true" aria-disabled="true">
					<span class="suffix" id="convertedSuffix">BTC</span>
				  </div>
				  <div class="amount-fees">Taxas: R$1 (transação) + 5%</div>
				</div>
			  </div>
			  <div class="button-container">
				<button type="button" class="prev-btn">
				  <svg viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M15.5 19l-7-7 7-7" stroke-linecap="round" stroke-linejoin="round"/></svg>
				</button>
				<button type="button" class="next-btn eulen-button-style go-wallet-step">Próximo</button>
			  </div>
			</div>

			<!-- STEP 2-DEPX: Amount (BRL -> DPX) -->
			<div class="form-step" data-step="2-depix" data-kind="amount-depix">
			  <div class="step-content-wrapper">
				<h2>Quanto você quer comprar?</h2>
				<div class="step-state-chips"></div>
				<div class="amount-stack">
				  <label for="desiredAmountBRL_DPX">Você paga:</label>
				  <div class="amount-row">
					<span class="prefix currency-brl">R$</span>
					<input id="desiredAmountBRL_DPX" name="desiredAmountBRL_DPX" type="text" inputmode="decimal" placeholder="0" autocomplete="off" required>
				  </div>
				  <label for="convertedAmountDPX">Você recebe:</label>
				  <div class="amount-row">
					<span class="prefix depix-logo--sm" id="convertedPrefix_DPX">Đ</span>
					<input id="convertedAmountDPX" type="text" placeholder="0" readonly disabled tabindex="-1" aria-readonly="true" aria-disabled="true">
					<span class="suffix" id="convertedSuffix_DPX">DPX</span>
				  </div>
				  <div class="amount-fees">Taxas: R$1 (transação) + 5%</div>
				</div>
			  </div>
			  <div class="button-container">
				<button type="button" class="prev-btn">
				  <svg viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M15.5 19l-7-7 7-7" stroke-linecap="round" stroke-linejoin="round"/></svg>
				</button>
				<button type="button" class="next-btn eulen-button-style go-wallet-step">Próximo</button>
			  </div>
			</div>

			<!-- STEP 2.6: Selecionar rede (apenas dois quadrados) -->
			<div class="form-step" data-step="2.6">
			  <div class="step-content-wrapper">
				<h2>Para onde enviaremos?</h2>
				<div class="step-state-chips"></div>
				<!-- BLOCO CONFIRM VALORES (comentado a pedido) -->
				<!--
				<div class="amount-stack" id="confirm-amounts">
				  <label for="confirmBRL">Você paga:</label>
				  <div class="amount-row">
					<span class="prefix currency-brl">R$</span>
					<input id="confirmBRL" type="text" placeholder="0" readonly disabled tabindex="-1" aria-readonly="true" aria-disabled="true">
				  </div>
				  <label for="confirmOut">Você recebe:</label>
				  <div class="amount-row">
					<span class="prefix" id="confirmPrefix">₿</span>
					<input id="confirmOut" type="text" placeholder="0" readonly disabled tabindex="-1" aria-readonly="true" aria-disabled="true">
					<span class="suffix" id="confirmSuffix">BTC</span>
				  </div>
				  <div class="amount-fees" id="confirmFees">Taxa: <span class="fee-expl">(R$1 transação + 5%)</span></div>
				</div>
				-->

				<div class="welcome-content">
				  <div class="radio-group category-options">
					<div class="option-row">
					  <input type="radio" id="network-liquid" name="networkChoice" value="liquid" required autocomplete="off">
					  <label for="network-liquid" class="option-card">
						<img class="liquid-icon" src="<?php echo esc_url( plugins_url('assets/Ícones/l-btc.png', DEPIXPLUGIN_PLUGIN_DIR . 'depixplugin.php') ); ?>" alt="Liquid" loading="lazy"/>
						<span class="crypto-name">Liquid</span>
					  </label>
					  <input type="radio" id="network-lightning" name="networkChoice" value="lightning" required autocomplete="off" disabled>
					  <label for="network-lightning" class="option-card option-card--disabled">
						<span class="net-logo net-lightning net-logo--lg" aria-hidden="true">⚡</span>
						<span class="crypto-name">Lightning</span>
						<small class="option-note">em breve</small>
					  </label>
					</div>
				  </div>
				</div>
				<div class="error-message" id="networkChoice-error">Por favor, selecione uma rede.</div>
			  </div>
			  <div class="button-container">
				<button type="button" class="prev-btn">
				  <svg viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M15.5 19l-7-7 7-7" stroke-linecap="round" stroke-linejoin="round"/></svg>
				</button>
			  </div>
			</div>

			<!-- STEP 2.7: Endereço da carteira -->
			<div class="form-step" data-step="2.7">
			  <div class="step-content-wrapper">
				<h2>Endereço da carteira</h2>
				<div class="step-state-chips"></div>
				<label for="wallet-address">Endereço da carteira*</label>
				<input type="text" id="wallet-address" name="walletAddress" placeholder="bc1..." required autocomplete="off">
				<div class="input-hint" id="wallet-hint"></div>
				<div class="error-message" id="walletAddress-error">Por favor, insira o endereço da sua carteira.</div>
			  </div>
			  <div class="button-container">
				<button type="button" class="prev-btn">
				  <svg viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M15.5 19l-7-7 7-7" stroke-linecap="round" stroke-linejoin="round"/></svg>
				</button>
				<button type="button" class="next-btn eulen-button-style">Próximo</button>
			  </div>
			</div>

			<!-- STEP 2.5: General User Info -->
			<div class="form-step" data-step="2.5">
			  <div class="step-content-wrapper">
				<div id="user-info-container"></div>
			  </div>
			  <div class="button-container">
				<button type="button" class="prev-btn">
				  <svg viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M15.5 19l-7-7 7-7" stroke-linecap="round" stroke-linejoin="round"/></svg>
				</button>
				<button type="button" class="next-btn eulen-button-style">Próximo</button>
			  </div>
			</div>

			<!-- STEP 3: Conditional Flows -->
			<div class="form-step" data-step="3" data-profile="p2p">
			  <div class="step-content-wrapper">
				<div id="p2p-meeting-consent"></div>
			  </div>
			  <div class="button-container">
				<button type="button" class="prev-btn">
				  <svg viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M15.5 19l-7-7 7-7" stroke-linecap="round" stroke-linejoin="round"/></svg>
				</button>
				<button type="button" class="next-btn eulen-button-style">Próximo</button>
			  </div>
			</div>

			<div class="form-step" data-step="3" data-profile="comercio">
			  <div class="step-content-wrapper">
				<div id="comercio-meeting-consent"></div>
			  </div>
			  <div class="button-container">
				<button type="button" class="prev-btn">
				  <svg viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M15.5 19l-7-7 7-7" stroke-linecap="round" stroke-linejoin="round"/></svg>
				</button>
				<button type="button" class="next-btn eulen-button-style">Próximo</button>
			  </div>
			</div>

			<div class="form-step" data-step="3" data-profile="plataforma">
			  <div class="step-content-wrapper">
				<div id="plataforma-meeting-consent"></div>
			  </div>
			  <div class="button-container">
				<button type="button" class="prev-btn">
				  <svg viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M15.5 19l-7-7 7-7" stroke-linecap="round" stroke-linejoin="round"/></svg>
				</button>
				<button type="button" class="next-btn eulen-button-style">Próximo</button>
			  </div>
			</div>

			<div class="form-step" data-step="3" data-profile="usoProprio">
			  <div class="step-content-wrapper">
				<div class="info-message" style="margin-top: 50px; margin-bottom: 20px;">
				  <h2>Uso Próprio</h2>
				  <p style="max-width: 500px; margin-left: auto; margin-right: auto;">Para casos de uso próprio, recomendamos visitar nossa página de parceiros que podem te vender DePix.</p>
				</div>
				<div style="display: flex; align-items: center; justify-content: center; margin-top: 30px;">
				  <a href="https://eulen.app/partners-p2p/" class="eulen-button-style" style="text-decoration: none;">Visitar página de parceiros</a>
				</div>
			  </div>
			  <div class="button-container">
				<button type="button" class="prev-btn">
				  <svg viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M15.5 19l-7-7 7-7" stroke-linecap="round" stroke-linejoin="round"/></svg>
				</button>
			  </div>
			</div>

			<div class="form-step" data-step="3" data-category="feature">
			  <div class="step-content-wrapper">
				<div class="info-message" style="margin-top: 50px; margin-bottom: 20px;">
				  <h2>Sugestão / Feature request</h2>
				  <p>Tem uma ideia para melhorar a Eulen ou o DePix?</p>
				  <p>Coloque na nossa plataforma de sugestões:</p>
				</div>
				<div style="display: flex; align-items: center; justify-content: center; margin-top: 30px;">
				  <a href="https://feedback.eulen.app" target="_blank" class="eulen-button-style" style="text-decoration: none;">Acessar feedback.eulen.app</a>
				</div>
			  </div>
			  <div class="button-container">
				<button type="button" class="prev-btn">
				  <svg viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M15.5 19l-7-7 7-7" stroke-linecap="round" stroke-linejoin="round"/></svg>
				</button>
			  </div>
			</div>

			<div class="form-step" data-step="3" data-category="general">
			  <div class="step-content-wrapper">
				<h2>Pesquise sua dúvida:</h2>
				<div class="search-container">
				  <div class="search-field-container">
					<input type="text" id="question-search" name="questionSearch" placeholder="Ex.: Como faço para me tornar parceiro?" class="search-input">
				  </div>
				  <div id="search-results" class="search-results-container"></div>
				</div>
				<!-- bloco de contato/telegram removido -->
			  </div>
			  <div class="button-container">
				<button type="button" class="prev-btn">
				  <svg viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M15.5 19l-7-7 7-7" stroke-linecap="round" stroke-linejoin="round"/></svg>
				</button>
			  </div>
			</div>

			<div class="form-step" data-step="3" data-category="jobs">
			  <div class="step-content-wrapper">
				<div class="info-message" style="margin-top: 50px; margin-bottom: 20px;">
				  <h2>Vagas</h2>
				  <p style="max-width: 500px; margin-left: auto; margin-right: auto;">Confira nossas oportunidades abertas e candidate-se diretamente em nossa página de carreiras.</p>
				</div>
				<div style="display: flex; align-items: center; justify-content: center; margin-top: 30px;">
				  <a href="https://jobs.eulen.app" target="_blank" class="eulen-button-style" style="text-decoration: none;">Visite jobs.eulen.app</a>
				</div>
			  </div>
			  <div class="button-container">
				<button type="button" class="prev-btn">
				  <svg viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M15.5 19l-7-7 7-7" stroke-linecap="round" stroke-linejoin="round"/></svg>
				</button>
			  </div>
			</div>

			<div class="form-step" data-step="3" data-category="complex">
			  <div class="step-content-wrapper">
				<h2>Dúvidas Complexas</h2>
				<label for="complex-topic">Área da dúvida*</label>
				<select id="complex-topic" name="complexTopic" required>
				  <option value="">Selecione a área</option>
				  <option value="api">Questões técnicas</option>
				  <option value="taxas">Custos de operação</option>
				  <option value="compliance">Compliance e Regulação</option>
				  <option value="security">Segurança e Privacidade</option>
				  <option value="other">Outro</option>
				</select>
				<div class="error-message" id="complexTopic-error">Por favor, selecione uma área.</div>
				<label for="complex-description">Detalhes da sua dúvida*</label>
				<textarea id="complex-description" name="complexDescription" rows="5" required placeholder="Descreva sua dúvida complexa detalhadamente"></textarea>
				<div class="error-message" id="complexDescription-error">Por favor, descreva sua dúvida em detalhes.</div>
				<label for="complex-context">Contexto e sistema atual*</label>
				<textarea id="complex-context" name="complexContext" rows="3" required placeholder="Forneça informações sobre seu ambiente/sistema atual e como este questionamento se relaciona ao seu caso"></textarea>
				<div class="error-message" id="complexContext-error">Por favor, forneça o contexto.</div>
				<label for="complex-tried">O que já tentou? (opcional)</label>
				<textarea id="complex-tried" name="complexTried" rows="3" placeholder="Descreva soluções que você já tentou, se aplicável"></textarea>
			  </div>
			  <div class="button-container">
				<button type="button" class="prev-btn">
				  <svg viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M15.5 19l-7-7 7-7" stroke-linecap="round" stroke-linejoin="round"/></svg>
				</button>
				<button type="submit" class="submit-btn eulen-button-style">Enviar</button>
			  </div>
			</div>

			<div class="form-step" data-step="4" style="text-align: center;">
			  <div class="step-content-wrapper">
				<h2>Pagamento via Pix</h2>
				  <div class="step-state-chips"></div>
				<div id="final-message-content" style="margin-bottom: 8px;"></div>
				<div id="pix-payment-container" class="pix-payment pix-payment--compact">
				  <div class="qr-box" aria-label="QR Code do Pix">
					<img id="pix-qr-image" alt="" draggable="false" />
				  </div>
				  <div class="brcode-box">
					<input id="pix-brcode" type="text" readonly>
					<span id="pix-brcode-preview" class="brcode-preview" aria-hidden="true"></span>
					<button type="button" id="pix-copy" class="copy-icon" title="Copiar"></button>
				  </div>
				  <div id="pix-status" class="pix-status waiting">Aguardando pagamento...</div>
				  <button type="button" id="pix-simulate-webhook" class="eulen-button-style" style="display:none; margin-top:10px;">Simular pagamento (webhook)</button>
				</div>
			  </div>
			  <div class="button-container">
				<button type="button" class="prev-btn">
				  <svg viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M15.5 19l-7-7 7-7" stroke-linecap="round" stroke-linejoin="round"/></svg>
				</button>
			  </div>
			</div>
		  </form>
		</div>
		</div>

		<?php
		return ob_get_clean();
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

