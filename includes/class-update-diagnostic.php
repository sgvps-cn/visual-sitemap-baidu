<?php
if (!defined('ABSPATH')) {
    exit('禁止直接访问！');
}

class VisualSitemap_UpdateDiagnostic {

    public function runDiagnostics() {
        $report = array(
            'timestamp' => current_time('mysql'),
            'tests' => array()
        );

        // 测试1: 路径验证
        $report['tests']['path_verification'] = $this->testPaths();

        // 测试2: 文件权限检查
        $report['tests']['file_permissions'] = $this->testFilePermissions();

        // 测试3: 磁盘空间检查
        $report['tests']['disk_space'] = $this->testDiskSpace();

        // 测试4: PHP配置检查
        $report['tests']['php_config'] = $this->testPHPConfig();

        // 测试5: 文件系统操作测试
        $report['tests']['file_operations'] = $this->testFileOperations();

        // 测试6: 模拟文件复制测试
        $report['tests']['copy_simulation'] = $this->testCopySimulation();

        return $report;
    }

    private function testPaths() {
        $result = array(
            'name' => '路径验证',
            'status' => 'pass',
            'details' => array()
        );

        // 获取插件根目录路径
        if (defined('VISUAL_SITEMAP_BAIDU_PLUGIN_DIR')) {
            $plugin_dir = VISUAL_SITEMAP_BAIDU_PLUGIN_DIR;
        } else {
            $plugin_dir = plugin_dir_path(dirname(__FILE__) . '/visual-sitemap-baidu.php');
        }
        $result['details']['plugin_dir_path'] = $plugin_dir;
        $result['details']['contains_plugins'] = strpos($plugin_dir, 'plugins') !== false;
        $result['details']['contains_wp_content'] = strpos($plugin_dir, 'wp-content') !== false;
        $result['details']['is_absolute'] = $plugin_dir[0] === '/' || $plugin_dir[1] === ':';

        // 测试 WP_CONTENT_DIR
        $result['details']['WP_CONTENT_DIR'] = defined('WP_CONTENT_DIR') ? WP_CONTENT_DIR : 'undefined';
        $result['details']['WP_CONTENT_DIR_exists'] = is_dir(WP_CONTENT_DIR);

        // 测试 WP_PLUGIN_DIR
        $result['details']['WP_PLUGIN_DIR'] = defined('WP_PLUGIN_DIR') ? WP_PLUGIN_DIR : 'undefined';
        $result['details']['WP_PLUGIN_DIR_exists'] = is_dir(WP_PLUGIN_DIR);

        // 测试 ABSPATH
        $result['details']['ABSPATH'] = defined('ABSPATH') ? ABSPATH : 'undefined';
        $result['details']['ABSPATH_exists'] = is_dir(ABSPATH);

        // 验证路径一致性
        $expected_plugin_dir = WP_PLUGIN_DIR . '/visual-sitemap-baidu/';
        $result['details']['expected_plugin_dir'] = $expected_plugin_dir;
        $result['details']['path_match'] = (strpos($plugin_dir, $expected_plugin_dir) === 0);

        if (!$result['details']['contains_plugins'] ||
            !$result['details']['contains_wp_content'] ||
            !$result['details']['path_match']) {
            $result['status'] = 'fail';
        }

        return $result;
    }

