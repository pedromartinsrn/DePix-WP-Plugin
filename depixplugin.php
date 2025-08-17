<?php




if ( ! function_exists( 'add_action' ) ) {
	echo 'You cannot access this file directly.';
	exit;
}

define( 'DEPIXPLUGIN_VERSION', '0.0.1' );
define( 'DEPIXPLUGIN_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );

register_activation_hook( __FILE__, array( 'DepixPlugin', 'plugin_activation' ) );
register_deactivation_hook( __FILE__, array( 'DepixPlugin', 'plugin_deactivation' ) );

require_once DEPIXPLUGIN_PLUGIN_DIR . 'class.depixplugin.php';

function depixplugin_load_textdomain() {
    load_plugin_textdomain( 'depixplugin', false, dirname( plugin_basename( __FILE__ ) ) . '/languages' );
}
add_action( 'plugins_loaded', 'depixplugin_load_textdomain' );

function depixplugin_enqueue_scripts() {
	wp_enqueue_style( 'depixplugin-style', plugins_url( 'assets/style.css', __FILE__ ), array(), DEPIXPLUGIN_VERSION );
	wp_enqueue_script( 'depixplugin-script', plugins_url( 'assets/script.js', __FILE__ ), array( 'jquery' ), DEPIXPLUGIN_VERSION, true );
}

add_action( 'wp_enqueue_scripts', 'depixplugin_enqueue_scripts' );


add_action( 'init', array( 'DepixPlugin', 'init' ) );

