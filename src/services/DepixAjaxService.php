<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * AJAX service
 */
class DepixAjaxService {
    
    public static function init() {
        add_action('wp_ajax_depix_get_p2ps', array(__CLASS__, 'handle_get_p2ps'));
        add_action('wp_ajax_nopriv_depix_get_p2ps', array(__CLASS__, 'handle_get_p2ps'));
        
        add_action('wp_ajax_depix_get_p2ps_json', array(__CLASS__, 'handle_get_p2ps_json'));
        add_action('wp_ajax_nopriv_depix_get_p2ps_json', array(__CLASS__, 'handle_get_p2ps_json'));
    }
    
    public static function handle_get_p2ps() {
        if (!self::verify_nonce()) {
            wp_send_json_error('Nonce inválido');
            return;
        }
        
        $search = sanitize_text_field($_POST['search'] ?? '');
        $orderby = sanitize_text_field($_POST['orderby'] ?? '');
        $order = sanitize_text_field($_POST['order'] ?? 'asc');
        
        try {
            $p2ps = DepixP2PService::get_p2ps();
            
            if (empty($p2ps)) {
                wp_send_json_error('Nenhum P2P encontrado');
                return;
            }
            
            $filtered_p2ps = DepixP2PService::filter_p2ps($p2ps, $search, $orderby, $order);
            wp_send_json_success($filtered_p2ps);
            
        } catch (Exception $e) {
            error_log('DePix Plugin AJAX Error: ' . $e->getMessage());
            wp_send_json_error('Erro ao carregar P2Ps: ' . $e->getMessage());
        }
    }
    
    public static function handle_get_p2ps_json() {
        if (!self::verify_nonce()) {
            wp_send_json_error('Nonce inválido');
            return;
        }
        
        try {
            $p2ps_data = DepixP2PService::get_p2ps();
            
            if (empty($p2ps_data)) {
                wp_send_json_error('Dados não encontrados');
                return;
            }
            
            wp_send_json_success($p2ps_data);
            
        } catch (Exception $e) {
            error_log('DePix Plugin JSON Error: ' . $e->getMessage());
            wp_send_json_error('Erro ao carregar dados JSON: ' . $e->getMessage());
        }
    }
    
    private static function verify_nonce() {
        $nonce = $_POST['nonce'] ?? $_GET['nonce'] ?? '';
        return wp_verify_nonce($nonce, 'depix_p2ps_nonce');
    }
    
    public static function get_nonce() {
        return wp_create_nonce('depix_p2ps_nonce');
    }
}
