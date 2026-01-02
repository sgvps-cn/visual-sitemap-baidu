<?php
if (!defined('ABSPATH')) {
    exit('禁止直接访问！');
}

// 配置常量定义
define('VISUAL_SITEMAP_LOG_LIMIT_MIN', 10);
define('VISUAL_SITEMAP_LOG_LIMIT_MAX', 500);
define('VISUAL_SITEMAP_LOG_LIMIT_DEFAULT', 50);
define('VISUAL_SITEMAP_CRON_HOUR_DEFAULT', 3);
define('VISUAL_SITEMAP_SPIN_LEVEL_MIN', 1);
define('VISUAL_SITEMAP_SPIN_LEVEL_MAX', 5);

class VisualSitemap_SettingsManager {

    private static $default_settings = array(
        'baidu_api_url' => '',
        'cron_hour' => 3,
        'ignore_urls' => "wp-admin\nwp-login.php\nfeed\ncomment-page\n?replytocom=",
        'log_limit' => 50,
        'enable_seo_meta' => 1,
        'enable_robots' => 1,
        'enable_auto_push' => 1,
        'enable_priority' => 1,
        'home_priority' => '1.0',
        'post_priority' => '0.8',
        'page_priority' => '0.7',
        'category_priority' => '0.6',
        'tag_priority' => '0.5',
        'enable_spintax' => 0,
        'spin_level' => 2,
        'spin_mode' => 'word',
        'custom_synonyms' => "",
        'api_endpoint' => 'https://api.sgvps.cn',
        'last_check_update' => 0,
        'latest_version' => VISUAL_SITEMAP_BAIDU_VERSION
    );

    public static function getSettings() {
        $settings = wp_cache_get('visual_sitemap_baidu_settings');

        if (!$settings || !is_array($settings)) {
            $settings = get_option('visual_sitemap_baidu_settings');

            if (!$settings || !is_array($settings)) {
                $settings = array();
            }

            $settings = wp_parse_args($settings, self::$default_settings);

            $settings['cron_hour'] = intval($settings['cron_hour']);
            $settings['log_limit'] = intval($settings['log_limit']);
            $settings['enable_seo_meta'] = intval($settings['enable_seo_meta']);
            $settings['enable_robots'] = intval($settings['enable_robots']);
            $settings['enable_auto_push'] = intval($settings['enable_auto_push']);
            $settings['enable_priority'] = intval($settings['enable_priority']);

            $settings['cron_hour'] = ($settings['cron_hour'] < 0 || $settings['cron_hour'] > 23) ? 3 : $settings['cron_hour'];
            $settings['log_limit'] = ($settings['log_limit'] < 10 || $settings['log_limit'] > 500) ? 50 : $settings['log_limit'];

            $priority_fields = array('home_priority', 'post_priority', 'page_priority', 'category_priority', 'tag_priority');
            foreach ($priority_fields as $field) {
                $value = floatval($settings[$field]);
                if ($value < 0.0 || $value > 1.0) {
                    $settings[$field] = '0.5';
                } else {
                    $settings[$field] = (string)$value;
                }
            }

            wp_cache_set('visual_sitemap_baidu_settings', $settings, '', 3600);
        }

        return $settings;
    }

    public static function saveSettings($settings) {
        $settings = wp_parse_args($settings, self::$default_settings);

        $result = update_option('visual_sitemap_baidu_settings', $settings);

        wp_cache_set('visual_sitemap_baidu_settings', $settings, '', 3600);

        return $result;
    }

    public static function initSettings() {
        if (!wp_cache_get('visual_sitemap_baidu_settings')) {
            $settings = get_option('visual_sitemap_baidu_settings');
            if (!$settings || !is_array($settings)) {
                $settings = self::$default_settings;
                update_option('visual_sitemap_baidu_settings', $settings);
            } else {
                $settings = wp_parse_args($settings, self::$default_settings);
                update_option('visual_sitemap_baidu_settings', $settings);
            }
            wp_cache_set('visual_sitemap_baidu_settings', $settings, '', 3600);
        }
    }

    public static function getSiteURL() {
        static $site_url = null;

        if ($site_url === null) {
            $site_url = home_url('/', is_ssl() ? 'https' : 'http');
            $site_url = rtrim($site_url, '/') . '/';
        }

        return $site_url;
    }

    public static function validateAPIUrl($api_url) {
        if (empty($api_url)) {
            return false;
        }

        if (strpos($api_url, 'data.zz.baidu.com/urls') === false) {
            return false;
        }

        $parsed_url = parse_url($api_url);
        if (!$parsed_url || !isset($parsed_url['query'])) {
            return false;
        }

        parse_str($parsed_url['query'], $params);

        if (empty($params['site']) || empty($params['token'])) {
            return false;
        }

        if (!filter_var($api_url, FILTER_VALIDATE_URL)) {
            return false;
        }

        return true;
    }

    public static function checkPermissions() {
        $paths = array(
            ABSPATH => '网站根目录',
            ABSPATH . 'sitemap.xml' => 'Sitemap文件'
        );

        $errors = array();

        foreach ($paths as $path => $name) {
            if (file_exists($path)) {
                if (!is_writable($path)) {
                    $errors[] = $name . ' 不可写';
                }
            } else {
                $dir = dirname($path);
                if (!is_writable($dir)) {
                    $errors[] = $name . ' 所在目录不可写';
                }
            }
        }

        return $errors;
    }
}
