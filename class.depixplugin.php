<?php

if (!defined('ABSPATH')) {
    exit;
}

class DepixPlugin {
    
    public static function init() {
        add_action('admin_notices', function() {
            echo '<div class="notice notice-success is-dismissible"><p>Hello World from Depix WP Plugin!</p></div>';
        });
    }
    
    public static function plugin_activation() {
        add_option('depix_plugin_options', array(
            'api_enabled' => true,
            'cache_enabled' => true,
            'debug_mode' => false
        ));
        flush_rewrite_rules();
    }

    public static function plugin_deactivation() {
        delete_option('depix_plugin_options');
        if (function_exists('delete_transient')) {
            delete_transient('depix_p2p_data');
        }
        flush_rewrite_rules();
    }
}