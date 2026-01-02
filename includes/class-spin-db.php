<?php
if (!defined('ABSPATH')) {
    exit('禁止直接访问！');
}

class VisualSitemap_SpinDB {

    private $table_name;

    public function __construct() {
        global $wpdb;
        $this->table_name = $wpdb->prefix . 'visual_sitemap_baidu_spin_logs';
    }

    public function createTable() {
        global $wpdb;
        
        $table_exists = $wpdb->get_var("SHOW TABLES LIKE '{$this->table_name}'");
        
        if ($table_exists) {
            return true;
        }
        
        $sql = "CREATE TABLE {$this->table_name} (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT,
            post_id BIGINT UNSIGNED NOT NULL,
            original_content TEXT NOT NULL,
            spun_content TEXT NOT NULL,
            spin_degree DECIMAL(5,2) DEFAULT 0.00,
            spin_level TINYINT(1) DEFAULT 2,
            spin_mode VARCHAR(20) DEFAULT 'word',
            template_type VARCHAR(50) DEFAULT 'default',
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_post_id (post_id),
            KEY idx_spin_degree (spin_degree)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
        
        return true;
    }

    public function saveSpinLog($post_id, $original_content, $spun_content, $spin_degree, $spin_level, $spin_mode, $template_type) {
        global $wpdb;
        
        $result = $wpdb->insert($this->table_name,
            array(
                'post_id' => $post_id,
                'original_content' => substr($original_content, 0, 65000),
                'spun_content' => substr($spun_content, 0, 65000),
                'spin_degree' => $spin_degree,
                'spin_level' => $spin_level,
                'spin_mode' => $spin_mode,
                'template_type' => $template_type
            ),
            array('%d', '%s', '%s', '%f', '%d', '%s', '%s')
        );
        
        return $result ? $wpdb->insert_id : false;
    }

    public function getSpinLog($post_id) {
        global $wpdb;
        
        return $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$this->table_name} WHERE post_id = %d ORDER BY created_at DESC LIMIT 1",
            $post_id
        ));
    }
}
