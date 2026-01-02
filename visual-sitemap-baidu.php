<?php
/*
Plugin Name: 百度SEO优化
Plugin URI: https://github.com/sgvps-cn
Description: 完全免费全后台可视化配置，自动生成SEO友好Sitemap，百度快速收录，WordPress全站SEO优化，每天自动推送，伪原创设置
Version: 5.0.2
Author: 星耀云
Author URI: https://www.sgvps.cn
License: GPLv2
Text Domain: visual-sitemap-baidu-seo
Requires PHP: 7.2
Requires at least: 5.0
*/

// 防止直接访问
if (!defined('ABSPATH')) {
    exit('禁止直接访问！');
}

// 定义插件常量
define('VISUAL_SITEMAP_BAIDU_VERSION', '5.0.2');
define('VISUAL_SITEMAP_BAIDU_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('VISUAL_SITEMAP_BAIDU_PLUGIN_URL', plugin_dir_url(__FILE__));



require_once VISUAL_SITEMAP_BAIDU_PLUGIN_DIR . 'includes/class-settings-manager.php';
require_once VISUAL_SITEMAP_BAIDU_PLUGIN_DIR . 'includes/class-log-manager.php';
require_once VISUAL_SITEMAP_BAIDU_PLUGIN_DIR . 'includes/class-baidu-spider.php';
require_once VISUAL_SITEMAP_BAIDU_PLUGIN_DIR . 'includes/class-content-template.php';
require_once VISUAL_SITEMAP_BAIDU_PLUGIN_DIR . 'includes/class-spin-engine.php';
require_once VISUAL_SITEMAP_BAIDU_PLUGIN_DIR . 'includes/class-url-dedup.php';
require_once VISUAL_SITEMAP_BAIDU_PLUGIN_DIR . 'includes/class-spin-db.php';
require_once VISUAL_SITEMAP_BAIDU_PLUGIN_DIR . 'includes/class-sitemap-generator.php';
require_once VISUAL_SITEMAP_BAIDU_PLUGIN_DIR . 'includes/class-baidu-push.php';
require_once VISUAL_SITEMAP_BAIDU_PLUGIN_DIR . 'includes/class-robots-generator.php';
require_once VISUAL_SITEMAP_BAIDU_PLUGIN_DIR . 'includes/class-update-manager.php';
require_once VISUAL_SITEMAP_BAIDU_PLUGIN_DIR . 'includes/api-cache-clear.php';



require_once VISUAL_SITEMAP_BAIDU_PLUGIN_DIR . 'assets/admin-styles.php';
require_once VISUAL_SITEMAP_BAIDU_PLUGIN_DIR . 'admin/pages-main.php';
require_once VISUAL_SITEMAP_BAIDU_PLUGIN_DIR . 'admin/pages-settings.php';
require_once VISUAL_SITEMAP_BAIDU_PLUGIN_DIR . 'admin/pages-seo-settings.php';
require_once VISUAL_SITEMAP_BAIDU_PLUGIN_DIR . 'admin/pages-spin-settings.php';
require_once VISUAL_SITEMAP_BAIDU_PLUGIN_DIR . 'includes/class-update-diagnostic.php';



add_action('plugins_loaded', 'visual_sitemap_baidu_seo_init');
function visual_sitemap_baidu_seo_init() {
    // 加载文本域（国际化支持）
    load_plugin_textdomain('visual-sitemap-baidu-seo', false, dirname(plugin_basename(__FILE__)) . '/languages/');
    
    // 初始化配置
    VisualSitemap_SettingsManager::initSettings();
}



register_activation_hook(__FILE__, 'visual_sitemap_baidu_seo_activate');
function visual_sitemap_baidu_seo_activate() {
    // 检查权限
    if (!current_user_can('activate_plugins')) {
        return;
    }
    
    $log_manager = new VisualSitemap_LogManager();
    $log_manager->createTable();
    
    $url_dedup = new VisualSitemap_URLDedup();
    $url_dedup->createTable();
    
    $spin_db = new VisualSitemap_SpinDB();
    $spin_db->createTable();
    
    global $wpdb;
    $spider_table = $wpdb->prefix . 'visual_sitemap_baidu_spider_logs';
    
    $table_exists = $wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $spider_table));
    
    if (!$table_exists) {
        $sql = "CREATE TABLE {$spider_table} (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT,
            post_id BIGINT UNSIGNED DEFAULT 0,
            spider_type VARCHAR(50) NOT NULL,
            user_agent VARCHAR(500) DEFAULT '',
            ip_address VARCHAR(45) DEFAULT '',
            request_uri VARCHAR(500) DEFAULT '',
            visit_time DATETIME DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_post_id (post_id),
            KEY idx_spider_type (spider_type),
            KEY idx_visit_time (visit_time)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
    }
    
    wp_clear_scheduled_hook('visual_sitemap_baidu_cron');

    $settings = VisualSitemap_SettingsManager::getSettings();
    $hour = intval($settings['cron_hour']);
    $hour = ($hour < 0 || $hour > 23) ? VISUAL_SITEMAP_CRON_HOUR_DEFAULT : $hour; // 边界值校验

    $current_time = current_time('timestamp');
    $target_time = strtotime(date('Y-m-d ' . $hour . ':00:00', $current_time));

    if ($target_time <= $current_time) {
        $target_time = strtotime('+1 day', $target_time);
    }
    
    if (!wp_next_scheduled('visual_sitemap_baidu_cron')) {
        wp_schedule_event($target_time, 'daily', 'visual_sitemap_baidu_cron');
    }

    $generator = new VisualSitemap_SitemapGenerator();
    $generator->generate(false);
    
    $robots = new VisualSitemap_RobotsGenerator();
    $robots->generate();

    $update_manager = new VisualSitemap_UpdateManager();
    $update_manager->syncSiteInfo();

    $log = new VisualSitemap_LogManager();
    $log->log('activate', '插件激活成功，定时任务已设置在每天'.$hour.'点执行', 'success');
}



