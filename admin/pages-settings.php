<?php
if (!defined('ABSPATH')) {
    exit('禁止直接访问！');
}

function visual_sitemap_baidu_settings_page() {
    // 权限检查
    if (!current_user_can('manage_options')) {
        wp_die(__('您没有足够的权限访问此页面！', 'visual-sitemap-baidu-seo'));
    }
    
    $messages = array();

    // 保存配置
    if (isset($_POST['visual_sitemap_baidu_save_settings'])) {
        check_admin_referer('visual_sitemap_baidu_settings_nonce');
        
        $settings = VisualSitemap_SettingsManager::getSettings();
        
        // 安全过滤和验证
        $settings['baidu_api_url'] = esc_url_raw($_POST['baidu_api_url']);
        $settings['cron_hour'] = intval($_POST['cron_hour']);
        $settings['ignore_urls'] = sanitize_textarea_field($_POST['ignore_urls']);
        $settings['log_limit'] = intval($_POST['log_limit']);
        
        // 边界值校验
        $settings['cron_hour'] = ($settings['cron_hour'] < 0 || $settings['cron_hour'] > 23) ? VISUAL_SITEMAP_CRON_HOUR_DEFAULT : $settings['cron_hour'];
        $settings['log_limit'] = ($settings['log_limit'] < VISUAL_SITEMAP_LOG_LIMIT_MIN || $settings['log_limit'] > VISUAL_SITEMAP_LOG_LIMIT_MAX) ? VISUAL_SITEMAP_LOG_LIMIT_DEFAULT : $settings['log_limit'];
        
        // 更新配置
        VisualSitemap_SettingsManager::saveSettings($settings);
        
        // 更新定时任务
        wp_clear_scheduled_hook('visual_sitemap_baidu_cron');
        $hour = intval($settings['cron_hour']);
        
        $current_time = current_time('timestamp');
        $target_time = strtotime(date('Y-m-d ' . $hour . ':00:00', $current_time));
        
        if ($target_time <= $current_time) {
            $target_time = strtotime('+1 day', $target_time);
        }
        
        wp_schedule_event($target_time, 'daily', 'visual_sitemap_baidu_cron');
        
        $messages[] = array(
            'type' => 'updated',
            'text' => __('配置保存成功！定时任务已更新', 'visual-sitemap-baidu-seo')
        );
        
        // 记录日志
        $log = new VisualSitemap_LogManager();
        $log->log('settings', '基础配置已更新：执行时间='.$hour.'点，日志保留='.$settings['log_limit'].'条', 'success');
    }

    // 获取当前配置和自动域名
    $settings = VisualSitemap_SettingsManager::getSettings();
    $site_url = VisualSitemap_SettingsManager::getSiteURL();
    
    // 输出页面
    ?>
    <div class="wrap">
        <h1><?php _e('插件基础配置', 'visual-sitemap-baidu-seo'); ?></h1>
        
        <?php
        // 显示消息提示
        foreach ($messages as $msg) {
            echo "<div class='{$msg['type']} notice is-dismissible'><p>{$msg['text']}</p></div>";
        }
        ?>
        
        <div class="vseo-card" style="max-width:800px;">
            <!-- 自动获取域名提示 -->
            <div class="vseo-status-info">
                <p style="margin:0;"><strong><?php _e('自动获取站点域名：', 'visual-sitemap-baidu-seo'); ?></strong><?php echo esc_url($site_url); ?></p>
                <p style="margin:5px 0 0 0;color:#666;"><?php _e('提示：请确保百度接口地址中的site参数和该域名一致！', 'visual-sitemap-baidu-seo'); ?></p>
            </div>
            
            <form method="post">
                <?php wp_nonce_field('visual_sitemap_baidu_settings_nonce'); ?>
                
                <!-- 百度接口配置（完整地址） -->
                <div class="vseo-section">
                    <h3 class="vseo-section-title"><?php _e('百度推送接口配置', 'visual-sitemap-baidu-seo'); ?></h3>
                    <table class="vseo-table">
                        <tr valign="top">
                            <th scope="row" style="width:200px;"><?php _e('完整接口地址', 'visual-sitemap-baidu-seo'); ?></th>
                            <td>
                                <input type="url" name="baidu_api_url" value="<?php echo esc_attr($settings['baidu_api_url']); ?>" class="regular-text" placeholder="http://data.zz.baidu.com/urls?site=https://www.sgvps.cn&token=xxx" required>
                                <p class="vseo-description"><?php _e('从百度搜索资源平台复制完整的API推送地址（包含site和token参数）', 'visual-sitemap-baidu-seo'); ?></p>
                                <p class="vseo-description" style="color:red;font-weight:bold;"><?php _e('示例：', 'visual-sitemap-baidu-seo'); ?> http://data.zz.baidu.com/urls?site=https://www.sgvps.cn&token=xxx</p>
                            </td>
                        </tr>
                    </table>
                </div>

                <!-- 自动执行配置 -->
                <div class="vseo-section">
                    <h3 class="vseo-section-title"><?php _e('自动执行配置', 'visual-sitemap-baidu-seo'); ?></h3>
                    <table class="vseo-table">
                        <tr valign="top">
                            <th scope="row" style="width:200px;"><?php _e('执行小时', 'visual-sitemap-baidu-seo'); ?></th>
                            <td>
                                <input type="number" name="cron_hour" value="<?php echo intval($settings['cron_hour']); ?>" min="0" max="23" class="small-text">
                                <p class="vseo-description"><?php _e('每天几点执行（0=凌晨0点，3=凌晨3点，23=晚上11点）', 'visual-sitemap-baidu-seo'); ?></p>
                            </td>
                        </tr>
                    </table>
                </div>

                <!-- 高级配置 -->
                <div class="vseo-section">
                    <h3 class="vseo-section-title"><?php _e('URL过滤配置', 'visual-sitemap-baidu-seo'); ?></h3>
                    <table class="vseo-table">
                        <tr valign="top">
                            <th scope="row" style="width:200px;"><?php _e('忽略URL关键词', 'visual-sitemap-baidu-seo'); ?></th>
                            <td>
                                <textarea name="ignore_urls" rows="4" class="regular-text"><?php echo esc_textarea($settings['ignore_urls']); ?></textarea>
                                <p class="vseo-description"><?php _e('每行一个关键词，包含这些关键词的URL不会被收录（SEO优化：过滤低质量URL）', 'visual-sitemap-baidu-seo'); ?></p>
                            </td>
                        </tr>
                        <tr valign="top">
                            <th scope="row"><?php _e('日志保留数量', 'visual-sitemap-baidu-seo'); ?></th>
                            <td>
                                <input type="number" name="log_limit" value="<?php echo intval($settings['log_limit']); ?>" min="10" max="500" class="small-text">
                                <p class="vseo-description"><?php _e('最多保留多少条日志（建议50-100）', 'visual-sitemap-baidu-seo'); ?></p>
                            </td>
                        </tr>
                    </table>
                </div>

                <!-- 保存按钮 -->
                <p class="submit">
                    <input type="submit" name="visual_sitemap_baidu_save_settings" class="button button-primary" value="<?php _e('保存配置', 'visual-sitemap-baidu-seo'); ?>">
                </p>
            </form>
        </div>
    </div>
    <?php
}
