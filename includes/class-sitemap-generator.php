<?php
if (!defined('ABSPATH')) {
    exit('禁止直接访问！');
}

class VisualSitemap_SitemapGenerator {

    private static $settings_cache = null;
    private static $ignore_urls_cache = null;

    public function generate($push_baidu = false) {
        $start_time = microtime(true);

        $settings = $this->getCachedSettings();

        if (self::$ignore_urls_cache === null) {
            self::$ignore_urls_cache = array_filter(array_map('trim', explode("\n", $settings['ignore_urls'])));
        }

        $urls = $this->collectURLs(self::$ignore_urls_cache);

        $xml = $this->buildXML($urls);

        $result = $this->writeFile($xml);

        if ($result) {
            $log = new VisualSitemap_LogManager();
            $duration = round((microtime(true) - $start_time) * 1000, 2);
            $log->log('generate', 'SEO Sitemap生成成功，共' . count($urls) . '条URL，耗时' . $duration . 'ms', 'success');

            if ($push_baidu || (current_filter() == 'visual_sitemap_baidu_cron')) {
                $baidu_push = new VisualSitemap_BaiduPush();
                $baidu_push->push($urls);
            }
        } else {
            $log = new VisualSitemap_LogManager();
            $log->log('generate', '写入SEO Sitemap.xml失败，检查根目录写入权限', 'fail');
        }

        return $result;
    }

    private function getCachedSettings() {
        if (self::$settings_cache === null) {
            self::$settings_cache = VisualSitemap_SettingsManager::getSettings();
        }
        return self::$settings_cache;
    }

    private function collectURLs($ignore_urls) {
        $settings = $this->getCachedSettings();
        $urls = array();
        $enable_priority = $settings['enable_priority'];
        $lastmod_default = date('c');

        $home_url = VisualSitemap_SettingsManager::getSiteURL();
        if (!$this->isIgnore($home_url, $ignore_urls)) {
            $urls[] = array(
                'loc' => $home_url,
                'lastmod' => $lastmod_default,
                'changefreq' => 'daily',
                'priority' => $enable_priority ? $settings['home_priority'] : '1.0'
            );
        }

        $posts = get_posts(array(
            'post_type' => 'post',
            'post_status' => 'publish',
            'numberposts' => -1,
            'orderby' => 'modified',
            'order' => 'DESC',
            'no_found_rows' => true,
            'update_post_meta_cache' => false,
            'update_post_term_cache' => false,
            'fields' => 'ids'
        ));

        $post_priority = $enable_priority ? $settings['post_priority'] : '0.8';
        foreach ($posts as $post_id) {
            $url = get_permalink($post_id);
            if ($url && !$this->isIgnore($url, $ignore_urls)) {
                $urls[] = array(
                    'loc' => $url,
                    'lastmod' => get_post_modified_time('c', false, $post_id),
                    'changefreq' => 'weekly',
                    'priority' => $post_priority
                );
            }
        }

        $pages = get_posts(array(
            'post_type' => 'page',
            'post_status' => 'publish',
            'numberposts' => -1,
            'no_found_rows' => true,
            'update_post_meta_cache' => false,
            'update_post_term_cache' => false,
            'fields' => 'ids'
        ));

        $page_priority = $enable_priority ? $settings['page_priority'] : '0.7';
        foreach ($pages as $post_id) {
            $url = get_permalink($post_id);
            if ($url && !$this->isIgnore($url, $ignore_urls)) {
                $urls[] = array(
                    'loc' => $url,
                    'lastmod' => get_post_modified_time('c', false, $post_id),
                    'changefreq' => 'monthly',
                    'priority' => $page_priority
                );
            }
        }

        $categories = get_categories(array(
            'hide_empty' => true,
            'fields' => 'ids',
            'no_found_rows' => true
        ));

        $cat_priority = $enable_priority ? $settings['category_priority'] : '0.6';
        foreach ($categories as $cat_id) {
            $url = get_category_link($cat_id);
            if ($url && !$this->isIgnore($url, $ignore_urls)) {
                $urls[] = array(
                    'loc' => $url,
                    'lastmod' => $lastmod_default,
                    'changefreq' => 'weekly',
                    'priority' => $cat_priority
                );
            }
        }

        $tags = get_tags(array(
            'hide_empty' => true,
            'fields' => 'ids',
            'no_found_rows' => true
        ));

        $tag_priority = $enable_priority ? $settings['tag_priority'] : '0.5';
        foreach ($tags as $tag_id) {
            $url = get_tag_link($tag_id);
            if ($url && !$this->isIgnore($url, $ignore_urls)) {
                $urls[] = array(
                    'loc' => $url,
                    'lastmod' => $lastmod_default,
                    'changefreq' => 'monthly',
                    'priority' => $tag_priority
                );
            }
        }

        return $urls;
    }

    private function buildXML($urls) {
        $xml_parts = array();
        $xml_parts[] = '<?xml version="1.0" encoding="UTF-8"?>';
        $xml_parts[] = '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">';

        foreach ($urls as $url) {
            $xml_parts[] = '  <url>';
            $xml_parts[] = '    <loc>' . esc_url($url['loc']) . '</loc>';
            $xml_parts[] = '    <lastmod>' . esc_html($url['lastmod']) . '</lastmod>';
            $xml_parts[] = '    <changefreq>' . esc_html($url['changefreq']) . '</changefreq>';
            $xml_parts[] = '    <priority>' . esc_html($url['priority']) . '</priority>';
            $xml_parts[] = '  </url>';
        }

        $xml_parts[] = '</urlset>';

        return implode("\n", $xml_parts);
    }

    private function writeFile($xml) {
        $sitemap_path = ABSPATH . 'sitemap.xml';

        if (!file_exists($sitemap_path)) {
            $fp = @fopen($sitemap_path, 'w');
            if ($fp) {
                fclose($fp);
            }
        }

        $result = file_put_contents($sitemap_path, $xml);

        return $result !== false;
    }

    private function isIgnore($url, $ignore_list) {
        if (!is_array($ignore_list) || empty($ignore_list)) {
            return false;
        }

        foreach ($ignore_list as $keyword) {
            $keyword = trim($keyword);
            if (!empty($keyword) && strpos($url, $keyword) !== false) {
                return true;
            }
        }

        return false;
    }
}
