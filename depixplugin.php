<?php
/**
* @package Depix WP Plugin
*/

/*
Plugin Name: Depix WP Plugin
Description: An open-source Wordpress plugin that shows Bitcoin P2Ps which accept the Depix method of payment.
Version: 0.0.1
Requires at least: 5.8
Requires PHP: 7.4
Author: Pedro, MZero, Caioqf
License: GPLv2 or later
Text Domain: depixplugin
*/

if ( ! function_exists( 'add_action' ) ) {
	echo 'You cannot access this file directly.';
	exit;
}

define( 'DEPIXPLUGIN_VERSION', '0.0.1' );
define( 'DEPIXPLUGIN_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );

register_activation_hook( __FILE__, array( 'DepixPlugin', 'plugin_activation' ) );
register_deactivation_hook( __FILE__, array( 'DepixPlugin', 'plugin_deactivation' ) );

require_once DEPIXPLUGIN_PLUGIN_DIR . 'class.depixplugin.php';

function depixplugin_enqueue_scripts() {
	wp_enqueue_style( 'depixplugin-style', plugins_url( 'assets/style.css', __FILE__ ), array(), DEPIXPLUGIN_VERSION );
	wp_enqueue_script( 'depixplugin-script', plugins_url( 'assets/script.js', __FILE__ ), array( 'jquery' ), DEPIXPLUGIN_VERSION, true );
}

add_action( 'wp_enqueue_scripts', 'depixplugin_enqueue_scripts' );


add_action( 'init', array( 'DepixPlugin', 'init' ) );

