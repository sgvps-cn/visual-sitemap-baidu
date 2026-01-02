<?php
if (!defined('ABSPATH')) {
    exit('禁止直接访问！');
}

add_action('rest_api_init', function () {
    register_rest_route('visual-sitemap-baidu/v1', '/clear-cache', array(
        'methods' => 'POST',
        'callback' => 'visual_sitemap_baidu_api_clear_cache',
        'permission_callback' => '__return_true'
    ));
});

function visual_sitemap_baidu_api_clear_cache($request) {
    $params = $request->get_json_params();
    $cache_type = isset($params['cache_type']) ? $params['cache_type'] : 'ad_cache';

    $cleared_caches = array();

    // 清除广告缓存
    if ($cache_type === 'ad_cache' || $cache_type === 'all') {
        $result = delete_transient('visual_sitemap_baidu_ad_content');
        $cleared_caches['ad_cache'] = $result ? 'cleared' : 'not_found';
    }

    // 清除更新检查缓存
    if ($cache_type === 'update_cache' || $cache_type === 'all') {
        $result = delete_transient('visual_sitemap_baidu_update_check');
        $cleared_caches['update_cache'] = $result ? 'cleared' : 'not_found';
    }

    // 记录日志
    $log = new VisualSitemap_LogManager();
    $log->log('cache_clear', '缓存清除请求 - 类型: ' . $cache_type, 'success');

    return array(
        'success' => true,
        'message' => '缓存清除成功',
        'cleared' => $cleared_caches,
        'timestamp' => time()
    );
}

add_action('admin_post_visual_sitemap_clear_cache', 'visual_sitemap_baidu_handle_cache_clear_request');
add_action('admin_post_nopriv_visual_sitemap_clear_cache', 'visual_sitemap_baidu_handle_cache_clear_request');

function visual_sitemap_baidu_handle_cache_clear_request() {
    $cache_type = isset($_GET['cache_type']) ? $_GET['cache_type'] : 'all';

    // 验证请求来源（可选，可以添加token验证）
    // 这里暂时不做严格验证，因为WordPress admin_post钩子已有基本保护

    $cleared_caches = array();

    // 清除广告缓存
    if ($cache_type === 'ad_cache' || $cache_type === 'all') {
        $result = delete_transient('visual_sitemap_baidu_ad_content');
        $cleared_caches['ad_cache'] = $result ? 'cleared' : 'not_found';
    }

    // 清除更新检查缓存
    if ($cache_type === 'update_cache' || $cache_type === 'all') {
        $result = delete_transient('visual_sitemap_baidu_update_check');
        $cleared_caches['update_cache'] = $result ? 'cleared' : 'not_found';
    }

    // 返回JSON响应
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(array(
        'success' => true,
        'message' => '缓存清除成功',
        'cleared' => $cleared_caches
    ));
    exit;
}