    private function testFilePermissions() {
        $result = array(
            'name' => '文件权限检查',
            'status' => 'pass',
            'details' => array()
        );

        // 获取插件根目录路径
        if (defined('VISUAL_SITEMAP_BAIDU_PLUGIN_DIR')) {
            $plugin_dir = VISUAL_SITEMAP_BAIDU_PLUGIN_DIR;
        } else {
            $plugin_dir = plugin_dir_path(dirname(__FILE__) . '/visual-sitemap-baidu.php');
        }
        $test_files = array(
            'visual-sitemap-baidu.php',
            'includes/class-update-manager.php',
            'includes/class-settings-manager.php'
        );

        foreach ($test_files as $file) {
            $file_path = $plugin_dir . $file;

            if (file_exists($file_path)) {
                $file_info = array(
                    'exists' => true,
                    'readable' => is_readable($file_path),
                    'writable' => is_writable($file_path),
                    'permissions' => substr(sprintf('%o', fileperms($file_path)), -4),
                    'size' => filesize($file_path),
                    'owner' => function_exists('posix_getpwuid') ? posix_getpwuid(fileowner($file_path))['name'] : 'unknown',
                    'group' => function_exists('posix_getgrgid') ? posix_getgrgid(filegroup($file_path))['name'] : 'unknown'
                );

                // 检查父目录是否可写
                $parent_dir = dirname($file_path);
                $file_info['parent_writable'] = is_writable($parent_dir);
                $file_info['parent_permissions'] = substr(sprintf('%o', fileperms($parent_dir)), -4);

                $result['details'][$file] = $file_info;

                if (!$file_info['writable'] || !$file_info['parent_writable']) {
                    $result['status'] = 'fail';
                }
            } else {
                $result['details'][$file] = array('exists' => false);
                $result['status'] = 'fail';
            }
        }

        // 检查插件主目录
        $result['details']['plugin_dir'] = array(
            'path' => $plugin_dir,
            'exists' => is_dir($plugin_dir),
            'writable' => is_writable($plugin_dir),
            'readable' => is_readable($plugin_dir),
            'permissions' => substr(sprintf('%o', fileperms($plugin_dir)), -4)
        );

        if (!$result['details']['plugin_dir']['writable']) {
            $result['status'] = 'fail';
        }

        // 检查WP_CONTENT_DIR
        $result['details']['wp_content_dir'] = array(
            'path' => WP_CONTENT_DIR,
            'exists' => is_dir(WP_CONTENT_DIR),
            'writable' => is_writable(WP_CONTENT_DIR)
        );

        if (!$result['details']['wp_content_dir']['writable']) {
            $result['status'] = 'fail';
        }

        return $result;
    }

    private function testDiskSpace() {
        $result = array(
            'name' => '磁盘空间检查',
            'status' => 'pass',
            'details' => array()
        );

        // 获取插件根目录路径
        if (defined('VISUAL_SITEMAP_BAIDU_PLUGIN_DIR')) {
            $plugin_dir = VISUAL_SITEMAP_BAIDU_PLUGIN_DIR;
        } else {
            $plugin_dir = plugin_dir_path(dirname(__FILE__) . '/visual-sitemap-baidu.php');
        }

        $free_space = disk_free_space($plugin_dir);
        $total_space = disk_total_space($plugin_dir);

        $result['details']['free_space'] = $this->formatBytes($free_space);
        $result['details']['total_space'] = $this->formatBytes($total_space);
        $result['details']['used_space'] = $this->formatBytes($total_space - $free_space);
        $result['details']['free_space_mb'] = round($free_space / 1024 / 1024, 2);

        // 检查是否有足够的磁盘空间（至少100MB）
        if ($free_space < 100 * 1024 * 1024) {
            $result['status'] = 'warning';
            $result['details']['warning'] = '磁盘空间不足100MB';
        }

        // 检查插件目录大小
        $plugin_size = $this->getDirectorySize($plugin_dir);
        $result['details']['plugin_size'] = $this->formatBytes($plugin_size);

        return $result;
    }

