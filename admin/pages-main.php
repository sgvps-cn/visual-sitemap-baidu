<?php
if (!defined('ABSPATH')) {
    exit('禁止直接访问！');
}

function visual_sitemap_baidu_get_actual_version() {
    $plugin_file = VISUAL_SITEMAP_BAIDU_PLUGIN_DIR . 'visual-sitemap-baidu.php';

    if (!file_exists($plugin_file)) {
        return VISUAL_SITEMAP_BAIDU_VERSION;
    }

    // 只读取前100行，避免读取大文件
    $content = '';
    $fp = @fopen($plugin_file, 'r');
    if ($fp) {
        for ($i = 0; $i < 100 && !feof($fp); $i++) {
            $content .= fgets($fp, 1024);
        }
        fclose($fp);
    }

    if (empty($content)) {
        return VISUAL_SITEMAP_BAIDU_VERSION;
    }

    // 优先从常量定义中读取
    if (preg_match("/define\(['\"]VISUAL_SITEMAP_BAIDU_VERSION['\"],\s*['\"]([^'\"]+)['\"]\)/", $content, $matches)) {
        return $matches[1];
    }

    // 从注释头读取
    if (preg_match('/Version:\s*([0-9.]+)/', $content, $matches)) {
        return $matches[1];
    }

    return VISUAL_SITEMAP_BAIDU_VERSION;
}

function visual_sitemap_baidu_get_ad_content() {
    $cache_key = 'visual_sitemap_baidu_ad_content';
    $cached = get_transient($cache_key);

    if ($cached !== false) {
        return $cached;
    }

    $api_url = 'https://api.sgvps.cn/api/ad.php';
    $site_url = home_url();

    $response = wp_remote_get($api_url, array(
        'timeout' => 10,
        'sslverify' => true,
        'headers' => array(
            'X-Site-URL' => $site_url,
            'X-Plugin-Version' => VISUAL_SITEMAP_BAIDU_VERSION,
            'X-Plugin-Slug' => 'visual-sitemap-baidu-seo'
        )
    ));

    if (is_wp_error($response)) {
        return array();
    }

    $body = wp_remote_retrieve_body($response);
    $data = json_decode($body, true);

    if (json_last_error() !== JSON_ERROR_NONE) {
        error_log('Ad API JSON Error: ' . json_last_error_msg() . ' - Response: ' . $body);
        return array();
    }

    if (!isset($data['success']) || $data['success'] !== true || !isset($data['ads']) || !is_array($data['ads'])) {
        error_log('Ad API Response Error: Invalid data format');
        return array();
    }

    // 验证和过滤广告数据，防止XSS攻击
    $ads = array();
    $trusted_domains = array('sgvps.cn', 'www.sgvps.cn');

    foreach ($data['ads'] as $ad) {
        if (!is_array($ad) || !isset($ad['url']) || !isset($ad['title']) || !isset($ad['description'])) {
            continue;
        }

        // 验证URL格式和域名
        $url = esc_url_raw($ad['url']);
        if (empty($url)) {
            continue;
        }

        $parsed_url = parse_url($url);
        if (!isset($parsed_url['host'])) {
            continue;
        }

        // 只允许来自可信域名的链接
        $url_host = $parsed_url['host'];
        $is_trusted = false;
        foreach ($trusted_domains as $domain) {
            if ($url_host === $domain || strpos($url_host, '.' . $domain) !== false) {
                $is_trusted = true;
                break;
            }
        }

        if (!$is_trusted) {
            continue;
        }

        // 过滤标题和描述
        $ads[] = array(
            'url' => $url,
            'title' => sanitize_text_field($ad['title']),
            'description' => sanitize_text_field($ad['description'])
        );

        if (count($ads) >= 6) {
            break;
        }
    }

    set_transient($cache_key, $ads, HOUR_IN_SECONDS);

    return $ads;
}

function visual_sitemap_baidu_clear_ad_cache() {
    delete_transient('visual_sitemap_baidu_ad_content');
    return true;
}

