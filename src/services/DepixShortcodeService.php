<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Shortcode Service
 */
class DepixShortcodeService {
    
    public static function init() {
        add_action('init', array(__CLASS__, 'register_shortcodes'));
    }
    
    public static function register_shortcodes() {
        add_shortcode('depix_p2ps', array(__CLASS__, 'render_p2ps_list'));
    }
    
    public static function render_p2ps_list($atts = array()) {
        $atts = shortcode_atts(array(
            'limit' => 0,
            'search' => '',
            'orderby' => '',
            'order' => 'asc',
            'style' => 'default'
        ), $atts);
        
        $debug_info = '';
        if (defined('WP_DEBUG') && WP_DEBUG) {
            $debug_info = '<!-- DePix Plugin Debug: Shortcode [depix_p2ps] carregado -->';
        }
        
        return $debug_info . self::get_p2ps_html($atts);
    }
    
    private static function get_p2ps_html($atts) {
        $style_class = 'depix-style-' . esc_attr($atts['style']);
        
        return sprintf('
        <div class="depix-p2ps %s" data-limit="%s" data-search="%s" data-orderby="%s" data-order="%s">
            <div class="depix-p2ps-header">
                <form method="get" class="depix-p2ps-search-form">
                    <input type="text" name="depix_p2ps_search" placeholder="Buscar P2P..." value="%s" />
                    <select name="depix_p2ps_orderby">
                        <option value="">Ordenar por</option>
                        <option value="name"%s>Nome</option>
                        <option value="minValue"%s>Valor Mínimo</option>
                        <option value="tax"%s>Taxa</option>
                    </select>
                    <select name="depix_p2ps_order">
                        <option value="asc"%s>Ascendente</option>
                        <option value="desc"%s>Descendente</option>
                    </select>
                    <button type="submit">Buscar</button>
                </form>
            </div>
            <div class="depix-p2ps-results">
                <div class="depix-p2ps-loading">Carregando P2Ps...</div>
            </div>
        </div>',
            $style_class,
            esc_attr($atts['limit']),
            esc_attr($atts['search']),
            esc_attr($atts['orderby']),
            esc_attr($atts['order']),
            esc_attr($atts['search']),
            selected($atts['orderby'], 'name', false),
            selected($atts['orderby'], 'minValue', false),
            selected($atts['orderby'], 'tax', false),
            selected($atts['order'], 'asc', false),
            selected($atts['order'], 'desc', false)
        );
    }
}