    private function testPHPConfig() {
        $result = array(
            'name' => 'PHP配置检查',
            'status' => 'pass',
            'details' => array()
        );

        // 关键PHP配置
        $ini_settings = array(
            'max_execution_time' => ini_get('max_execution_time'),
            'memory_limit' => ini_get('memory_limit'),
            'upload_max_filesize' => ini_get('upload_max_filesize'),
            'post_max_size' => ini_get('post_max_size'),
            'file_uploads' => ini_get('file_uploads'),
            'safe_mode' => ini_get('safe_mode'),
            'open_basedir' => ini_get('open_basedir'),
            'disable_functions' => ini_get('disable_functions')
        );

        $result['details']['ini_settings'] = $ini_settings;

        // 检查是否禁用了关键函数
        $disabled_functions = explode(',', $ini_settings['disable_functions']);
        $disabled_functions = array_map('trim', $disabled_functions);
        $critical_functions = array('copy', 'rename', 'unlink', 'file_get_contents', 'file_put_contents');

        $blocked_functions = array();
        foreach ($critical_functions as $func) {
            if (in_array($func, $disabled_functions)) {
                $blocked_functions[] = $func;
            }
        }

        $result['details']['blocked_functions'] = $blocked_functions;

        if (!empty($blocked_functions)) {
            $result['status'] = 'fail';
            $result['details']['error'] = '关键文件操作函数被禁用: ' . implode(', ', $blocked_functions);
        }

        // 检查OPcache
        $result['details']['opcache_enabled'] = function_exists('opcache_get_status') ? opcache_get_status(false) : false;
        $result['details']['opcache_enabled_simple'] = function_exists('opcache_enabled') ? opcache_enabled() : false;

        return $result;
    }

    private function testFileOperations() {
        $result = array(
            'name' => '文件系统操作测试',
            'status' => 'pass',
            'details' => array()
        );

        // 获取插件根目录路径
        if (defined('VISUAL_SITEMAP_BAIDU_PLUGIN_DIR')) {
            $plugin_dir = VISUAL_SITEMAP_BAIDU_PLUGIN_DIR;
        } else {
            $plugin_dir = plugin_dir_path(dirname(__FILE__) . '/visual-sitemap-baidu.php');
        }
        $test_dir = $plugin_dir . 'test-diagnostic-temp/';

        // 创建测试目录
        $mkdir_result = wp_mkdir_p($test_dir);
        $result['details']['create_directory'] = $mkdir_result;

        if (!$mkdir_result) {
            $result['status'] = 'fail';
            $result['details']['error'] = '无法创建测试目录';
            return $result;
        }

        // 创建测试文件
        $test_file = $test_dir . 'test.txt';
        $content = 'Test content at ' . time();
        $write_result = file_put_contents($test_file, $content);
        $result['details']['write_file'] = $write_result !== false;

        if ($write_result === false) {
            $result['status'] = 'fail';
            $result['details']['error'] = '无法写入测试文件';
            $this->cleanupTestDir($test_dir);
            return $result;
        }

        // 读取测试文件
        $read_result = file_get_contents($test_file);
        $result['details']['read_file'] = $read_result === $content;

        // 复制测试文件
        $test_file_copy = $test_dir . 'test-copy.txt';
        $copy_result = copy($test_file, $test_file_copy);
        $result['details']['copy_file'] = $copy_result;

        if (!$copy_result) {
            $result['status'] = 'fail';
            $result['details']['error'] = '无法复制测试文件: ' . error_get_last()['message'];
            $this->cleanupTestDir($test_dir);
            return $result;
        }

        // 验证复制结果
        $copy_content = file_get_contents($test_file_copy);
        $result['details']['copy_verify'] = $copy_content === $content;

        // 删除测试文件
        $delete_result = unlink($test_file);
        $result['details']['delete_file'] = $delete_result;

        // 清理测试目录
        $cleanup_result = $this->cleanupTestDir($test_dir);
        $result['details']['cleanup'] = $cleanup_result;

        if (!$copy_result || !$result['details']['copy_verify']) {
            $result['status'] = 'fail';
        }

        return $result;
    }