function visual_sitemap_baidu_main_page() {
    // 自动清除广告缓存,确保显示最新内容
    visual_sitemap_baidu_clear_ad_cache();

    // 权限检查
    if (!current_user_can('manage_options')) {
        wp_die(__('您没有足够的权限访问此页面！', 'visual-sitemap-baidu-seo'));
    }

    // 处理表单提交
    $messages = visual_sitemap_baidu_main_handle_form();
    
    // 获取配置和定时信息
    $settings = VisualSitemap_SettingsManager::getSettings();
    $site_url = VisualSitemap_SettingsManager::getSiteURL();
    $api_url_valid = VisualSitemap_SettingsManager::validateAPIUrl($settings['baidu_api_url']);
    $next_cron = wp_next_scheduled('visual_sitemap_baidu_cron');
    $perm_errors = VisualSitemap_SettingsManager::checkPermissions();

    // 从插件文件读取实际版本号
    $actual_version = visual_sitemap_baidu_get_actual_version();

    // 检查插件更新 - 如果刚更新过（1分钟内），跳过更新检查
    $just_updated = get_transient('visual_sitemap_baidu_just_updated');
    $update_info = array('has_update' => false);

    if (!$just_updated) {
        // 不强制刷新，使用缓存
        $update_manager = new VisualSitemap_UpdateManager();
        $update_info = $update_manager->checkForUpdate(false);
    }
    ?>
    <div class="wrap">
        <div style="display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 10px; margin-bottom: 20px;">
            <h1 style="margin: 0;"><?php _e('百度SEO优化', 'visual-sitemap-baidu-seo'); ?></h1>
            <div style="display: flex; align-items: center; gap: 10px; flex-wrap: wrap;">
                <span class="vseo-version-badge" style="background: #2271b1; color: white; padding: 5px 12px; border-radius: 4px; font-size: 13px; font-weight: 500;">
                    当前版本: <?php echo esc_html($actual_version); ?>
                </span>
                <?php if ($update_info && isset($update_info['has_update']) && $update_info['has_update']): ?>
                <span class="vseo-update-badge" style="background: #d63638; color: white; padding: 5px 12px; border-radius: 4px; font-size: 13px; font-weight: 500;">
                    🔔 新版本: <?php echo esc_html($update_info['new_version']); ?>
                </span>
                <?php endif; ?>
                <span class="vseo-last-check" style="color: #666; font-size: 12px;">
                    最后检测: <?php echo date_i18n('Y-m-d H:i:s', current_time('timestamp')); ?>
                </span>
                <button type="button" id="vseo-check-update" class="button button-small" onclick="visualSitemapCheckUpdate()">
                    检查更新
                </button>

            </div>
        </div>

        <script type="text/javascript">
        function visualSitemapCheckUpdate() {
            var btn = document.getElementById('vseo-check-update');
            btn.disabled = true;
            btn.textContent = '检测中...';

            fetch('<?php echo admin_url('admin-ajax.php'); ?>', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: 'action=visual_sitemap_baidu_check_update&nonce=<?php echo wp_create_nonce('visual_sitemap_baidu_check_update'); ?>'
            })
            .then(response => response.json())
            .then(data => {
                btn.disabled = false;
                btn.textContent = '检查更新';

                if (data.success) {
                    // 强制刷新，不使用缓存
                    setTimeout(function() {
                        location.reload(true);
                    }, 500);
                } else {
                    alert(data.message || '检查更新失败');
                }
            })
            .catch(error => {
                btn.disabled = false;
                btn.textContent = '检查更新';
                alert('网络错误，请稍后重试');
            });
        }

        function visualSitemapPerformUpdate() {
            if (!confirm('确认要更新到最新版本吗？\n\n更新过程中请不要关闭浏览器，系统会自动备份当前版本。')) {
                return;
            }

            var btn = document.getElementById('vseo-one-click-update');
            var progressBar = document.getElementById('vseo-progress-bar');
            var progressText = document.getElementById('vseo-progress-text');
            var progressContainer = document.getElementById('vseo-update-progress');

            // 禁用按钮并显示进度
            btn.disabled = true;
            btn.textContent = '更新中...';
            progressContainer.style.display = 'block';

            // 模拟进度显示
            var progress = 0;
            var progressSteps = [
                { progress: 10, text: '正在备份当前版本...' },
                { progress: 20, text: '正在下载更新包...' },
                { progress: 40, text: '正在解压更新文件...' },
                { progress: 60, text: '正在更新文件...' },
                { progress: 80, text: '正在清理临时文件...' },
                { progress: 90, text: '更新完成，正在验证...' },
                { progress: 100, text: '更新成功！' }
            ];

            var stepIndex = 0;

            function updateProgress() {
                if (stepIndex < progressSteps.length) {
                    var step = progressSteps[stepIndex];
                    progressBar.style.width = step.progress + '%';
                    progressText.textContent = step.text;
                    stepIndex++;
                    setTimeout(updateProgress, 800);
                }
            }

            updateProgress();

            // 发送更新请求
            fetch('<?php echo admin_url('admin-ajax.php'); ?>', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: 'action=visual_sitemap_baidu_perform_update&nonce=<?php echo wp_create_nonce('visual_sitemap_baidu_perform_update'); ?>'
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // 显示成功提示
                    document.getElementById('vseo-update-notice').style.display = 'none';
                    document.getElementById('vseo-update-success').style.display = 'block';
                    document.getElementById('vseo-success-message').textContent = data.message;

                    // 不自动刷新，让用户手动刷新
                    // 这样可以避免刷新时PHP文件还没完全加载的问题
                } else {
                    alert('更新失败：' + (data.message || '未知错误'));
                    btn.disabled = false;
                    btn.textContent = '✨ 一键更新';
                    progressContainer.style.display = 'none';
                    progressBar.style.width = '0%';
                }
            })
            .catch(error => {
                alert('网络错误，请稍后重试');
                btn.disabled = false;
                btn.textContent = '✨ 一键更新';
                progressContainer.style.display = 'none';
                progressBar.style.width = '0%';
            });
        }

        function visualSitemapRestoreBackup() {
            if (!confirm('确认要恢复到更新前的版本吗？\n\n这将撤销最近的更新操作。')) {
                return;
            }

            var btn = document.getElementById('vseo-restore-backup-btn');
            btn.disabled = true;
            btn.textContent = '恢复中...';

            fetch('<?php echo admin_url('admin-ajax.php'); ?>', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: 'action=visual_sitemap_baidu_restore_backup&nonce=<?php echo wp_create_nonce('visual_sitemap_baidu_restore_backup'); ?>'
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert(data.message || '恢复成功！');
                    location.reload();
                } else {
                    alert('恢复失败：' + (data.message || '未知错误'));
                    btn.disabled = false;
                    btn.textContent = '恢复备份';
                }
            })
            .catch(error => {
                alert('网络错误，请稍后重试');
                btn.disabled = false;
                btn.textContent = '恢复备份';
            });
        }
        </script>

        <!-- 更新提示区域 -->
        <?php if ($update_info && isset($update_info['has_update']) && $update_info['has_update']): ?>
        <div class="notice notice-warning is-dismissible" style="margin: 20px 0;" id="vseo-update-notice">
            <p>
                <strong>🔔 <?php printf(__('发现新版本 %s！当前版本：%s', 'visual-sitemap-baidu-seo'), esc_html($update_info['new_version']), esc_html($actual_version)); ?></strong>
            </p>
            <?php if (!empty($update_info['upgrade_notice'])): ?>
            <p><?php echo esc_html($update_info['upgrade_notice']); ?></p>
            <?php endif; ?>

            <!-- 更新进度显示 -->
            <div id="vseo-update-progress" style="display: none; margin: 15px 0;">
                <div style="background: #f0f0f0; border-radius: 3px; overflow: hidden; height: 24px; margin-bottom: 10px;">
                    <div id="vseo-progress-bar" style="background: #2271b1; height: 100%; width: 0%; transition: width 0.3s;"></div>
                </div>
                <p id="vseo-progress-text" style="margin: 5px 0; font-size: 13px; color: #666;">准备更新...</p>
            </div>

            <p>
                <button type="button" id="vseo-one-click-update" class="button button-primary" onclick="visualSitemapPerformUpdate()">
                    ✨ 一键更新
                </button>
                <a href="<?php echo admin_url('plugins.php'); ?>" class="button"><?php _e('前往插件页面', 'visual-sitemap-baidu-seo'); ?></a>
                <a href="https://www.sgvps.cn" target="_blank" class="button"><?php _e('访问官网', 'visual-sitemap-baidu-seo'); ?></a>
            </p>
        </div>

        <!-- 更新成功提示 -->
        <div class="notice notice-success is-dismissible" id="vseo-update-success" style="margin: 20px 0; display: none;">
            <p>
                <strong>✅ <span id="vseo-success-message">更新成功！</span></strong>
            </p>
            <p style="color: #d63638; font-size: 13px;">
                ⚠️ 更新已完成，请点击下方按钮刷新页面以加载新版本
            </p>
            <p>
                <button type="button" class="button button-primary" onclick="location.reload(true);">
                    刷新页面（推荐）
                </button>
                <button type="button" class="button" id="vseo-restore-backup-btn" onclick="visualSitemapRestoreBackup()">
                    恢复备份
                </button>
            </p>
        </div>
        <?php endif; ?>

        <!-- 广告区域 -->
        <?php
        $ad_content = visual_sitemap_baidu_get_ad_content();
        if (!empty($ad_content) && is_array($ad_content)) {
            echo '<div class="vseo-ad-area" style="margin: 20px 0;">';
            echo '<div style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 8px 15px; font-size: 13px; font-weight: bold; border-radius: 4px 4px 0 0; display: inline-block; margin-bottom: -1px;">📢 广告区</div>';
            echo '<div style="border: 2px solid #667eea; border-top: none; padding: 15px; border-radius: 0 0 4px 4px; background: #f8f9fa;">';
            echo '<div style="display: grid; grid-template-columns: repeat(6, 1fr); gap: 10px; text-align: center;">';
            foreach ($ad_content as $index => $ad) {
                echo '<div style="background: white; padding: 12px 8px; border-radius: 4px; box-shadow: 0 1px 3px rgba(0,0,0,0.1); transition: transform 0.2s;">';
                echo '<a href="' . esc_url($ad['url']) . '" target="_blank" style="text-decoration: none; color: #333; display: block;">';
                echo '<div style="font-size: 12px; font-weight: bold; color: #667eea; margin-bottom: 4px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;">' . esc_html($ad['title']) . '</div>';
                echo '<div style="font-size: 11px; color: #666; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;">' . esc_html($ad['description']) . '</div>';
                echo '</a>';
                echo '</div>';
            }
            echo '</div>';
            echo '</div>';
            echo '</div>';
        }
        ?>

        <?php
        // 显示消息提示
        foreach ($messages as $msg) {
            echo "<div class='{$msg['type']} notice is-dismissible'><p>{$msg['text']}</p></div>";
        }

        // 权限警告
        if (!empty($perm_errors)) {
            echo "<div class='error notice'><p class='vseo-permission-warning'>⚠️ 权限警告：" . implode('，', $perm_errors) . "</p></div>";
        }
        ?>

        <!-- 配置状态提示 -->
        <div class="vseo-card">
            <div class="vseo-card-header">
                <h2 class="vseo-card-title"><?php _e('配置状态', 'visual-sitemap-baidu-seo'); ?></h2>
            </div>
            <div class="<?php echo $api_url_valid ? 'vseo-status-success' : 'vseo-status-error'; ?>">
                <?php if ($api_url_valid): ?>
                    <p style="margin:0;"><strong>✅ <?php _e('自动获取站点域名：', 'visual-sitemap-baidu-seo'); ?></strong><?php echo esc_url($site_url); ?></p>
                    <p style="margin:5px 0 0 0;"><strong>✅ <?php _e('百度接口地址格式验证通过，SEO优化已启用！', 'visual-sitemap-baidu-seo'); ?></strong></p>
                <?php else: ?>
                    <p style="margin:0;"><strong>❌ <?php _e('自动获取站点域名：', 'visual-sitemap-baidu-seo'); ?></strong><?php echo esc_url($site_url); ?></p>
                    <p style="margin:5px 0 0 0;"><strong>❌ <?php _e('百度接口地址未配置/格式错误，请先去「插件配置」页面填写！', 'visual-sitemap-baidu-seo'); ?></strong></p>
                    <p style="margin:5px 0 0 0;color:#666;"><?php _e('提示：接口地址格式示例：', 'visual-sitemap-baidu-seo'); ?> http://data.zz.baidu.com/urls?site=https://www.sgvps.cn&token=xxx</p>
                <?php endif; ?>
            </div>
        </div>

        <!-- 快速操作区 -->
        <div class="vseo-card">
            <div class="vseo-card-header">
                <h2 class="vseo-card-title"><?php _e('快速操作', 'visual-sitemap-baidu-seo'); ?></h2>
            </div>
            <form method="post">
                <?php wp_nonce_field('visual_sitemap_baidu_nonce'); ?>
                <div class="vseo-btn-group">
                    <input type="submit" name="visual_sitemap_baidu_generate" class="button button-primary" value="<?php _e('生成SEO Sitemap并推送百度', 'visual-sitemap-baidu-seo'); ?>">
                    <input type="submit" name="visual_sitemap_baidu_check_api" class="button button-secondary" value="<?php _e('验证百度接口', 'visual-sitemap-baidu-seo'); ?>">
                    <input type="submit" name="visual_sitemap_baidu_generate_robots" class="button button-secondary" value="<?php _e('生成优化Robots.txt', 'visual-sitemap-baidu-seo'); ?>">
                </div>
            </form>
            <div class="vseo-notice vseo-status-info">
                <p style="margin:0;"><strong><?php _e('SEO Sitemap地址：', 'visual-sitemap-baidu-seo'); ?></strong><?php echo esc_url(home_url('/sitemap.xml')); ?></p>
                <p style="margin:5px 0 0 0;"><strong><?php _e('Robots.txt地址：', 'visual-sitemap-baidu-seo'); ?></strong><?php echo esc_url(home_url('/robots.txt')); ?></p>
            </div>
        </div>

        <!-- 自动执行配置 -->
        <div class="vseo-card">
            <div class="vseo-card-header">
                <h2 class="vseo-card-title"><?php _e('自动执行设置', 'visual-sitemap-baidu-seo'); ?></h2>
            </div>
            <p style="margin:0 0 10px 0;"><strong>📅 <?php _e('自动执行周期：', 'visual-sitemap-baidu-seo'); ?></strong><?php _e('每天凌晨', 'visual-sitemap-baidu-seo'); ?> <?php echo intval($settings['cron_hour']); ?> <?php _e('点', 'visual-sitemap-baidu-seo'); ?></p>
            <p style="margin:0 0 10px 0;"><strong>⏰ <?php _e('下次自动执行时间：', 'visual-sitemap-baidu-seo'); ?></strong><?php echo $next_cron ? date_i18n('Y-m-d H:i:s', $next_cron) : '<span style="color:red">'.__('未设置（请重新启用插件）', 'visual-sitemap-baidu-seo').'</span>'; ?></p>
            <p style="margin:0 0 15px 0;"><strong>✨ <?php _e('SEO增强：', 'visual-sitemap-baidu-seo'); ?></strong><?php echo $settings['enable_auto_push'] ? __('发布/更新文章时自动推送百度', 'visual-sitemap-baidu-seo') : __('未启用实时推送', 'visual-sitemap-baidu-seo'); ?></p>
            <div>
                <a href="<?php echo esc_url(admin_url('admin.php?page=visual-sitemap-baidu-settings')); ?>" class="button button-small"><?php _e('修改执行时间', 'visual-sitemap-baidu-seo'); ?></a>
                <a href="<?php echo esc_url(admin_url('admin.php?page=visual-sitemap-baidu-seo-settings')); ?>" class="button button-small" style="margin-left:5px;"><?php _e('SEO优化设置', 'visual-sitemap-baidu-seo'); ?></a>
            </div>
        </div>

        <!-- 操作日志 -->
        <div class="vseo-card">
            <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:15px;flex-wrap:wrap;">
                <h2 class="vseo-card-title" style="margin-bottom:0;"><?php _e('操作日志', 'visual-sitemap-baidu-seo'); ?></h2>
                <form method="post">
                    <?php wp_nonce_field('visual_sitemap_baidu_nonce'); ?>
                    <input type="submit" name="visual_sitemap_baidu_clear_log" class="button button-secondary button-small" value="<?php _e('清空日志', 'visual-sitemap-baidu-seo'); ?>">
                </form>
            </div>
            <?php visual_sitemap_baidu_display_logs(); ?>
        </div>
    </div>
    <?php
}

