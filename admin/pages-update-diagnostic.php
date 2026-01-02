<?php
if (!defined('ABSPATH')) {
    exit('禁止直接访问！');
}



?>

<div class="wrap">
    <h1>一键更新诊断工具</h1>

    <?php
    // 检查是否运行诊断
    $run_diagnostic = isset($_POST['run_diagnostic']) && $_POST['run_diagnostic'] === '1';

    if ($run_diagnostic) {
        check_admin_referer('visual_sitemap_update_diagnostic');

        $report = null;
        $error_message = '';

        try {
            $diagnostic = new VisualSitemap_UpdateDiagnostic();
            $report = $diagnostic->runDiagnostics();
        } catch (Exception $e) {
            $error_message = '诊断过程出错: ' . $e->getMessage();
        } catch (Error $e) {
            $error_message = '系统错误: ' . $e->getMessage();
        }

        if ($error_message) {
            echo '<div class="notice notice-error"><p><strong>错误:</strong> ' . esc_html($error_message) . '</p></div>';
        } elseif (!$report) {
            echo '<div class="notice notice-error"><p><strong>错误:</strong> 诊断报告生成失败</p></div>';
        }

        // 显示诊断报告
        echo '<div class="card">';

        // 总体状态
        $has_errors = false;
        $has_warnings = false;
        foreach ($report['tests'] as $test) {
            if ($test['status'] === 'fail') {
                $has_errors = true;
            } elseif ($test['status'] === 'warning') {
                $has_warnings = true;
            }
        }

        if ($has_errors) {
            echo '<div class="notice notice-error"><p><strong>发现错误！</strong> 某些诊断测试失败，可能导致一键更新无法正常工作。</p></div>';
        } elseif ($has_warnings) {
            echo '<div class="notice notice-warning"><p><strong>发现警告！</strong> 某些配置可能影响一键更新的性能或稳定性。</p></div>';
        } else {
            echo '<div class="notice notice-success"><p><strong>所有测试通过！</strong> 系统配置正常，可以执行一键更新。</p></div>';
        }

        // 显示详细报告
        echo '<h2>诊断报告</h2>';
        echo '<p>生成时间: ' . $report['timestamp'] . '</p>';

        foreach ($report['tests'] as $test_name => $test) {
            $status_class = $test['status'] === 'pass' ? 'success' : ($test['status'] === 'warning' ? 'warning' : 'error');
            $status_text = $test['status'] === 'pass' ? '通过' : ($test['status'] === 'warning' ? '警告' : '失败');

            echo '<div class="notice notice-' . $status_class . ' inline">';
            echo '<h3>' . $test['name'] . ' - ' . $status_text . '</h3>';

            if ($test['status'] === 'fail' && isset($test['details']['error'])) {
                echo '<p><strong>错误:</strong> ' . esc_html($test['details']['error']) . '</p>';
            }

            echo '<pre style="background: #f1f1f1; padding: 10px; overflow: auto; max-height: 400px;">';
            echo esc_html(print_r($test['details'], true));
            echo '</pre>';

            echo '</div>';
        }

        echo '</div>';
    }
    ?>

    <div class="card">
        <h2>运行诊断</h2>
        <p>此工具会检查以下内容:</p>
        <ul>
            <li>路径配置是否正确</li>
            <li>文件和目录权限是否足够</li>
            <li>磁盘空间是否充足</li>
            <li>PHP配置是否支持文件操作</li>
            <li>文件系统操作是否正常</li>
            <li>模拟文件复制是否成功</li>
        </ul>

        <form method="post" action="">
            <?php wp_nonce_field('visual_sitemap_update_diagnostic'); ?>
            <input type="hidden" name="run_diagnostic" value="1">
            <?php submit_button('运行诊断测试'); ?>
        </form>
    </div>

    <div class="card">
        <h2>常见问题</h2>
        <h3>文件复制失败</h3>
        <p><strong>可能原因:</strong></p>
        <ul>
            <li>文件或目录权限不足（需要755/644权限）</li>
            <li>磁盘空间不足</li>
            <li>文件被锁定（如OPcache）</li>
            <li>SELinux或AppArmor限制</li>
            <li>PHP disable_functions禁用了关键函数</li>
        </ul>

        <h3>网站出现严重错误</h3>
        <p><strong>可能原因:</strong></p>
        <ul>
            <li>更新过程中删除了关键文件</li>
            <li>版本不兼容</li>
            <li>PHP错误（如内存不足、超时）</li>
        </ul>

        <h3>解决方法</h3>
        <p>如果诊断发现问题，请检查:</p>
        <ul>
            <li>WordPress和插件目录的权限设置为755</li>
            <li>WordPress和插件文件的权限设置为644</li>
            <li>确保PHP的disable_functions不包含copy、unlink等文件操作函数</li>
            <li>检查服务器是否有足够的磁盘空间</li>
            <li>如果使用共享主机，联系主机商确认是否允许文件操作</li>
        </ul>
    </div>
</div>

<style>
.notice.inline {
    margin: 10px 0;
}
</style>
