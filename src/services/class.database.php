<?php 
if (!defined('ABSPATH')) { exit; }

require_once ABSPATH . 'wp-admin/includes/upgrade.php';

class DepixTablesWP
{
    public function executeInitialTable()
    {
        try {
            global $wpdb;
            $table = $wpdb->prefix . 'depixwp_transactions';
            $charset = $wpdb->get_charset_collate();
            $sql = "CREATE TABLE IF NOT EXISTS $table (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                tx_id VARCHAR(64) NOT NULL UNIQUE,
                amount_cents BIGINT UNSIGNED NOT NULL,
                status VARCHAR(32) NOT NULL DEFAULT 'created',
                async TINYINT(1) NOT NULL DEFAULT 0,
                qr_copy_paste TEXT NULL,
                qr_image_url TEXT NULL,
                meta TEXT NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                INDEX (status),
                INDEX (created_at)
            ) $charset;";

            return dbDelta($sql);
        } catch (Exception $e) {
            error_log('Erro ao criar tabela: ' . $e->getMessage());
        }
    }

    public function storeTransaction($data, $async, $amountInCents)
    {
        global $wpdb;
        $table = $wpdb->prefix . 'depixwp_transactions';

        $tx_id = $data['qrId'] ?? $data['id'] ?? '';
        if ($tx_id === '') {
            error_log('[Depix][DB][Warn] storeTransaction sem id/qrId no payload.');
        }
        $wpdb->insert(
            $table,
            [
                'tx_id' => $tx_id,
                'amount_cents' => isset($data['amountInCents']) ? (int)$data['amountInCents'] : (isset($data['valueInCents']) ? (int)$data['valueInCents'] : (int)$amountInCents),
                'status' => $data['status'] ?? 'created',
                'async' => $async ? 1 : 0,
                'qr_copy_paste' => $data['qrCopyPaste'] ?? null,
                'qr_image_url' => $data['qrImageUrl'] ?? null,
                'meta' => isset($data['meta']) ? wp_json_encode($data['meta']) : null,
            ],
            [
                '%s',
                '%d',
                '%s',
                '%d',
                '%s',
                '%s',
                '%s',
            ]
        );
    }

    public function getTransactionStatus(string $tx_id): ?string
    {
        global $wpdb;
        $table = $wpdb->prefix . 'depixwp_transactions';
        $row = $wpdb->get_var($wpdb->prepare("SELECT status FROM $table WHERE tx_id = %s", $tx_id));
        return $row ?: null;
    }

    public function updateTransaction(array $data): bool
    {
        global $wpdb;
        $table = $wpdb->prefix . 'depixwp_transactions';

        if (isset($data['qrId'])) {
            $data['id'] = $data['qrId'];
        }
        if (empty($data['id'])) {
            error_log('[Depix][DB][Warn] updateTransaction sem id/qrId.');
            return false;
        }

        $fields = [];
        $formats = [];

        if (!empty($data['status'])) {
            $fields['status'] = sanitize_text_field($data['status']);
            $formats[] = '%s';
        }
        if (isset($data['amountInCents']) || isset($data['valueInCents'])) {
            $fields['amount_cents'] = isset($data['amountInCents']) ? (int)$data['amountInCents'] : (int)$data['valueInCents'];
            $formats[] = '%d';
        }

        $fields['meta'] = wp_json_encode($data);
        $formats[] = '%s';

        if (empty($fields)) {
            return false;
        }

        $updated = $wpdb->update(
            $table,
            $fields,
            ['tx_id' => sanitize_text_field($data['id'])],
            $formats,
            ['%s']
        );
        if (!$updated && !empty($data['qrId']) && $data['qrId'] !== $data['id']) {
            $updated = $wpdb->update(
                $table,
                $fields,
                ['tx_id' => sanitize_text_field($data['qrId'])],
                $formats,
                ['%s']
            );
        }
        if (!$updated) {
            error_log('[Depix][DB][Info] Nenhuma linha atualizada para tx_id='.$data['id']);
        }
        return (bool)$updated;    
    }

}