function visual_sitemap_baidu_main_handle_form() {
    $messages = array();
    
    // 手动生成Sitemap
    if (isset($_POST['visual_sitemap_baidu_generate'])) {
        check_admin_referer('visual_sitemap_baidu_nonce');
        
        // 检查权限
        $perm_errors = VisualSitemap_SettingsManager::checkPermissions();
        if (!empty($perm_errors)) {
            $messages[] = array(
                'type' => 'error',
                'text' => '权限不足：' . implode('，', $perm_errors) . '，请修改目录权限！'
            );
        } else {
            $generator = new VisualSitemap_SitemapGenerator();
            $result = $generator->generate(true);
            $messages[] = array(
                'type' => $result ? 'updated' : 'error',
                'text' => $result ? 'Sitemap生成并推送百度成功！' : 'Sitemap生成失败，请检查日志！'
            );
        }
    }

    // 清空日志
    if (isset($_POST['visual_sitemap_baidu_clear_log'])) {
        check_admin_referer('visual_sitemap_baidu_nonce');
        
        $log_manager = new VisualSitemap_LogManager();
        $log_manager->clearLogs();
        
        $messages[] = array(
            'type' => 'updated',
            'text' => '日志已清空！'
        );
    }

    // 生成优化的robots.txt
    if (isset($_POST['visual_sitemap_baidu_generate_robots'])) {
        check_admin_referer('visual_sitemap_baidu_nonce');
        
        $robots = new VisualSitemap_RobotsGenerator();
        $result = $robots->generate();
        $messages[] = array(
            'type' => $result ? 'updated' : 'error',
            'text' => $result ? 'robots.txt优化生成成功！' : 'robots.txt生成失败，请检查目录权限！'
        );
    }

    // 验证百度接口
    if (isset($_POST['visual_sitemap_baidu_check_api'])) {
        check_admin_referer('visual_sitemap_baidu_nonce');
        
        $baidu_push = new VisualSitemap_BaiduPush();
        $result = $baidu_push->validateAPI();
        
        $messages[] = array(
            'type' => $result['success'] ? 'updated' : 'error',
            'text' => $result['message']
        );
    }
    
    return $messages;
}

