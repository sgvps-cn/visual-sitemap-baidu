<?php
if (!defined('ABSPATH')) {
    exit('禁止直接访问！');
}

class VisualSitemap_URLDedup {

    private $table_name;

    public function __construct() {
        global $wpdb;
        $this->table_name = $wpdb->prefix . 'visual_sitemap_baidu_urls';
    }

    public function createTable() {
        global $wpdb;
        
        $table_exists = $wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $this->table_name));
        
        if ($table_exists) {
            return true;
        }
        
        $sql = "CREATE TABLE {$this->table_name} (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT,
            url VARCHAR(500) NOT NULL,
            url_hash VARCHAR(32) NOT NULL,
            post_id BIGINT UNSIGNED DEFAULT 0,
            content_hash VARCHAR(32) DEFAULT '',
            spider_visited TINYINT(1) DEFAULT 0,
            last_visit_time DATETIME NULL,
            push_status TINYINT(1) DEFAULT 0,
            push_time DATETIME NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY idx_url_hash (url_hash),
            KEY idx_post_id (post_id),
            KEY idx_spider_visited (spider_visited),
            KEY idx_push_status (push_status)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
        
        return true;
    }

    public function addURL($url, $post_id = 0, $content = '') {
        global $wpdb;
        
        $url_hash = md5($url);
        $content_hash = empty($content) ? '' : md5($content);

        $exists = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM {$this->table_name} WHERE url_hash = %s",
            $url_hash
        ));

        if ($exists) {
            $wpdb->update($this->table_name, 
                array(
                    'content_hash' => $content_hash,
                    'post_id' => $post_id,
                    'updated_at' => current_time('mysql')
                ),
                array('url_hash' => $url_hash),
                array('%s', '%d', '%s'),
                array('%s')
            );
            return $exists;
        }

        $result = $wpdb->insert($this->table_name, 
            array(
                'url' => substr($url, 0, 500),
                'url_hash' => $url_hash,
                'post_id' => $post_id,
                'content_hash' => $content_hash
            ),
            array('%s', '%s', '%d', '%s')
        );
        
        return $result ? $wpdb->insert_id : false;
    }

    public function isDuplicate($url, $content = '') {
        global $wpdb;
        
        $url_hash = md5($url);
        
        $exists = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$this->table_name} WHERE url_hash = %s",
            $url_hash
        ));

        if (!$exists) {
            return false;
        }

        if (!empty($content)) {
            $content_hash = md5($content);
            return $content_hash === $exists->content_hash;
        }
        
        return true;
    }

    public function markPushed($url) {
        global $wpdb;
        
        $url_hash = md5($url);
        
        $result = $wpdb->update($this->table_name,
            array(
                'push_status' => 1,
                'push_time' => current_time('mysql'),
                'updated_at' => current_time('mysql')
            ),
            array('url_hash' => $url_hash),
            array('%d', '%s', '%s'),
            array('%s')
        );
        
        return $result !== false;
    }

    public function markSpiderVisited($url) {
        global $wpdb;
        
        $url_hash = md5($url);
        
        $result = $wpdb->update($this->table_name,
            array(
                'spider_visited' => 1,
                'last_visit_time' => current_time('mysql'),
                'updated_at' => current_time('mysql')
            ),
            array('url_hash' => $url_hash),
            array('%d', '%s', '%s'),
            array('%s')
        );
        
        return $result !== false;
    }

    public function getUnpushedURLs($limit = 1000) {
        global $wpdb;
        
        $urls = $wpdb->get_col($wpdb->prepare(
            "SELECT url FROM {$this->table_name} WHERE push_status = 0 LIMIT %d",
            $limit
        ));
        
        return $urls;
    }

    public function getPushedButUnvisitedURLs($limit = 100) {
        global $wpdb;
        
        $urls = $wpdb->get_col($wpdb->prepare(
            "SELECT url FROM {$this->table_name} WHERE push_status = 1 AND spider_visited = 0 LIMIT %d",
            $limit
        ));
        
        return $urls;
    }

    public function cleanupOldRecords($days = 90) {
        global $wpdb;
        
        $result = $wpdb->query($wpdb->prepare(
            "DELETE FROM {$this->table_name} WHERE created_at < DATE_SUB(NOW(), INTERVAL %d DAY)",
            $days
        ));
        
        return $result;
    }
}
