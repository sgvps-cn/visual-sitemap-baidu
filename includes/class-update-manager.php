<?php
if (!defined('ABSPATH')) {
    exit('禁止直接访问！');
}

class VisualSitemap_UpdateManager {

    private $api_endpoint;

    private $current_version;

    private $site_url;

    public function __construct() {
        $settings = VisualSitemap_SettingsManager::getSettings();
        $this->api_endpoint = isset($settings['api_endpoint']) ? $settings['api_endpoint'] : 'https://api.sgvps.cn';
        $this->current_version = VISUAL_SITEMAP_BAIDU_VERSION;
        $this->site_url = home_url();
    }

    private function getPluginRootDir() {
        if (defined('VISUAL_SITEMAP_BAIDU_PLUGIN_DIR')) {
            return VISUAL_SITEMAP_BAIDU_PLUGIN_DIR;
        }

        $current_file = dirname(__FILE__);
        return dirname($current_file) . '/';
    }

    public function checkForUpdate($force_refresh = false) {
        $cache_key = 'visual_sitemap_baidu_update_check';

        if ($force_refresh) {
            delete_transient($cache_key);
        }

        $cached = get_transient($cache_key);

        if ($cached !== false) {
            return $cached;
        }

        $this->syncSiteInfo();

        $api_url = rtrim($this->api_endpoint, '/') . '/api/check-update.php';

        $request_data = array(
            'plugin_slug' => 'visual-sitemap-baidu-seo',
            'current_version' => $this->current_version,
            'site_url' => $this->site_url,
            'php_version' => phpversion(),
            'wp_version' => get_bloginfo('version'),
            'locale' => get_locale(),
            'timestamp' => time()
        );

        $response = wp_remote_post($api_url, array(
            'timeout' => 15,
            'sslverify' => true,
            'headers' => array(
                'Content-Type' => 'application/json'
            ),
            'body' => json_encode($request_data)
        ));

        if (is_wp_error($response)) {
            $error_msg = $response->get_error_message();
            error_log('VisualSitemap: 检查更新失败 - ' . $error_msg);
            $error_data = array('error' => $error_msg);
            set_transient($cache_key, $error_data, 5 * MINUTE_IN_SECONDS);
            return $error_data;
        }

        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            error_log('VisualSitemap: 检查更新 - JSON解析失败 - ' . $body);
            $error_data = array('error' => 'API响应解析失败');
            set_transient($cache_key, $error_data, 5 * MINUTE_IN_SECONDS);
            return $error_data;
        }

        if (!isset($data['success']) || $data['success'] !== true) {
            $error_msg = isset($data['message']) ? $data['message'] : '更新检查失败';
            error_log('VisualSitemap: 检查更新 - ' . $error_msg);
            $error_data = array('error' => $error_msg);
            set_transient($cache_key, $error_data, 5 * MINUTE_IN_SECONDS);
            return $error_data;
        }

        $update_info = array(
            'has_update' => false,
            'new_version' => '',
            'download_url' => '',
            'package_url' => '',
            'requires_php' => '7.2',
            'requires_wp' => '5.0',
            'tested_up_to' => '',
            'last_updated' => '',
            'sections' => array(),
            'upgrade_notice' => ''
        );

        if (isset($data['update']) && is_array($data['update'])) {
            $update = $data['update'];

            if (isset($update['version']) && version_compare($update['version'], $this->current_version, '>')) {
                $update_info['has_update'] = true;
                $update_info['new_version'] = $update['version'];
                $update_info['download_url'] = isset($update['download_url']) ? $update['download_url'] : '';
                $update_info['package_url'] = isset($update['package_url']) ? $update['package_url'] : '';
                $update_info['requires_php'] = isset($update['requires_php']) ? $update['requires_php'] : '7.2';
                $update_info['requires_wp'] = isset($update['requires_wp']) ? $update['requires_wp'] : '5.0';
                $update_info['tested_up_to'] = isset($update['tested_up_to']) ? $update['tested_up_to'] : '';
                $update_info['last_updated'] = isset($update['last_updated']) ? $update['last_updated'] : '';
                $update_info['sections'] = isset($update['sections']) ? $update['sections'] : array();
                $update_info['upgrade_notice'] = isset($update['upgrade_notice']) ? $update['upgrade_notice'] : '';
            }
        }

        $cache_duration = $update_info['has_update'] ? 30 * MINUTE_IN_SECONDS : 1 * HOUR_IN_SECONDS;
        set_transient($cache_key, $update_info, $cache_duration);

