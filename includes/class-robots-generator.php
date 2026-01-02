<?php
if (!defined('ABSPATH')) {
    exit('禁止直接访问！');
}

class VisualSitemap_RobotsGenerator {

    public function generate() {
        $site_url = VisualSitemap_SettingsManager::getSiteURL();
        
        // 备份原有robots.txt
        $robots_path = ABSPATH . 'robots.txt';
        $robots_backup = ABSPATH . 'robots.txt.bak';

        if (file_exists($robots_path) && !file_exists($robots_backup)) {
            if (!@copy($robots_path, $robots_backup)) {
                error_log('VisualSitemap: 备份robots.txt失败');
            }
        }
        
        // 生成SEO优化的robots内容
        $robots = $this->buildContent($site_url);
        
        // 写入文件
        $result = file_put_contents($robots_path, $robots);
        
        if ($result === false) {
            $log = new VisualSitemap_LogManager();
            $log->log('robots', '生成robots.txt失败，检查根目录写入权限', 'fail');
            return false;
        }
        
        $log = new VisualSitemap_LogManager();
        $log->log('robots', 'SEO优化的robots.txt生成成功', 'success');
        return true;
    }

    private function buildContent($site_url) {
        $robots = "User-agent: *\n";
        $robots .= "Disallow: /wp-admin/\n";
        $robots .= "Disallow: /wp-includes/\n";
        $robots .= "Disallow: /wp-content/plugins/\n";
        $robots .= "Disallow: /wp-content/themes/\n";
        $robots .= "Disallow: /wp-login.php\n";
        $robots .= "Disallow: /feed/\n";
        $robots .= "Disallow: /comment-page-\n";
        $robots .= "Disallow: /*?replytocom=\n";
        $robots .= "Disallow: /trackback/\n";
        $robots .= "Disallow: /author/\n";
        $robots .= "Allow: /wp-content/uploads/\n";
        $robots .= "\n";
        $robots .= "Sitemap: " . esc_url($site_url) . "sitemap.xml\n";
        $robots .= "Host: " . parse_url($site_url, PHP_URL_HOST) . "\n";
        
        return $robots;
    }

    public function restore() {
        $robots_path = ABSPATH . 'robots.txt';
        $robots_backup = ABSPATH . 'robots.txt.bak';
        
        if (file_exists($robots_backup)) {
            // 先删除生成的robots.txt
            if (file_exists($robots_path)) {
                unlink($robots_path);
            }
            rename($robots_backup, $robots_path);
            return true;
        }
        
        return false;
    }
}
