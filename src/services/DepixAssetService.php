<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Assets service
 */
class DepixAssetService {
    
    public static function init() {
        add_action('wp_enqueue_scripts', array(__CLASS__, 'enqueue_frontend_assets'));
    }
    
    public static function enqueue_frontend_assets() {
        if (!self::should_enqueue_assets()) {
            return;
        }
        
        $plugin_url = plugin_dir_url(dirname(dirname(__FILE__)));
        
        wp_enqueue_style(
            'depix-p2ps-style',
            $plugin_url . 'assets/style.css',
            array(),
            DEPIXPLUGIN_VERSION
        );
        
        wp_enqueue_script(
            'depix-p2ps-script',
            $plugin_url . 'assets/script.js',
            array('jquery'),
            DEPIXPLUGIN_VERSION,
            true
        );
        
        wp_localize_script('depix-p2ps-script', 'depix_ajax', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => DepixAjaxService::get_nonce(),
            'plugin_url' => $plugin_url,
            'debug' => defined('WP_DEBUG') && WP_DEBUG
        ));
        
        if (defined('WP_DEBUG') && WP_DEBUG) {
            global $post;
            error_log('DePix Plugin: Assets enfileirados para ' . (is_object($post) ? $post->post_title : 'página atual'));
        }
    }
    
    private static function should_enqueue_assets() {
        global $post;
        
        if (is_object($post) && has_shortcode($post->post_content, 'depix_p2ps')) {
            return true;
        }
        
        if (is_singular()) {
            $content = get_the_content();
            if (has_shortcode($content, 'depix_p2ps')) {
                return true;
            }
        }
        
        if (defined('WP_DEBUG') && WP_DEBUG) {
            return true;
        }
        
        return false;
    }
}