register_deactivation_hook(__FILE__, 'visual_sitemap_baidu_seo_deactivate');
function visual_sitemap_baidu_seo_deactivate() {
    // 检查权限
    if (!current_user_can('activate_plugins')) {
        return;
    }
    
    wp_clear_scheduled_hook('visual_sitemap_baidu_cron');
    
    $log = new VisualSitemap_LogManager();
    $log->log('deactivate', '插件停用，定时任务已清除', 'success');
}



register_uninstall_hook(__FILE__, 'visual_sitemap_baidu_seo_uninstall');
function visual_sitemap_baidu_seo_uninstall() {
    if (!defined('WP_UNINSTALL_PLUGIN')) {
        exit;
    }
    
    global $wpdb;
    
    $wpdb->query("DROP TABLE IF EXISTS {$wpdb->prefix}visual_sitemap_baidu_logs");
    $wpdb->query("DROP TABLE IF EXISTS {$wpdb->prefix}visual_sitemap_baidu_urls");
    $wpdb->query("DROP TABLE IF EXISTS {$wpdb->prefix}visual_sitemap_baidu_spin_logs");
    $wpdb->query("DROP TABLE IF EXISTS {$wpdb->prefix}visual_sitemap_baidu_spider_logs");
    
    delete_option('visual_sitemap_baidu_settings');
    wp_cache_delete('visual_sitemap_baidu_settings');
    
    $robots_path = ABSPATH . 'robots.txt';
    $robots_backup = ABSPATH . 'robots.txt.bak';
    
    if (file_exists($robots_backup)) {
        if (file_exists($robots_path)) {
            unlink($robots_path);
        }
        rename($robots_backup, $robots_path);
    }
}



