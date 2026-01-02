<?php
if (!defined('ABSPATH')) {
    exit('禁止直接访问！');
}

class VisualSitemap_LogManager {

    private $table_name;

    public function __construct() {
        global $wpdb;
        $this->table_name = $wpdb->prefix . 'visual_sitemap_baidu_logs';
    }

    public function createTable() {
        global $wpdb;
        
        $sql = "CREATE TABLE IF NOT EXISTS {$this->table_name} (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT,
            action VARCHAR(50) NOT NULL COMMENT '操作类型',
            content TEXT NOT NULL COMMENT '操作内容',
            status VARCHAR(20) NOT NULL COMMENT '操作状态',
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',
            PRIMARY KEY (id),
            INDEX idx_created_at (created_at),
            INDEX idx_status (status)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
        
        return true;
    }

    public function log($action, $content, $status) {
        // 参数验证
        if (empty($action) || empty($content) || empty($status)) {
            return false;
        }
        
        global $wpdb;
        
        // 清理内容长度
        $action = substr(sanitize_text_field($action), 0, 50);
        $content = sanitize_textarea_field($content);
        $status = substr(sanitize_text_field($status), 0, 20);
        
        // 插入日志
        $result = $wpdb->insert($this->table_name, array(
            'action' => $action,
            'content' => $content,
            'status' => $status
        ));
        
        // 清理旧日志
        $settings = VisualSitemap_SettingsManager::getSettings();
        $log_limit = intval($settings['log_limit']);
        
        if ($log_limit > 0) {
            $wpdb->query($wpdb->prepare(
                "DELETE FROM {$this->table_name} WHERE id NOT IN (
                    SELECT id FROM (
                        SELECT id FROM {$this->table_name} ORDER BY created_at DESC LIMIT %d
                    ) AS temp
                )",
                $log_limit
            ));
        }
        
        return $result !== false;
    }

    public function getLogs($limit = 50) {
        global $wpdb;
        
        $settings = VisualSitemap_SettingsManager::getSettings();
        $log_limit = intval($settings['log_limit']);
        
        return $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$this->table_name} ORDER BY created_at DESC LIMIT %d",
            $log_limit
        ), ARRAY_A);
    }

    public function clearLogs() {
        global $wpdb;
        
        $result = $wpdb->query("TRUNCATE TABLE {$this->table_name}");
        
        return $result !== false;
    }
}
