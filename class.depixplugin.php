<?php

if (!defined('ABSPATH')) {
    exit;
}

require_once DEPIXPLUGIN_PLUGIN_DIR . 'src/services/autoloader.php';

class DepixPlugin {
    
    public static function init() {
        depix_init_services();
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
        delete_transient('depix_p2p_data');
        flush_rewrite_rules();
    }
}
add_action('plugins_loaded', array('DepixPlugin', 'init'));