add_action('admin_menu', 'visual_sitemap_baidu_seo_add_menu');
function visual_sitemap_baidu_seo_add_menu() {
    $main_page = add_menu_page(
        __('百度SEO优化', 'visual-sitemap-baidu-seo'),
        __('百度SEO优化', 'visual-sitemap-baidu-seo'),
        'manage_options',
        'visual-sitemap-baidu-seo',
        'visual_sitemap_baidu_main_page',
        'dashicons-admin-generic',
        60
    );
    
    $settings_page = add_submenu_page(
        'visual-sitemap-baidu-seo',
        __('插件配置', 'visual-sitemap-baidu-seo'),
        __('插件配置', 'visual-sitemap-baidu-seo'),
        'manage_options',
        'visual-sitemap-baidu-settings',
        'visual_sitemap_baidu_settings_page'
    );
    
    $seo_settings_page = add_submenu_page(
        'visual-sitemap-baidu-seo',
        __('SEO优化设置', 'visual-sitemap-baidu-seo'),
        __('SEO优化设置', 'visual-sitemap-baidu-seo'),
        'manage_options',
        'visual-sitemap-baidu-seo-settings',
        'visual_sitemap_baidu_seo_settings_page'
    );
    
    $spin_settings_page = add_submenu_page(
        'visual-sitemap-baidu-seo',
        __('伪原创设置', 'visual-sitemap-baidu-seo'),
        __('伪原创设置', 'visual-sitemap-baidu-seo'),
        'manage_options',
        'visual-sitemap-baidu-spin-settings',
        'visual_sitemap_baidu_spin_settings_page'
    );

    add_action('load-' . $main_page, 'visual_sitemap_baidu_load_admin_assets');
    add_action('load-' . $settings_page, 'visual_sitemap_baidu_load_admin_assets');
    add_action('load-' . $seo_settings_page, 'visual_sitemap_baidu_load_admin_assets');
    add_action('load-' . $spin_settings_page, 'visual_sitemap_baidu_load_admin_assets');
}

// ============================================================
// 定时任务钩子
// ============================================================

add_action('visual_sitemap_baidu_cron', 'visual_sitemap_baidu_cron_job');
function visual_sitemap_baidu_cron_job() {
    // 执行Sitemap生成和推送
    $generator = new VisualSitemap_SitemapGenerator();
    $generator->generate(true);
    
    // 重新生成robots.txt（如果启用）
    $settings = VisualSitemap_SettingsManager::getSettings();
    if ($settings['enable_robots']) {
        $robots = new VisualSitemap_RobotsGenerator();
        $robots->generate();
    }
}

// ============================================================
// 发布/更新文章时自动推送
// ============================================================

add_action('publish_post', 'visual_sitemap_baidu_auto_push_on_publish', 10, 2);
function visual_sitemap_baidu_auto_push_on_publish($post_id, $post = null) {
    // 参数验证
    if (wp_is_post_revision($post_id) || wp_is_post_autosave($post_id)) {
        return;
    }
    
    $settings = VisualSitemap_SettingsManager::getSettings();
    if (!$settings['enable_auto_push']) {
        return;
    }
    
    // 获取文章URL
    $url = get_permalink($post_id);
    if (!$url) {
        $log = new VisualSitemap_LogManager();
        $log->log('auto_push', '自动推送失败：无法获取文章URL（ID='.$post_id.')', 'fail');
        return;
    }
    
    $urls = array(array('loc' => $url));
    
    // 推送百度
    $baidu_push = new VisualSitemap_BaiduPush();
    $result = $baidu_push->push($urls);
    
    $log = new VisualSitemap_LogManager();
    if ($result) {
        $log->log('auto_push', '文章发布自动推送成功：'.$url, 'success');
    } else {
        $log->log('auto_push', '文章发布自动推送失败：'.$url, 'fail');
    }
}

// 更新文章时也推送
add_action('post_updated', 'visual_sitemap_baidu_auto_push_on_update', 10, 3);
function visual_sitemap_baidu_auto_push_on_update($post_id, $post_after, $post_before) {
    if ($post_after->post_status != 'publish' || $post_before->post_status == 'publish') {
        return;
    }
    
    visual_sitemap_baidu_auto_push_on_publish($post_id, $post_after);
}

// ============================================================
// 插件设置链接
// ============================================================

add_filter('plugin_action_links_' . plugin_basename(__FILE__), 'visual_sitemap_baidu_add_action_links');
function visual_sitemap_baidu_add_action_links($links) {
    $settings_link = '<a href="' . admin_url('admin.php?page=visual-sitemap-baidu-seo') . '">' . __('设置', 'visual-sitemap-baidu-seo') . '</a>';
    array_unshift($links, $settings_link);
    return $links;
}

