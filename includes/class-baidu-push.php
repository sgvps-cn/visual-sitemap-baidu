<?php
if (!defined('ABSPATH')) {
    exit('禁止直接访问！');
}

class VisualSitemap_BaiduPush {

    public function push($urls) {
        // 参数验证
        if (!is_array($urls) || empty($urls)) {
            $log = new VisualSitemap_LogManager();
            $log->log('push', '推送失败：URL列表为空', 'fail');
            return false;
        }
        
        $settings = VisualSitemap_SettingsManager::getSettings();
        $api_url = $settings['baidu_api_url'];
        $site_url = VisualSitemap_SettingsManager::getSiteURL();
        
        // 接口地址验证
        if (!VisualSitemap_SettingsManager::validateAPIUrl($api_url)) {
            $msg = '百度推送失败：接口地址格式错误（需包含data.zz.baidu.com、site和token参数）';
            $log = new VisualSitemap_LogManager();
            $log->log('push', $msg, 'fail');
            return false;
        }

        $url_list = array_column($urls, 'loc');
        $url_list = array_unique($url_list); // 去重
        $url_chunks = array_chunk($url_list, 2000); // 百度限制每次最多2000条
        $total_success = 0;

        foreach ($url_chunks as $chunk) {
            $post_data = implode("\n", $chunk);
            $response = wp_remote_post($api_url, array(
                'headers' => array(
                    'Content-Type' => 'text/plain',
                    'User-Agent' => 'Mozilla/5.0 (compatible; Baiduspider/2.0; +http://www.baidu.com/search/spider.html)'
                ),
                'body' => $post_data,
                'timeout' => 15,
                'sslverify' => true,
                'compress' => true // 启用压缩
            ));

            // 处理响应
            if (is_wp_error($response)) {
                $error_msg = $response->get_error_message();
                $log = new VisualSitemap_LogManager();
                $log->log('push', "推送失败：{$error_msg}（批次）", 'fail');
                continue;
            }

            $body = wp_remote_retrieve_body($response);
            $result = json_decode($body, true);
            
            if (isset($result['success'])) {
                $total_success += $result['success'];
                $msg = "推送成功，本次推送{$result['success']}条，剩余配额{$result['remain']}条";
                $log = new VisualSitemap_LogManager();
                $log->log('push', $msg, 'success');
            } else {
                $log = new VisualSitemap_LogManager();
                $log->log('push', "推送失败：{$body}", 'fail');
            }
        }

        if ($total_success > 0) {
            $log = new VisualSitemap_LogManager();
            $log->log('push', "全部推送完成，总计成功{$total_success}条URL", 'success');
        }
        
        // 触发钩子标记URL已推送
        do_action('visual_sitemap_baidu_after_push', $urls);
        
        return $total_success > 0;
    }

    public function validateAPI() {
        $settings = VisualSitemap_SettingsManager::getSettings();
        $api_url = $settings['baidu_api_url'];
        $site_url = VisualSitemap_SettingsManager::getSiteURL();
        
        if (!VisualSitemap_SettingsManager::validateAPIUrl($api_url)) {
            return array(
                'success' => false,
                'message' => '接口地址格式错误！请填写完整的百度推送接口地址（包含site和token参数）'
            );
        }
        
        $response = wp_remote_post($api_url, array(
            'headers' => array('Content-Type' => 'text/plain'),
            'body' => $site_url,
            'timeout' => 10,
            'sslverify' => true,
            'user-agent' => 'WordPress/Visual-Sitemap-Baidu-SEO/3.2'
        ));
        
        if (is_wp_error($response)) {
            return array(
                'success' => false,
                'message' => '接口验证失败：' . $response->get_error_message()
            );
        }
        
        $body = wp_remote_retrieve_body($response);
        $result = json_decode($body, true);
        
        if (isset($result['success']) && $result['success'] >= 0) {
            return array(
                'success' => true,
                'message' => '接口验证成功！返回结果：' . json_encode($result)
            );
        } else {
            return array(
                'success' => false,
                'message' => '接口验证失败：' . $body
            );
        }
    }
}