    private function testCopySimulation() {
        $result = array(
            'name' => '模拟文件复制测试',
            'status' => 'pass',
            'details' => array()
        );

        // 获取插件根目录路径
        if (defined('VISUAL_SITEMAP_BAIDU_PLUGIN_DIR')) {
            $plugin_dir = VISUAL_SITEMAP_BAIDU_PLUGIN_DIR;
        } else {
            $plugin_dir = plugin_dir_path(dirname(__FILE__) . '/visual-sitemap-baidu.php');
        }
        $test_source_dir = $plugin_dir . 'test-diagnostic-source/';
        $test_dest_dir = $plugin_dir . 'test-diagnostic-dest/';

        // 创建测试源目录和文件
        wp_mkdir_p($test_source_dir);
        wp_mkdir_p($test_source_dir . 'includes/');

        // 创建测试文件
        $test_files = array(
            'test-file-1.txt' => 'Test content 1',
            'test-file-2.txt' => 'Test content 2',
            'includes/test-file-3.txt' => 'Test content 3'
        );

        foreach ($test_files as $file => $content) {
            file_put_contents($test_source_dir . $file, $content);
        }

        $result['details']['source_files_created'] = count($test_files);

        // 尝试复制目录
        $update_manager = new VisualSitemap_UpdateManager();
        $copy_result = $update_manager->copyDirectory($test_source_dir, $test_dest_dir, true);

        $result['details']['copy_result'] = $copy_result;

        if (!$copy_result['success']) {
            $result['status'] = 'fail';
            $result['details']['error'] = $copy_result['message'];
            $this->cleanupTestDirs(array($test_source_dir, $test_dest_dir));
            return $result;
        }

        // 验证复制的文件
        $copied_files = 0;
        $verified_files = 0;

        foreach ($test_files as $file => $content) {
            $dest_file = $test_dest_dir . $file;
            if (file_exists($dest_file)) {
                $copied_files++;
                if (file_get_contents($dest_file) === $content) {
                    $verified_files++;
                } else {
                    $result['details']['mismatch'][$file] = '内容不匹配';
                }
            } else {
                $result['details']['missing'][$file] = '文件不存在';
            }
        }

        $result['details']['files_copied'] = $copied_files;
        $result['details']['files_verified'] = $verified_files;

        if ($copied_files !== count($test_files) || $verified_files !== count($test_files)) {
            $result['status'] = 'fail';
        }

        // 测试覆盖复制
        $result['details']['overwrite_test'] = array();
        file_put_contents($test_source_dir . 'test-file-1.txt', 'Updated content');

        $copy_result2 = $update_manager->copyDirectory($test_source_dir, $test_dest_dir, true);
        $result['details']['overwrite_test']['copy_result'] = $copy_result2['success'];

        $new_content = file_get_contents($test_dest_dir . 'test-file-1.txt');
        $result['details']['overwrite_test']['content_updated'] = ($new_content === 'Updated content');

        if (!$result['details']['overwrite_test']['copy_result'] ||
            !$result['details']['overwrite_test']['content_updated']) {
            $result['status'] = 'fail';
        }

        // 清理
        $this->cleanupTestDirs(array($test_source_dir, $test_dest_dir));

        return $result;
    }

    private function formatBytes($bytes, $precision = 2) {
        $units = array('B', 'KB', 'MB', 'GB', 'TB');

        $bytes = max($bytes, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);

        $bytes /= pow(1024, $pow);

        return round($bytes, $precision) . ' ' . $units[$pow];
    }

    private function getDirectorySize($directory) {
        $size = 0;
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
                $size += $this->getDirectorySize($file_path . '/');
            } else {
                $size += filesize($file_path);
            }
        }

        closedir($dir);
        return $size;
    }

    private function cleanupTestDir($directory) {
        if (!is_dir($directory)) {
            return true;
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
                $this->cleanupTestDir($file_path . '/');
                @rmdir($file_path);
            } else {
                @unlink($file_path);
            }
        }

        closedir($dir);
        return @rmdir($directory);
    }

    private function cleanupTestDirs($directories) {
        foreach ($directories as $dir) {
            $this->cleanupTestDir($dir);
        }
    }
}