// ============================================================
// AJAX - 检查插件更新
// ============================================================

add_action('wp_ajax_visual_sitemap_baidu_check_update', 'visual_sitemap_baidu_ajax_check_update');
function visual_sitemap_baidu_ajax_check_update() {
    check_ajax_referer('visual_sitemap_baidu_check_update', 'nonce');

    if (!current_user_can('manage_options')) {
        wp_send_json_error(array('message' => '权限不足'));
    }

    $update_manager = new VisualSitemap_UpdateManager();
    // 强制刷新缓存
    $update_info = $update_manager->checkForUpdate(true);

    if (isset($update_info['error'])) {
        wp_send_json_error(array('message' => $update_info['error']));
    }

    wp_send_json_success(array(
        'has_update' => isset($update_info['has_update']) ? $update_info['has_update'] : false,
        'new_version' => isset($update_info['new_version']) ? $update_info['new_version'] : ''
    ));
}

// ============================================================
// AJAX - 执行一键更新
// ============================================================

add_action('wp_ajax_visual_sitemap_baidu_perform_update', 'visual_sitemap_baidu_ajax_perform_update');
function visual_sitemap_baidu_ajax_perform_update() {
    try {
        check_ajax_referer('visual_sitemap_baidu_perform_update', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => '权限不足'));
        }

        // 增加PHP执行时间限制
        @set_time_limit(300);

        // 增加内存限制
        @ini_set('memory_limit', '256M');

        // 禁用输出缓冲，防止内存问题
        if (ob_get_level()) {
            @ob_end_clean();
        }

        $update_manager = new VisualSitemap_UpdateManager();
        $result = $update_manager->performUpdate();

        if ($result['success']) {
            // 清理旧备份（使用try-catch保护）
            try {
                $update_manager->cleanupOldBackups(7);
            } catch (Exception $e) {
                error_log('VisualSitemap: 清理旧备份失败（非致命） - ' . $e->getMessage());
                // 不影响更新成功
            }

            wp_send_json_success(array(
                'message' => $result['message'],
                'new_version' => $result['new_version']
            ));
        } else {
            wp_send_json_error(array(
                'message' => $result['message'] ?? '更新失败：未知错误'
            ));
        }
    } catch (Exception $e) {
        error_log('VisualSitemap: 一键更新异常 - ' . $e->getMessage());
        error_log('VisualSitemap: 异常堆栈 - ' . $e->getTraceAsString());
        wp_send_json_error(array(
            'message' => '更新失败：' . $e->getMessage()
        ));
    }
}

// ============================================================
// AJAX - 恢复备份
// ============================================================

add_action('wp_ajax_visual_sitemap_baidu_restore_backup', 'visual_sitemap_baidu_ajax_restore_backup');
function visual_sitemap_baidu_ajax_restore_backup() {
    try {
        check_ajax_referer('visual_sitemap_baidu_restore_backup', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => '权限不足'));
        }

        // 增加PHP执行时间限制
        @set_time_limit(120);

        // 增加内存限制
        @ini_set('memory_limit', '256M');

        $update_manager = new VisualSitemap_UpdateManager();
        $result = $update_manager->restoreBackup();

        if ($result) {
            wp_send_json_success(array(
                'message' => '备份已恢复，插件已回滚到之前版本'
            ));
        } else {
            wp_send_json_error(array(
                'message' => '恢复失败，备份文件不存在或已过期'
            ));
        }
    } catch (Exception $e) {
        error_log('VisualSitemap: 恢复备份异常 - ' . $e->getMessage());
        error_log('VisualSitemap: 异常堆栈 - ' . $e->getTraceAsString());
        wp_send_json_error(array(
            'message' => '恢复失败：' . $e->getMessage()
        ));
    }
}

// ============================================================
// 百度蜘蛛检测钩子
// ============================================================