function visual_sitemap_baidu_display_logs() {
    global $wpdb;
    $table = $wpdb->prefix . 'visual_sitemap_baidu_logs';
    $settings = VisualSitemap_SettingsManager::getSettings();
    $log_limit = intval($settings['log_limit']);
    $logs = $wpdb->get_results($wpdb->prepare(
        "SELECT * FROM $table ORDER BY created_at DESC LIMIT %d",
        $log_limit
    ), ARRAY_A);
    ?>
    <div class="vseo-log-container">
        <table class="vseo-table">
            <thead>
                <tr>
                    <th><?php _e('时间', 'visual-sitemap-baidu-seo'); ?></th>
                    <th><?php _e('操作类型', 'visual-sitemap-baidu-seo'); ?></th>
                    <th><?php _e('状态', 'visual-sitemap-baidu-seo'); ?></th>
                    <th><?php _e('详情', 'visual-sitemap-baidu-seo'); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php
                if ($logs) {
                    foreach ($logs as $log) {
                        $status_color = $log['status'] == 'success' ? '#46b450' : '#dc3232';
                        // 将数据库时间转换为本地时间
                        $local_time = date_i18n('Y-m-d H:i:s', strtotime($log['created_at']));
                        echo "<tr>
                            <td>".esc_html($local_time)."</td>
                            <td>".esc_html($log['action'])."</td>
                            <td style='color:{$status_color};font-weight:bold;'>".esc_html($log['status'])."</td>
                            <td>".esc_html($log['content'])."</td>
                        </tr>";
                    }
                } else {
                    echo "<tr><td colspan='4' style='text-align:center;padding:20px;'>".__('暂无日志记录', 'visual-sitemap-baidu-seo')."</td></tr>";
                }
                ?>
            </tbody>
        </table>
    </div>
    <?php
}