        return $update_info;
    }

    public function downloadUpdate($package_url) {
        if (empty($package_url)) {
            return false;
        }

        $parsed_url = parse_url($package_url);
        $trusted_domains = array('sgvps.cn', 'www.sgvps.cn', 'api.sgvps.cn');

        if (!isset($parsed_url['host']) || !in_array($parsed_url['host'], $trusted_domains)) {
            error_log('VisualSitemap: 更新包URL来自不受信任的域名 - ' . $package_url);
            return false;
        }

        require_once(ABSPATH . 'wp-admin/includes/file.php');
        require_once(ABSPATH . 'wp-admin/includes/misc.php');

        $download_url = add_query_arg(array(
            'plugin_slug' => 'visual-sitemap-baidu-seo',
            'current_version' => $this->current_version,
            'site_url' => $this->site_url,
            'timestamp' => time()
        ), $package_url);

        $tmp_file = download_url($download_url);

        if (is_wp_error($tmp_file)) {
            return false;
        }

        return $tmp_file;
    }

    public function getUpdateNotice() {
        $update_info = $this->checkForUpdate();

        if (isset($update_info['error'])) {
            return '';
        }

        if (!$update_info['has_update']) {
            return '';
        }

        $notice = '<div class="notice notice-info is-dismissible">';
        $notice .= '<p><strong>' . sprintf(__('插件有新版本可用：当前版本 %s，新版本 %s', 'visual-sitemap-baidu-seo'), $this->current_version, $update_info['new_version']) . '</strong></p>';

        if (!empty($update_info['upgrade_notice'])) {
            $notice .= '<p>' . esc_html($update_info['upgrade_notice']) . '</p>';
        }

        if (!empty($update_info['sections']['changelog'])) {
            $notice .= '<details><summary>' . __('查看更新日志', 'visual-sitemap-baidu-seo') . '</summary>';
            $notice .= '<div style="max-height:300px;overflow-y:auto;padding:10px;background:#f9f9f9;margin-top:10px;border-radius:4px;">';
            $notice .= wp_kses_post($update_info['sections']['changelog']);
            $notice .= '</div></details>';
        }

        $notice .= '<p>';
        $notice .= '<a href="' . admin_url('plugins.php') . '" class="button button-primary">' . __('前往插件页面', 'visual-sitemap-baidu-seo') . '</a> ';
        $notice .= '<a href="https://www.sgvps.cn" target="_blank" class="button">' . __('访问官网', 'visual-sitemap-baidu-seo') . '</a>';
        $notice .= '</p>';
        $notice .= '</div>';

        return $notice;
    }

    public function clearUpdateCache() {
        delete_transient('visual_sitemap_baidu_update_check');
    }

    public function getCurrentVersionInfo() {
        return array(
            'version' => $this->current_version,
            'site_url' => $this->site_url,
            'php_version' => phpversion(),
            'wp_version' => get_bloginfo('version'),
            'locale' => get_locale()
        );
    }

    public function syncSiteInfo() {
        $api_url = rtrim($this->api_endpoint, '/') . '/api/sync-site.php';

        $site_info = $this->getCurrentVersionInfo();

        $response = wp_remote_post($api_url, array(
            'timeout' => 10,
            'sslverify' => true,
            'headers' => array(
                'Content-Type' => 'application/json'
            ),
            'body' => json_encode($site_info)
        ));

        if (is_wp_error($response)) {
            error_log('VisualSitemap: 同步站点信息失败 - ' . $response->get_error_message());
            return false;
        }

        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            error_log('VisualSitemap: 同步站点信息 - JSON解析失败 - ' . $body);
            return false;
        }

        $success = isset($data['success']) && $data['success'] === true;

        if (!$success) {
            error_log('VisualSitemap: 同步站点信息失败 - ' . (isset($data['message']) ? $data['message'] : '未知错误'));
        }

        return $success;
    }

    public function performUpdate() {
        register_shutdown_function(function() {
            $error = error_get_last();
            if ($error !== null && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR])) {
                error_log('VisualSitemap: 更新过程中发生致命错误 - ' . $error['message'] . ' in ' . $error['file'] . ':' . $error['line']);

                try {
                    $update_manager = new VisualSitemap_UpdateManager();
                    $update_manager->restoreBackup();
                    error_log('VisualSitemap: 检测到致命错误，已自动恢复备份');
                } catch (Exception $e) {
                    error_log('VisualSitemap: 自动恢复备份失败 - ' . $e->getMessage());
                }
            }
        });

        try {
            error_log('VisualSitemap: 开始执行一键更新');

            $update_info = $this->checkForUpdate();

            if (isset($update_info['error'])) {
                error_log('VisualSitemap: 更新检查失败 - ' . $update_info['error']);
                return array(
                    'success' => false,
                    'message' => $update_info['error']
                );
            }

            if (!$update_info['has_update']) {
                error_log('VisualSitemap: 无可用更新');
                return array(
                    'success' => false,
                    'message' => '当前已是最新版本'
                );
            }

            error_log('VisualSitemap: 发现新版本 ' . $update_info['new_version']);

            if (version_compare(phpversion(), $update_info['requires_php'], '<')) {
                error_log('VisualSitemap: PHP版本不满足要求');
                return array(
                    'success' => false,
                    'message' => sprintf('需要PHP版本 %s 或更高，当前版本：%s', $update_info['requires_php'], phpversion())
                );
            }

            if (version_compare(get_bloginfo('version'), $update_info['requires_wp'], '<')) {
                error_log('VisualSitemap: WordPress版本不满足要求');
                return array(
                    'success' => false,
                    'message' => sprintf('需要WordPress版本 %s 或更高，当前版本：%s', $update_info['requires_wp'], get_bloginfo('version'))
                );
            }

            if (empty($update_info['download_url'])) {
                error_log('VisualSitemap: 更新包下载地址为空');
                return array(
                    'success' => false,
                    'message' => '更新包下载地址无效'
                );
            }

            error_log('VisualSitemap: 开始下载更新包 - ' . $update_info['download_url']);

            $package_url = $update_info['download_url'];
            $tmp_file = $this->downloadUpdate($package_url);

            if (!$tmp_file || is_wp_error($tmp_file)) {
                error_log('VisualSitemap: 下载更新包失败 - ' . (is_wp_error($tmp_file) ? $tmp_file->get_error_message() : '未知原因'));
                return array(
                    'success' => false,
                    'message' => '下载更新包失败：' . (is_wp_error($tmp_file) ? $tmp_file->get_error_message() : '未知错误')
                );
            }

            error_log('VisualSitemap: 更新包下载成功 - ' . $tmp_file);

            require_once(ABSPATH . 'wp-admin/includes/file.php');
            require_once(ABSPATH . 'wp-admin/includes/class-wp-upgrader.php');
            require_once(ABSPATH . 'wp-admin/includes/plugin-install.php');

            $wp_filesystem = WP_Filesystem();
            if (!$wp_filesystem) {
                error_log('VisualSitemap: 文件系统初始化失败');
                return array(
                    'success' => false,
                    'message' => '文件系统初始化失败'
                );
            }

            global $wp_filesystem;

            $to_dir = $wp_filesystem->wp_content_dir() . 'upgrade/temp-update-' . time() . '/';
            error_log('VisualSitemap: 解压更新包到 ' . $to_dir);

            $result = unzip_file($tmp_file, $to_dir);

            if (is_wp_error($result)) {
                @unlink($tmp_file);
                error_log('VisualSitemap: 解压更新包失败 - ' . $result->get_error_message());
                return array(
                    'success' => false,
                    'message' => '解压更新包失败：' . $result->get_error_message()
                );
            }

            error_log('VisualSitemap: 更新包解压成功');

            // 删除临时压缩包
            @unlink($tmp_file);

            // 备份当前版本
            error_log('VisualSitemap: 开始备份当前版本');
            $backup_result = $this->backupCurrentVersion();
            if (!$backup_result['success']) {
                error_log('VisualSitemap: 备份失败 - ' . $backup_result['message']);
                return array(
                    'success' => false,
                    'message' => '备份失败：' . $backup_result['message']
                );
            }

            error_log('VisualSitemap: 备份成功 - ' . $backup_result['backup_path']);

            $plugin_dir = $this->getPluginRootDir();

            $from_dir = $this->findPluginDirectoryInArchive($to_dir);

            if ($from_dir === false) {
                error_log('VisualSitemap: 更新包格式不正确，无法找到插件目录');
                $this->cleanupTempFiles($to_dir);
                return array(
                    'success' => false,
                    'message' => '更新包格式不正确，无法找到插件主文件（visual-sitemap-baidu.php）'
                );
            }

            error_log('VisualSitemap: 找到插件目录 ' . $from_dir);

            error_log('VisualSitemap: 开始复制文件从 ' . $from_dir . ' 到 ' . $plugin_dir);

            $copy_result = $this->copyDirectory($from_dir, $plugin_dir, true);
            if (!$copy_result['success']) {
                // 尝试恢复备份
                error_log('VisualSitemap: 文件复制失败，尝试恢复备份 - ' . $copy_result['message']);
                $this->restoreBackup();
                $this->cleanupTempFiles($to_dir);
                return array(
                    'success' => false,
                    'message' => '文件复制失败：' . $copy_result['message'] . '，已自动恢复到原版本'
                );
            }

            error_log('VisualSitemap: 文件复制成功');

            error_log('VisualSitemap: 开始清理目标目录中的多余文件');
            $sync_result = $this->syncDirectories($from_dir, $plugin_dir, true);
            if (!$sync_result['success']) {
                error_log('VisualSitemap: 目录同步失败（非致命） - ' . $sync_result['message']);
            } else {
                error_log('VisualSitemap: 目标目录清理完成，删除了 ' . $sync_result['deleted_count'] . ' 个文件');
            }

            error_log('VisualSitemap: 验证更新结果');
            $new_version_in_file = $this->getVersionFromPluginFile();
            if ($new_version_in_file !== $update_info['new_version']) {
                error_log('VisualSitemap: 版本验证失败，期望: ' . $update_info['new_version'] . ', 实际: ' . $new_version_in_file);
                $this->restoreBackup();
                $this->cleanupTempFiles($to_dir);
                return array(
                    'success' => false,
                    'message' => '版本验证失败，更新未生效，已自动恢复到原版本'
                );
            }

            error_log('VisualSitemap: 版本验证成功 - ' . $new_version_in_file);

            error_log('VisualSitemap: 验证更新完整性');
            $integrity_result = $this->verifyUpdateIntegrity($from_dir, $update_info['new_version']);
            if (!$integrity_result['success']) {
                error_log('VisualSitemap: 完整性验证失败 - ' . $integrity_result['message']);
                $this->restoreBackup();
                $this->cleanupTempFiles($to_dir);
                return array(
                    'success' => false,
                    'message' => '完整性验证失败：' . $integrity_result['message'] . '，已自动恢复到原版本'
                );
            }

            error_log('VisualSitemap: 完整性验证成功');

            error_log('VisualSitemap: 更新 class-update-manager.php');
            $update_manager_file = $from_dir . 'includes/class-update-manager.php';
            $target_manager_file = $this->getPluginRootDir() . 'includes/class-update-manager.php';

            if (file_exists($update_manager_file)) {
                $copy_result = @copy($update_manager_file, $target_manager_file);
                if ($copy_result) {
                @chmod($target_manager_file, 0644);
                error_log('VisualSitemap: class-update-manager.php 更新成功');
            } else {
                error_log('VisualSitemap: class-update-manager.php 更新失败，但版本已更新');
            }
        } else {
            error_log('VisualSitemap: 更新包中未找到 class-update-manager.php');
        }

        try {
                $this->cleanupTempFiles($to_dir);
            error_log('VisualSitemap: 临时文件清理完成');
        } catch (Exception $e) {
            error_log('VisualSitemap: 清理临时文件失败（非致命） - ' . $e->getMessage());
        }

        try {
                $this->clearUpdateCache();
            } catch (Exception $e) {
            error_log('VisualSitemap: 清除更新缓存失败（非致命） - ' . $e->getMessage());
        }

        try {
                set_transient('visual_sitemap_baidu_just_updated', true, MINUTE_IN_SECONDS);
            } catch (Exception $e) {
            error_log('VisualSitemap: 设置更新标记失败（非致命） - ' . $e->getMessage());
        }

        try {
                if (function_exists('wp_cache_flush')) {
                wp_cache_flush();
            }
        } catch (Exception $e) {
            error_log('VisualSitemap: 清除WordPress缓存失败（非致命） - ' . $e->getMessage());
        }

        error_log('VisualSitemap: 一键更新成功，新版本 ' . $update_info['new_version']);

            return array(
                'success' => true,
                'message' => sprintf('插件已成功更新到版本 %s', $update_info['new_version']),
                'new_version' => $update_info['new_version'],
                'needs_reload' => true
            );
        } catch (Exception $e) {
            error_log('VisualSitemap: 一键更新异常 - ' . $e->getMessage());
            return array(
                'success' => false,
                'message' => '更新过程发生异常：' . $e->getMessage()
            );
        }
    }

    private function backupCurrentVersion() {
        try {
            $plugin_dir = $this->getPluginRootDir();
            $backup_dir = WP_CONTENT_DIR . '/backups/visual-sitemap-baidu-backup-' . time() . '/';

            // 创建备份目录
            if (!is_dir(WP_CONTENT_DIR . '/backups/')) {
                wp_mkdir_p(WP_CONTENT_DIR . '/backups/');
            }

            // 复制文件
            $copy_result = $this->copyDirectory($plugin_dir, $backup_dir);
            if (!$copy_result['success']) {
                return array(
                    'success' => false,
                    'message' => $copy_result['message']
                );
            }

            // 保存备份路径
            set_transient('visual_sitemap_baidu_backup_path', $backup_dir, WEEK_IN_SECONDS);

            return array(
                'success' => true,
                'message' => '备份成功',
                'backup_path' => $backup_dir
            );
        } catch (Exception $e) {
            error_log('VisualSitemap: 备份异常 - ' . $e->getMessage());
            return array(
                'success' => false,
                'message' => '备份异常: ' . $e->getMessage()
            );
        }
    }

    public function restoreBackup() {
        try {
            error_log('VisualSitemap: 开始恢复备份');

            $backup_path = get_transient('visual_sitemap_baidu_backup_path');

            if (!$backup_path) {
                error_log('VisualSitemap: 未找到备份路径');
                return false;
            }

            $plugin_dir = $this->getPluginRootDir();

            if (!is_dir($backup_path)) {
                error_log('VisualSitemap: 备份目录不存在 - ' . $backup_path);
                return false;
            }

            if (!is_dir($plugin_dir)) {
                error_log('VisualSitemap: 插件目录不存在 - ' . $plugin_dir);
                return false;
            }

            try {
                $this->emptyPluginDirectory($plugin_dir);
            } catch (Exception $e) {
                error_log('VisualSitemap: 清空插件目录失败 - ' . $e->getMessage());
            }

            $result = $this->copyDirectory($backup_path, $plugin_dir);

            if (!$result['success']) {
                error_log('VisualSitemap: 复制备份文件失败 - ' . $result['message']);
                return false;
            }

            try {
                $this->clearUpdateCache();
            } catch (Exception $e) {
                error_log('VisualSitemap: 清除更新缓存失败 - ' . $e->getMessage());
            }

            error_log('VisualSitemap: 备份恢复成功');
            return true;
        } catch (Exception $e) {
            error_log('VisualSitemap: 恢复备份异常 - ' . $e->getMessage());
            return false;
        }
    }

    private function getVersionFromPluginFile() {
        $plugin_file = $this->getPluginRootDir() . 'visual-sitemap-baidu.php';

        if (!file_exists($plugin_file)) {
            return '';
        }

        $content = file_get_contents($plugin_file);

        if (preg_match('/Version:\s*([0-9.]+)/', $content, $matches)) {
            return $matches[1];
        }

        if (preg_match("/define\(['\"]VISUAL_SITEMAP_BAIDU_VERSION['\"],\s*['\"]([^'\"]+)['\"]\)/", $content, $matches)) {
            return $matches[1];
        }

        return '';
    }

    private function findPluginDirectoryInArchive($extract_path) {
        $dirs = glob($extract_path . '*', GLOB_ONLYDIR);

        if (empty($dirs)) {
            error_log('VisualSitemap: 解压目录为空');
            return false;
        }

        error_log('VisualSitemap: 找到 ' . count($dirs) . ' 个目录');

        foreach ($dirs as $dir) {
            $basename = basename($dir);
            error_log('VisualSitemap: 检查目录 ' . $basename);

            if (strpos($basename, '.') === 0) {
                error_log('VisualSitemap: 跳过隐藏目录 ' . $basename);
                continue;
            }

            if (file_exists($dir . '/visual-sitemap-baidu.php')) {
                error_log('VisualSitemap: 找到插件目录 ' . $basename);
                return $dir . '/';
            }
        }

        $dir_names = array_map('basename', $dirs);
        error_log('VisualSitemap: 未找到插件主文件，目录列表: ' . implode(', ', $dir_names));

        return false;
    }

    private function verifyUpdateIntegrity($source_dir, $expected_version) {
        try {
            $critical_files = [
                'visual-sitemap-baidu.php' => '插件主文件',
                'includes/class-update-manager.php' => '更新管理类',
                'includes/class-settings-manager.php' => '配置管理类',
                'admin/pages-main.php' => '主页面文件'
            ];

            $plugin_dir = $this->getPluginRootDir();
            $missing_files = array();

            foreach ($critical_files as $file => $description) {
                $file_path = $plugin_dir . $file;
                if (!file_exists($file_path)) {
                    $missing_files[] = $description . ' (' . $file . ')';
                    error_log('VisualSitemap: 缺失关键文件 - ' . $file);
                }
            }

            if (!empty($missing_files)) {
                return array(
                    'success' => false,
                    'message' => '缺失关键文件: ' . implode(', ', $missing_files)
                );
            }

            $new_version_in_file = $this->getVersionFromPluginFile();
            if ($new_version_in_file !== $expected_version) {
                return array(
                    'success' => false,
                    'message' => sprintf('版本号不匹配，期望: %s, 实际: %s', $expected_version, $new_version_in_file)
                );
            }

            $source_file_count = $this->countFilesInDirectory($source_dir);
            $dest_file_count = $this->countFilesInDirectory($plugin_dir);

            error_log('VisualSitemap: 文件数量对比 - 源: ' . $source_file_count . ', 目标: ' . $dest_file_count);

            return array(
                'success' => true,
                'message' => '完整性验证通过'
            );
        } catch (Exception $e) {
            error_log('VisualSitemap: 完整性验证异常 - ' . $e->getMessage());
            return array(
                'success' => false,
                'message' => '完整性验证异常: ' . $e->getMessage()
            );
        }
    }

    private function compareDirectoryContents($source_dir, $dest_dir) {
        try {
            $source_files = $this->listAllFiles($source_dir);
            $dest_files = $this->listAllFiles($dest_dir);

            // 标准化路径（相对于源目录）
            $source_relative = array();
            foreach ($source_files as $file) {
                $relative = str_replace($source_dir, '', $file);
                $source_relative[$relative] = true;
            }

            $dest_relative = array();
            foreach ($dest_files as $file) {
                $relative = str_replace($dest_dir, '', $file);
                $dest_relative[$relative] = true;
            }

            $extra_files = array();
            foreach (array_keys($dest_relative) as $relative_path) {
                if (!isset($source_relative[$relative_path])) {
                    $extra_files[] = $relative_path;
                }
            }

            $missing_files = array();
            foreach (array_keys($source_relative) as $relative_path) {
                if (!isset($dest_relative[$relative_path])) {
                    $missing_files[] = $relative_path;
                }
            }

            return array(
                'success' => true,
                'extra_files' => $extra_files,
                'missing_files' => $missing_files,
                'source_count' => count($source_files),
                'dest_count' => count($dest_files)
            );
        } catch (Exception $e) {
            error_log('VisualSitemap: 比较目录内容异常 - ' . $e->getMessage());
            return array(
                'success' => false,
                'message' => '比较目录内容异常: ' . $e->getMessage()
            );
        }
    }

    private function listAllFiles($directory) {
        try {
            $files = array();
            $dir = @opendir($directory);

            if (!$dir) {
                return $files;
            }

            while (($file = readdir($dir)) !== false) {
                if ($file == '.' || $file == '..') {
                    continue;
                }

                $file_path = $directory . $file;

                if (is_dir($file_path)) {
                    $files = array_merge($files, $this->listAllFiles($file_path . '/'));
                } else {
                    $files[] = $file_path;
                }
            }

            closedir($dir);
            return $files;
        } catch (Exception $e) {
            error_log('VisualSitemap: 列出文件异常 - ' . $e->getMessage());
            return array();
        }
    }

    private function countFilesInDirectory($directory) {
        try {
            $count = 0;
            $dir = @opendir($directory);

            if (!$dir) {
                return 0;
            }

            while (($file = readdir($dir)) !== false) {
                if ($file == '.' || $file == '..') {
                    continue;
                }

                $file_path = $directory . $file;

                if (is_dir($file_path)) {
                    $count += $this->countFilesInDirectory($file_path . '/');
                } else {
                    $count++;
                }
            }

            closedir($dir);
            return $count;
        } catch (Exception $e) {
            error_log('VisualSitemap: 统计文件数量异常 - ' . $e->getMessage());
            return 0;
        }
    }

    private function syncDirectories($source, $destination, $allow_delete = true) {
        try {
            error_log("VisualSitemap: 同步目录 {$source} -> {$destination}");

            // 标准化路径格式
            $source = rtrim($source, '/') . '/';
            $destination = rtrim($destination, '/') . '/';

            if (!is_dir($source)) {
                return array(
                    'success' => false,
                    'message' => '源目录不存在: ' . $source
                );
            }

            if (!is_dir($destination)) {
                return array(
                    'success' => true,
                    'deleted_count' => 0,
                    'deleted_dirs' => 0
                );
            }

            if (!$allow_delete) {
                return array(
                    'success' => true,
                    'deleted_count' => 0,
                    'deleted_dirs' => 0
                );
            }

            $deleted_count = 0;
            $deleted_dirs = 0;
            $error_files = array();
            $skipped_files = array();

            // 获取目标目录中的所有文件和目录
            $dir = @opendir($destination);

            if (!$dir) {
                return array(
                    'success' => false,
                    'message' => '无法打开目标目录: ' . $destination
                );
            }

            // 保护列表：不允许删除的关键文件
            $protected_files = [
                'class-update-manager.php',  // 正在执行的文件
                'visual-sitemap-baidu.php',  // 插件主文件
                'class-settings-manager.php'  // 核心类文件
            ];

            while (($file = readdir($dir)) !== false) {
                if ($file == '.' || $file == '..') {
                    continue;
                }

                $dest_file = $destination . $file;
                $src_file = $source . $file;

                // 跳过保护列表中的文件
                if (in_array($file, $protected_files)) {
                    $skipped_files[] = $file;
                    error_log('VisualSitemap: 跳过保护文件 ' . $file);
                    continue;
                }

                if (is_dir($dest_file)) {
                    if (!is_dir($src_file)) {
                        $is_critical_dir = in_array($file, ['includes', 'admin', 'assets']);
                        if ($is_critical_dir) {
                            $skipped_files[] = $file . '/';
                            error_log('VisualSitemap: 跳过关键目录 ' . $file);
                            continue;
                        }

                        $result = $this->deleteDirectory($dest_file);
                        if ($result) {
                            $deleted_dirs++;
                            error_log('VisualSitemap: 删除多余目录 ' . $dest_file);
                        } else {
                            $error_files[] = $dest_file;
                            error_log('VisualSitemap: 删除目录失败 ' . $dest_file);
                        }
                    } else {
                        $result = $this->syncDirectories($src_file, $dest_file, $allow_delete);
                        if (!$result['success']) {
                            closedir($dir);
                            return $result;
                        }
                        $deleted_count += $result['deleted_count'];
                        $deleted_dirs += $result['deleted_dirs'];
                    }
                } else {
                    // 如果是文件，检查源目录中是否存在
                    if (!file_exists($src_file)) {
                        // 源目录中没有对应文件，删除目标文件
                        $result = @unlink($dest_file);
                        if ($result) {
                            $deleted_count++;
                            error_log('VisualSitemap: 删除多余文件 ' . $dest_file);
                        } else {
                            $error_files[] = $dest_file;
                            error_log('VisualSitemap: 删除文件失败 ' . $dest_file);
                        }
                    }
                }
            }

            closedir($dir);

            if (!empty($skipped_files)) {
                error_log('VisualSitemap: 跳过的文件/目录: ' . implode(', ', $skipped_files));
            }

            if (!empty($error_files)) {
                return array(
                    'success' => false,
                    'message' => '部分文件/目录删除失败: ' . implode(', ', $error_files),
                    'deleted_count' => $deleted_count,
                    'deleted_dirs' => $deleted_dirs,
                    'skipped_files' => $skipped_files
                );
            }

            error_log("VisualSitemap: 目录同步成功，共删除 {$deleted_count} 个文件，{$deleted_dirs} 个目录，跳过 " . count($skipped_files) . " 个文件");

            return array(
                'success' => true,
                'deleted_count' => $deleted_count,
                'deleted_dirs' => $deleted_dirs,
                'skipped_files' => $skipped_files
            );
        } catch (Exception $e) {
            error_log('VisualSitemap: 同步目录异常 - ' . $e->getMessage());
            return array(
                'success' => false,
                'message' => '同步目录异常: ' . $e->getMessage()
            );
        }
    }

    private function deleteDirectory($directory) {
        try {
            $directory = rtrim($directory, '/') . '/';

            if (!is_dir($directory)) {
                return false;
            }

            $dir = @opendir($directory);
            if (!$dir) {
                return false;
            }

            while (($file = readdir($dir)) !== false) {
                if ($file == '.' || $file == '..') {
                    continue;
                }

                $file_path = $directory . $file;

                if (is_dir($file_path)) {
                    $this->deleteDirectory($file_path);
                } else {
                    @unlink($file_path);
                }
            }

            closedir($dir);

            return @rmdir($directory);
        } catch (Exception $e) {
            error_log('VisualSitemap: 删除目录异常 - ' . $e->getMessage());
            return false;
        }
    }

    public function copyDirectory($source, $destination, $overwrite = true) {
        try {
            error_log("VisualSitemap: 复制目录 {$source} -> {$destination}");

            // 标准化路径格式
            $source = rtrim($source, '/') . '/';
            $destination = rtrim($destination, '/') . '/';

            if (!is_dir($source)) {
                error_log('VisualSitemap: 复制目录 - 源目录不存在: ' . $source);
                return array(
                    'success' => false,
                    'message' => '源目录不存在: ' . $source
                );
            }

            if (!is_dir($destination)) {
                $mkdir_result = wp_mkdir_p($destination);
                if (!$mkdir_result) {
                    error_log('VisualSitemap: 复制目录 - 创建目标目录失败: ' . $destination);
                    return array(
                        'success' => false,
                        'message' => '创建目标目录失败: ' . $destination
                    );
                }
            }

            $dir = @opendir($source);

            if (!$dir) {
                error_log('VisualSitemap: 复制目录 - 无法打开源目录: ' . $source);
                return array(
                    'success' => false,
                    'message' => '无法打开源目录: ' . $source
                );
            }

            $file_count = 0;
            $dir_count = 0;
            $error_files = array();
            $skipped_files = array();

            error_log('VisualSitemap: 开始遍历源目录文件');

            while (($file = readdir($dir)) !== false) {
                if ($file == '.' || $file == '..') {
                    continue;
                }

                $src_file = $source . $file;
                $dest_file = $destination . $file;

                error_log("VisualSitemap: 处理文件/目录: $file");

                if (is_dir($src_file)) {
                    error_log("VisualSitemap: 发现子目录: $src_file -> $dest_file");
                    $result = $this->copyDirectory($src_file, $dest_file);
                    if (!$result['success']) {
                        closedir($dir);
                        error_log('VisualSitemap: 复制目录 - 子目录复制失败: ' . $src_file);
                        return $result;
                    }
                    $dir_count++;
                    error_log("VisualSitemap: 子目录复制成功: $file");
                } else {
                    // 检查目标文件是否存在
                    if (file_exists($dest_file)) {
                        error_log("VisualSitemap: 目标文件已存在: $dest_file");
                        if (!$overwrite) {
                            // 不覆盖，跳过
                            $skipped_files[] = $file;
                            error_log('VisualSitemap: 跳过现有文件: ' . $dest_file);
                            continue;
                        } else {
                            error_log("VisualSitemap: 将覆盖现有文件: $dest_file");
                        }
                    } else {
                        error_log("VisualSitemap: 目标文件不存在，将创建: $dest_file");
                    }

                    // 使用copy函数直接复制（覆盖）
                    $copy_result = @copy($src_file, $dest_file);
                    error_log("VisualSitemap: copy() 结果: " . ($copy_result ? '成功' : '失败') . " - $src_file -> $dest_file");

                    if (!$copy_result) {
                        $error_files[] = $file;
                        error_log('VisualSitemap: 复制文件失败: ' . $src_file . ' -> ' . $dest_file . ' (可能: 权限不足或文件被锁定)');
                        error_log('VisualSitemap: 源文件信息 - 存在:' . (file_exists($src_file) ? 'Y' : 'N') . ', 可读:' . (is_readable($src_file) ? 'Y' : 'N') . ', 大小:' . filesize($src_file));
                        error_log('VisualSitemap: 目标目录信息 - 存在:' . (file_exists($dest_file) ? 'Y' : 'N') . ', 可写:' . (is_writable(dirname($dest_file)) ? 'Y' : 'N'));
                    } else {
                        $file_count++;
                        // 设置文件权限
                        @chmod($dest_file, 0644);
                        error_log("VisualSitemap: 文件复制成功并设置权限: $dest_file (权限: 0644)");
                    }
                }
            }

            closedir($dir);

            if (!empty($skipped_files)) {
                error_log('VisualSitemap: 跳过的文件: ' . implode(', ', $skipped_files));
            }

            if (!empty($error_files)) {
                error_log('VisualSitemap: 复制失败的文件: ' . implode(', ', $error_files));
                return array(
                    'success' => false,
                    'message' => sprintf('部分文件复制失败: %s', implode(', ', $error_files))
                );
            }

            error_log("VisualSitemap: 复制目录成功，共复制 {$file_count} 个文件，{$dir_count} 个目录");

            return array('success' => true);
        } catch (Exception $e) {
            error_log('VisualSitemap: 复制目录异常 - ' . $e->getMessage());
            return array(
                'success' => false,
                'message' => '复制目录异常: ' . $e->getMessage()
            );
        }
    }

    private function emptyPluginDirectory($directory) {
        try {
            $directory = rtrim($directory, '/') . '/';

            if (!is_dir($directory)) {
                return false;
            }

            $current_file = __FILE__;
            $current_filename = basename($current_file);

            $dir = @opendir($directory);
            if (!$dir) {
                return false;
            }

            while (($file = readdir($dir)) !== false) {
                if ($file == '.' || $file == '..') {
                    continue;
                }

                $file_path = $directory . $file;

                if ($file == 'class-update-manager.php') {
                    error_log('VisualSitemap: 跳过当前文件 ' . $file);
                    continue;
                }

                if (is_dir($file_path)) {
                    $this->emptyDirectory($file_path);
                    @rmdir($file_path);
                } else {
                    @unlink($file_path);
                    error_log('VisualSitemap: 删除文件 ' . $file_path);
                }
            }

            closedir($dir);
            error_log('VisualSitemap: 插件目录清空完成');
            return true;
        } catch (Exception $e) {
            error_log('VisualSitemap: 清空插件目录异常 - ' . $e->getMessage());
            return false;
        }
    }

    private function emptyDirectory($directory) {
        try {
            $directory = rtrim($directory, '/') . '/';

            if (!is_dir($directory)) {
                return false;
            }

            $dir = @opendir($directory);
            if (!$dir) {
                return false;
            }

            while (($file = readdir($dir)) !== false) {
                if ($file == '.' || $file == '..') {
                    continue;
                }

                $file_path = $directory . $file;

                if (is_dir($file_path)) {
                    $this->emptyDirectory($file_path);
                    @rmdir($file_path);
                } else {
                    @unlink($file_path);
                }
            }

            closedir($dir);
            return true;
        } catch (Exception $e) {
            error_log('VisualSitemap: 清空目录异常 - ' . $e->getMessage());
            return false;
        }
    }

    private function cleanupTempFiles($temp_dir) {
        try {
            if (empty($temp_dir)) {
                return;
            }

            if (!is_dir($temp_dir)) {
                return;
            }

            $result = $this->emptyDirectory($temp_dir);
            if (!$result) {
                error_log('VisualSitemap: 清空临时目录失败 - ' . $temp_dir);
                // 继续尝试删除目录
            }

            // 删除目录本身
            $rmdir_result = @rmdir($temp_dir);
            if (!$rmdir_result) {
                error_log('VisualSitemap: 删除临时目录失败（非致命） - ' . $temp_dir);
            } else {
                error_log('VisualSitemap: 临时目录删除成功 - ' . $temp_dir);
            }
        } catch (Exception $e) {
            error_log('VisualSitemap: 清理临时文件异常 - ' . $e->getMessage());
        }
    }

    public function cleanupOldBackups($days = 7) {
        try {
            $backup_dir = WP_CONTENT_DIR . '/backups/';

            if (!is_dir($backup_dir)) {
                return;
            }

            $cutoff_time = time() - ($days * DAY_IN_SECONDS);

            $dirs = glob($backup_dir . 'visual-sitemap-baidu-backup-*');

            if (is_array($dirs)) {
                foreach ($dirs as $dir) {
                    $dir_time = filemtime($dir);
                    if ($dir_time && $dir_time < $cutoff_time) {
                        $this->emptyDirectory($dir);
                        @rmdir($dir);
                    }
                }
            }
        } catch (Exception $e) {
            error_log('VisualSitemap: 清理旧备份异常 - ' . $e->getMessage());
        }
    }
}
