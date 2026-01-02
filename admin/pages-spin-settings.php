<?php
if (!defined('ABSPATH')) {
    exit('禁止直接访问！');
}

function visual_sitemap_baidu_spin_settings_page() {
    // 权限检查
    if (!current_user_can('manage_options')) {
        wp_die(__('您没有足够的权限访问此页面！', 'visual-sitemap-baidu-seo'));
    }
    
    $messages = array();

    // 保存配置
    if (isset($_POST['visual_sitemap_baidu_save_spin_settings'])) {
        check_admin_referer('visual_sitemap_baidu_spin_settings_nonce');
        
        $settings = VisualSitemap_SettingsManager::getSettings();
        
        // 安全过滤和验证
        $settings['enable_spintax'] = isset($_POST['enable_spintax']) ? 1 : 0;
        $settings['spin_level'] = intval($_POST['spin_level']);
        $settings['spin_mode'] = sanitize_text_field($_POST['spin_mode']);
        
        // 边界值校验
        $settings['spin_level'] = max(1, min(5, $settings['spin_level']));
        
        // 验证模式
        if (!in_array($settings['spin_mode'], array('word', 'sentence', 'both'))) {
            $settings['spin_mode'] = 'word';
        }
        
        // 处理自定义同义词库
        $custom_synonyms_text = sanitize_textarea_field($_POST['custom_synonyms']);
        $custom_synonyms = array();
        
        if (!empty($custom_synonyms_text)) {
            $lines = explode("\n", trim($custom_synonyms_text));
            foreach ($lines as $line) {
                $line = trim($line);
                if (empty($line)) {
                    continue;
                }
                
                // 解析格式：原文词|同义词1,同义词2,同义词3
                if (strpos($line, '|') !== false) {
                    list($original, $synonyms_str) = explode('|', $line, 2);
                    $original = trim($original);
                    $synonyms = array_map('trim', explode(',', $synonyms_str));
                    $synonyms = array_filter($synonyms);
                    
                    if (!empty($original) && !empty($synonyms)) {
                        $custom_synonyms[$original] = $synonyms;
                    }
                }
            }
        }
        
        $settings['custom_synonyms'] = json_encode($custom_synonyms);
        
        // 更新配置
        VisualSitemap_SettingsManager::saveSettings($settings);
        
        $messages[] = array(
            'type' => 'updated',
            'text' => __('伪原创配置保存成功！', 'visual-sitemap-baidu-seo')
        );
        
        // 记录日志
        $log = new VisualSitemap_LogManager();
        $log->log('spin_settings', '伪原创配置已更新：强度=' . $settings['spin_level'] . '级，模式=' . $settings['spin_mode'] . '，同义词数量=' . count($custom_synonyms), 'success');
    }
    
    // 获取当前配置
    $settings = VisualSitemap_SettingsManager::getSettings();
    
    // 解析自定义同义词
    $custom_synonyms_text = '';
    if (!empty($settings['custom_synonyms'])) {
        $custom_synonyms = json_decode($settings['custom_synonyms'], true);
        if (is_array($custom_synonyms)) {
            foreach ($custom_synonyms as $original => $synonyms) {
                $custom_synonyms_text .= $original . '|' . implode(',', $synonyms) . "\n";
            }
        }
    }
    
    // 获取蜘蛛访问日志
    $spider_logs = array();
    global $wpdb;
    $spider_table = $wpdb->prefix . 'visual_sitemap_baidu_spider_logs';
    $spider_logs = $wpdb->get_results($wpdb->prepare(
        "SELECT * FROM {$spider_table} ORDER BY visit_time DESC LIMIT 10"
    ));
    
    // 输出页面
    ?>
    <div class="wrap">
        <h1><?php _e('伪原创设置', 'visual-sitemap-baidu-seo'); ?></h1>
        
        <?php
        // 显示消息提示
        foreach ($messages as $msg) {
            echo "<div class='{$msg['type']} notice is-dismissible'><p>{$msg['text']}</p></div>";
        }
        ?>
        
        <div class="vseo-card" style="max-width:900px;">
            <!-- 伪原创开关 -->
            <div class="vseo-section">
                <h3 class="vseo-section-title"><?php _e('伪原创功能', 'visual-sitemap-baidu-seo'); ?></h3>
                
                <form method="post">
                    <?php wp_nonce_field('visual_sitemap_baidu_spin_settings_nonce'); ?>
                    
                    <table class="form-table">
                        <tr>
                            <th scope="row">
                                <label for="enable_spintax"><?php _e('启用伪原创功能', 'visual-sitemap-baidu-seo'); ?></label>
                            </th>
                            <td>
                                <input type="checkbox" name="enable_spintax" id="enable_spintax" value="1" <?php checked($settings['enable_spintax'], 1); ?>>
                                <label for="enable_spintax"><?php _e('对百度蜘蛛应用伪原创', 'visual-sitemap-baidu-seo'); ?></label>
                                <p class="description">
                                    <?php _e('启用后，百度蜘蛛访问时会看到经过伪原创处理的内容，提升内容差异化，普通用户看到的仍然是原始内容', 'visual-sitemap-baidu-seo'); ?>
                                </p>
                            </td>
                        </tr>
                    </table>
                    
                    <!-- 伪原创强度和模式 -->
                    <div class="vseo-subsection">
                        <h4><?php _e('伪原创强度和模式', 'visual-sitemap-baidu-seo'); ?></h4>
                        
                        <table class="form-table">
                            <tr>
                                <th scope="row" style="width:200px;">
                                    <label for="spin_level"><?php _e('伪原创强度', 'visual-sitemap-baidu-seo'); ?></label>
                                </th>
                                <td>
                                    <select name="spin_level" id="spin_level">
                                        <option value="1" <?php selected($settings['spin_level'], 1); ?>><?php _e('1级 - 轻度（15%替换率）', 'visual-sitemap-baidu-seo'); ?></option>
                                        <option value="2" <?php selected($settings['spin_level'], 2); ?>><?php _e('2级 - 轻度（30%替换率）推荐', 'visual-sitemap-baidu-seo'); ?></option>
                                        <option value="3" <?php selected($settings['spin_level'], 3); ?>><?php _e('3级 - 中度（45%替换率）推荐', 'visual-sitemap-baidu-seo'); ?></option>
                                        <option value="4" <?php selected($settings['spin_level'], 4); ?>><?php _e('4级 - 强度（60%替换率）', 'visual-sitemap-baidu-seo'); ?></option>
                                        <option value="5" <?php selected($settings['spin_level'], 5); ?>><?php _e('5级 - 强度（75%替换率）', 'visual-sitemap-baidu-seo'); ?></option>
                                    </select>
                                    <p class="description">
                                        <?php _e('建议使用2-3级，确保内容可读性', 'visual-sitemap-baidu-seo'); ?>
                                    </p>
                                </td>
                            </tr>
                            
                            <tr>
                                <th scope="row">
                                    <label for="spin_mode"><?php _e('伪原创模式', 'visual-sitemap-baidu-seo'); ?></label>
                                </th>
                                <td>
                                    <select name="spin_mode" id="spin_mode">
                                        <option value="word" <?php selected($settings['spin_mode'], 'word'); ?>><?php _e('同义词替换', 'visual-sitemap-baidu-seo'); ?></option>
                                        <option value="sentence" <?php selected($settings['spin_mode'], 'sentence'); ?>><?php _e('段落重组', 'visual-sitemap-baidu-seo'); ?></option>
                                        <option value="both" <?php selected($settings['spin_mode'], 'both'); ?>><?php _e('混合模式（推荐）', 'visual-sitemap-baidu-seo'); ?></option>
                                    </select>
                                    <p class="description">
                                        <?php _e('同义词替换：替换原文中的词汇为同义词；段落重组：调整句子和段落顺序；混合模式：两者结合', 'visual-sitemap-baidu-seo'); ?>
                                    </p>
                                </td>
                            </tr>
                        </table>
                    </div>
                    
                    <!-- 自定义同义词库 -->
                    <div class="vseo-subsection">
                        <h4><?php _e('自定义同义词库', 'visual-sitemap-baidu-seo'); ?></h4>
                        <p class="vseo-description"><?php _e('每行一个，格式：原文词|同义词1,同义词2,同义词3', 'visual-sitemap-baidu-seo'); ?></p>
                        
                        <table class="form-table">
                            <tr>
                                <th scope="row">
                                    <label for="custom_synonyms"><?php _e('同义词设置', 'visual-sitemap-baidu-seo'); ?></label>
                                </th>
                                <td>
                                    <textarea name="custom_synonyms" id="custom_synonyms" rows="10" class="large-text code" placeholder="使用|利用,应用,采用&#10;学习|掌握,了解,研习&#10;方法|方式,办法,途径"><?php echo esc_textarea($custom_synonyms_text); ?></textarea>
                                    <p class="description">
                                        <?php _e('添加行业相关的专业词汇，提升伪原创效果', 'visual-sitemap-baidu-seo'); ?>
                                    </p>
                                </td>
                            </tr>
                        </table>
                    </div>
                    
                    <!-- 保存按钮 -->
                    <p class="submit">
                        <input type="submit" name="visual_sitemap_baidu_save_spin_settings" class="button button-primary" value="<?php _e('保存伪原创配置', 'visual-sitemap-baidu-seo'); ?>">
                    </p>
                </form>
            </div>
            
            <!-- 蜘蛛访问日志 -->
            <div class="vseo-section">
                <h3 class="vseo-section-title"><?php _e('百度蜘蛛访问日志', 'visual-sitemap-baidu-seo'); ?></h3>
                <p class="vseo-description"><?php _e('最近10条百度蜘蛛访问记录', 'visual-sitemap-baidu-seo'); ?></p>
                
                <?php if (!empty($spider_logs)) : ?>
                    <table class="wp-list-table widefat fixed striped">
                        <thead>
                            <tr>
                                <th><?php _e('访问时间', 'visual-sitemap-baidu-seo'); ?></th>
                                <th><?php _e('蜘蛛类型', 'visual-sitemap-baidu-seo'); ?></th>
                                <th><?php _e('IP地址', 'visual-sitemap-baidu-seo'); ?></th>
                                <th><?php _e('访问URL', 'visual-sitemap-baidu-seo'); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($spider_logs as $log) : ?>
                                <tr>
                                    <td><?php echo esc_html($log->visit_time); ?></td>
                                    <td>
                                        <span class="vseo-badge vseo-badge-<?php echo esc_attr($log->spider_type); ?>">
                                            <?php
                                            $spider_types = array(
                                                'normal' => '普通蜘蛛',
                                                'render' => '渲染蜘蛛',
                                                'image' => '图片蜘蛛',
                                                'other' => '其他蜘蛛'
                                            );
                                            echo isset($spider_types[$log->spider_type]) ? $spider_types[$log->spider_type] : $log->spider_type;
                                            ?>
                                        </span>
                                    </td>
                                    <td><?php echo esc_html($log->ip_address); ?></td>
                                    <td>
                                        <a href="<?php echo esc_url($log->request_uri); ?>" target="_blank">
                                            <?php echo esc_html($log->request_uri); ?>
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php else : ?>
                    <p class="vseo-empty-state">
                        <?php _e('暂无百度蜘蛛访问记录', 'visual-sitemap-baidu-seo'); ?>
                    </p>
                <?php endif; ?>
            </div>
            
            <!-- 伪原创说明 -->
            <div class="vseo-section vseo-info-box">
                <h3 class="vseo-section-title"><?php _e('伪原创功能说明', 'visual-sitemap-baidu-seo'); ?></h3>
                <ul>
                    <li><strong><?php _e('差异化处理', 'visual-sitemap-baidu-seo'); ?></strong>：<?php _e('伪原创仅对百度蜘蛛生效，普通用户看到原始内容，不影响用户体验', 'visual-sitemap-baidu-seo'); ?></li>
                    <li><strong><?php _e('智能算法', 'visual-sitemap-baidu-seo'); ?></strong>：<?php _e('基于同义词替换和段落重组，支持自定义同义词库，提升内容差异化', 'visual-sitemap-baidu-seo'); ?></li>
                    <li><strong><?php _e('缓存机制', 'visual-sitemap-baidu-seo'); ?></strong>：<?php _e('伪原创结果会被缓存，同一文章只处理一次，减少服务器负载', 'visual-sitemap-baidu-seo'); ?></li>
                    <li><strong><?php _e('强度选择', 'visual-sitemap-baidu-seo'); ?></strong>：<?php _e('2-3级强度平衡了效果和可读性，建议正式内容使用此范围', 'visual-sitemap-baidu-seo'); ?></li>
                    <li><strong><?php _e('合规使用', 'visual-sitemap-baidu-seo'); ?></strong>：<?php _e('伪原创用于提升内容差异化，不应用于抄袭他人内容，遵守百度搜索质量指南', 'visual-sitemap-baidu-seo'); ?></li>
                </ul>
            </div>
        </div>
    </div>
    <?php
}
