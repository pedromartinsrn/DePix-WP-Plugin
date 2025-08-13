<?php

if (!defined('ABSPATH')) { exit; }

require_once DEPIXPLUGIN_PLUGIN_DIR . 'src/services/class.eulen.php'; 
require_once DEPIXPLUGIN_PLUGIN_DIR . 'src/services/class.database.php';
require_once DEPIXPLUGIN_PLUGIN_DIR . 'src/services/class.eulenWebhook.php';
require_once DEPIXPLUGIN_PLUGIN_DIR . 'src/panel/class.eulenpanel.php';
require_once DEPIXPLUGIN_PLUGIN_DIR . 'src/shortcodes/class.shortcode.php';

class DepixPlugin {

    public static $eulen_service;
    public static $panel;
    public static $database;
    public static $webhook;

    public static function init() {
        self::$eulen_service = new EulenService();
        self::$database = new DepixTablesWP();

        self::$panel = new EulenPanel();
        self::$panel->init();

        self::$webhook = new EulenWebhook();
        self::$webhook->init();

        // Disable for debugging and use [depix_teste]
        // DepixShortcodes::init();
    }
    
    public static function plugin_activation() {
        add_option('depix_plugin_options', array(
            'api_enabled' => true,
            'cache_enabled' => true,
            'debug_mode' => false
        ));
        $db = new DepixTablesWP();
        $db->executeInitialTable();  
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