add_action('wp', 'visual_sitemap_baidu_spider_detect');
function visual_sitemap_baidu_spider_detect() {
    if (is_singular()) {
        VisualSitemap_BaiduSpider::logSpiderVisit(get_the_ID());
    }
}

// ============================================================
// 发布文章时添加URL记录
// ============================================================

add_action('publish_post', 'visual_sitemap_baidu_add_url_on_publish', 10, 2);
function visual_sitemap_baidu_add_url_on_publish($post_id, $post) {
    if ($post->post_status !== 'publish') {
        return;
    }
    
    $url = get_permalink($post_id);
    $content = $post->post_content;
    
    $url_dedup = new VisualSitemap_URLDedup();
    $url_dedup->addURL($url, $post_id, $content);
}

// ============================================================
// URL推送后标记状态
// ============================================================

add_action('visual_sitemap_baidu_after_push', 'visual_sitemap_baidu_mark_urls_pushed');
function visual_sitemap_baidu_mark_urls_pushed($urls) {
    $url_dedup = new VisualSitemap_URLDedup();
    
    foreach ($urls as $url_data) {
        if (isset($url_data['loc'])) {
            $url_dedup->markPushed($url_data['loc']);
        }
    }
}

// ============================================================
// 百度蜘蛛访问标记
// ============================================================

add_action('wp_head', 'visual_sitemap_baidu_mark_spider_visited');
function visual_sitemap_baidu_mark_spider_visited() {
    if (!VisualSitemap_BaiduSpider::isBaiduSpider()) {
        return;
    }
    
    if (is_singular()) {
        $url = get_permalink();
        $url_dedup = new VisualSitemap_URLDedup();
        $url_dedup->markSpiderVisited($url);
    }
}

// ============================================================
// 内容输出时应用伪原创（仅对百度蜘蛛）
// ============================================================

add_filter('the_content', 'visual_sitemap_baidu_apply_spin_to_baidu', 999);
function visual_sitemap_baidu_apply_spin_to_baidu($content) {
    // 仅对百度蜘蛛应用伪原创
    if (!VisualSitemap_BaiduSpider::isBaiduSpider()) {
        return $content;
    }
    
    $settings = get_option('visual_sitemap_baidu_settings');
    
    // 检查是否启用伪原创功能
    if (empty($settings['enable_spintax'])) {
        return $content;
    }
    
    global $post;
    
    if (!$post) {
        return $content;
    }
    
    $post_id = $post->ID;
    
    // 检查是否已缓存伪原创结果
    $spin_db = new VisualSitemap_SpinDB();
    $spin_log = $spin_db->getSpinLog($post_id);
    
    if ($spin_log) {
        return $spin_log->spun_content;
    }
    
    // 执行伪原创处理
    $spin_level = !empty($settings['spin_level']) ? intval($settings['spin_level']) : 2;
    $spin_mode = !empty($settings['spin_mode']) ? $settings['spin_mode'] : 'word';
    $template_type = VisualSitemap_ContentTemplate::getRecommendedTemplate($post_id);
    
    $engine = new VisualSitemap_SpinEngine($spin_level, $spin_mode);
    
    // 加载自定义同义词库
    if (!empty($settings['custom_synonyms'])) {
        $custom_synonyms = json_decode($settings['custom_synonyms'], true);
        if (is_array($custom_synonyms)) {
            $engine->loadCustomSynonyms($custom_synonyms);
        }
    }
    
    // 应用模板
    $templated_content = VisualSitemap_ContentTemplate::applyTemplate($content, $template_type, $post_id);
    
    // 执行伪原创
    $spun_content = $engine->spin($templated_content);
    
    // 计算伪原创度
    $spin_degree = $engine->calculateSpinDegree($templated_content, $spun_content);
    
    // 保存记录
    $spin_db->saveSpinLog($post_id, $templated_content, $spun_content, $spin_degree, $spin_level, $spin_mode, $template_type);
    
    // 记录日志
    $log = new VisualSitemap_LogManager();
    $log->log('spin', "文章[{$post_id}]伪原创完成，伪原创度：{$spin_degree}%", 'success');
    
    return $spun_content;
}


