<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}


class DepixP2PService {
    
    private static $cached_p2ps = null;
    
    public static function get_p2ps() {
        if (self::$cached_p2ps !== null) {
            return self::$cached_p2ps;
        }
        
        self::$cached_p2ps = self::load_from_json();
        return self::$cached_p2ps;
    }
    
    private static function load_from_json() {
        $json_file = DEPIXPLUGIN_PLUGIN_DIR . 'src/mock/p2p.json';
        if ( ! file_exists( $json_file ) ) {
            throw new \Exception('DePix: Arquivo não encontrado: ' . $json_file);
        }

        $json_data = file_get_contents( $json_file );
        if ($json_data === false) {
            throw new \Exception('DePix: Erro ao ler JSON: ' . $json_file);
        }
        
        $p2ps = json_decode( $json_data, true );
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new \Exception('DePix: JSON inválido - ' . json_last_error_msg());
        }

        return $p2ps;
    }
    
    public static function filter_p2ps($p2ps, $search = '', $orderby = '', $order = 'asc') {
        $filtered = $p2ps;
        
        if (!empty($search)) {
            $filtered = array_filter($filtered, function($p2p) use ($search) {
                return stripos($p2p['name'], $search) !== false ||
                       stripos($p2p['description'], $search) !== false ||
                       stripos($p2p['contact'], $search) !== false;
            });
        }
        
        if (!empty($orderby) && in_array($orderby, ['minValue', 'tax', 'name'])) {
            usort($filtered, function($a, $b) use ($orderby, $order) {
                if ($orderby === 'minValue') {
                    $valA = self::parse_price($a[$orderby]);
                    $valB = self::parse_price($b[$orderby]);
                } elseif ($orderby === 'tax') {
                    $valA = self::parse_percentage($a[$orderby]);
                    $valB = self::parse_percentage($b[$orderby]);
                } else {
                    $valA = $a[$orderby];
                    $valB = $b[$orderby];
                }
                
                if ($valA == $valB) return 0;
                
                $comparison = $valA > $valB ? 1 : -1;
                return $order === 'desc' ? -$comparison : $comparison;
            });
        }
        
        return array_values($filtered);
    }
    
    private static function parse_price($price_string) {
        return (float) preg_replace('/[^0-9,.]/', '', str_replace(',', '.', $price_string));
    }
    
    private static function parse_percentage($percent_string) {
        return (float) str_replace('%', '', $percent_string);
    }
    
    public static function clear_cache() {
        self::$cached_p2ps = null;
    }
}
