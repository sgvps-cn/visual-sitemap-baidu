<?php
if (!defined('ABSPATH')) {
    exit('禁止直接访问！');
}

class VisualSitemap_BaiduSpider {

    private static $baidu_patterns = array(
        'baiduspider',
        'baidu.com',
        'baiduimagespider',
        'baiduspider-render'
    );
    
    /**
     * 检测当前访问是否为百度蜘蛛
     * 
     * @return bool 是否为百度蜘蛛
     */
    public static function isBaiduSpider() {
        if (!isset($_SERVER['HTTP_USER_AGENT'])) {
            return false;
        }
        
        $user_agent = strtolower($_SERVER['HTTP_USER_AGENT']);
        
        foreach (self::$baidu_patterns as $pattern) {
            if (strpos($user_agent, $pattern) !== false) {
                return true;
            }
        }
        
        return false;
    }

    public static function getSpiderType() {
        if (!self::isBaiduSpider()) {
            return 'unknown';
        }
        
        $user_agent = strtolower($_SERVER['HTTP_USER_AGENT']);
        
        if (strpos($user_agent, 'baiduspider-render') !== false) {
            return 'baidu_render';
        } elseif (strpos($user_agent, 'baiduimagespider') !== false) {
            return 'baidu_image';
        } else {
            return 'baidu_spider';
        }
    }

    public static function logSpiderVisit($post_id = 0) {
        if (!self::isBaiduSpider()) {
            return;
        }
        
        global $wpdb;
        $table = $wpdb->prefix . 'visual_sitemap_baidu_spider_logs';
        
        $wpdb->insert($table, array(
            'post_id' => $post_id,
            'spider_type' => self::getSpiderType(),
            'user_agent' => substr($_SERVER['HTTP_USER_AGENT'], 0, 500),
            'ip_address' => self::getClientIP(),
            'request_uri' => $_SERVER['REQUEST_URI'],
            'visit_time' => current_time('mysql')
        ));
    }

    private static function getClientIP() {
        $ip = '';
        
        if (isset($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            $ip = $_SERVER['HTTP_X_FORWARDED_FOR'];
        } elseif (isset($_SERVER['HTTP_CLIENT_IP'])) {
            $ip = $_SERVER['HTTP_CLIENT_IP'];
        } else {
            $ip = $_SERVER['REMOTE_ADDR'];
        }
        
        return substr($ip, 0, 45);
    }
}
