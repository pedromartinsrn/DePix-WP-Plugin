<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Autoloader
 */
require_once DEPIXPLUGIN_PLUGIN_DIR . 'src/services/DepixP2PService.php';
require_once DEPIXPLUGIN_PLUGIN_DIR . 'src/services/DepixAjaxService.php';
require_once DEPIXPLUGIN_PLUGIN_DIR . 'src/services/DepixAssetService.php';
require_once DEPIXPLUGIN_PLUGIN_DIR . 'src/services/DepixShortcodeService.php';


function depix_init_services() {
    DepixAjaxService::init();
    DepixAssetService::init();
    DepixShortcodeService::init();
}
