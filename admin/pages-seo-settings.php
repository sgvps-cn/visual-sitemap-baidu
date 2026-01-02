<?php
if (!defined('ABSPATH')) {
    exit('禁止直接访问！');
}

function visual_sitemap_baidu_seo_settings_page() {
    // 权限检查
    if (!current_user_can('manage_options')) {
        wp_die(__('您没有足够的权限访问此页面！', 'visual-sitemap-baidu-seo'));
    }
    
    $messages = array();

    // 保存配置
    if (isset($_POST['visual_sitemap_baidu_save_seo_settings'])) {
        check_admin_referer('visual_sitemap_baidu_seo_settings_nonce');
        
        $settings = VisualSitemap_SettingsManager::getSettings();
        
        // 安全过滤和验证
        $settings['enable_seo_meta'] = isset($_POST['enable_seo_meta']) ? 1 : 0;
        $settings['enable_robots'] = isset($_POST['enable_robots']) ? 1 : 0;
        $settings['enable_auto_push'] = isset($_POST['enable_auto_push']) ? 1 : 0;
        $settings['enable_priority'] = isset($_POST['enable_priority']) ? 1 : 0;
        
        // 优先级设置
        $priority_fields = array('home', 'post', 'page', 'category', 'tag');
        foreach ($priority_fields as $field) {
            $value = floatval($_POST[$field . '_priority']);
            $value = max(0.1, min(1.0, $value)); // 限制在0.1-1.0之间
            $settings[$field . '_priority'] = strval($value);
        }
        
        // 更新配置
        VisualSitemap_SettingsManager::saveSettings($settings);
        
        $messages[] = array(
            'type' => 'updated',
            'text' => __('SEO优化配置保存成功！', 'visual-sitemap-baidu-seo')
        );
        
        // 记录日志
        $log = new VisualSitemap_LogManager();
        $log->log('seo_settings', 'SEO优化配置已更新', 'success');
    }

    // 获取当前配置
    $settings = VisualSitemap_SettingsManager::getSettings();
    
    // 输出页面
    ?>
    <div class="wrap">
        <h1><?php _e('SEO优化设置', 'visual-sitemap-baidu-seo'); ?></h1>
        
        <?php
        // 显示消息提示
        foreach ($messages as $msg) {
            echo "<div class='{$msg['type']} notice is-dismissible'><p>{$msg['text']}</p></div>";
        }
        ?>
        
        <div class="vseo-card" style="max-width:800px;">
            <!-- 基础SEO优化 -->
            <div class="vseo-section">
                <h3 class="vseo-section-title"><?php _e('基础SEO优化', 'visual-sitemap-baidu-seo'); ?></h3>
                <p class="vseo-description"><?php _e('启用以下功能可以全面提升网站的SEO表现', 'visual-sitemap-baidu-seo'); ?></p>
                
                <form method="post">
                    <?php wp_nonce_field('visual_sitemap_baidu_seo_settings_nonce'); ?>
                    
                    <table class="form-table">
                        <tr>
                            <th scope="row">
                                <label for="enable_robots"><?php _e('生成优化的robots.txt', 'visual-sitemap-baidu-seo'); ?></label>
                            </th>
                            <td>
                                <input type="checkbox" name="enable_robots" id="enable_robots" value="1" <?php checked($settings['enable_robots'], 1); ?>>
                                <label for="enable_robots"><?php _e('自动生成SEO友好的robots.txt文件', 'visual-sitemap-baidu-seo'); ?></label>
                                <p class="description"><?php _e('启用后，插件会自动生成robots.txt，阻止低质量页面被收录', 'visual-sitemap-baidu-seo'); ?></p>
                            </td>
                        </tr>
                        
                        <tr>
                            <th scope="row">
                                <label for="enable_auto_push"><?php _e('文章发布实时推送', 'visual-sitemap-baidu-seo'); ?></label>
                            </th>
                            <td>
                                <input type="checkbox" name="enable_auto_push" id="enable_auto_push" value="1" <?php checked($settings['enable_auto_push'], 1); ?>>
                                <label for="enable_auto_push"><?php _e('发布文章时立即推送到百度', 'visual-sitemap-baidu-seo'); ?></label>
                                <p class="description"><?php _e('启用后，每次发布新文章都会自动推送到百度API，加速收录', 'visual-sitemap-baidu-seo'); ?></p>
                            </td>
                        </tr>
                        
                        <tr>
                            <th scope="row">
                                <label for="enable_priority"><?php _e('启用Sitemap优先级', 'visual-sitemap-baidu-seo'); ?></label>
                            </th>
                            <td>
                                <input type="checkbox" name="enable_priority" id="enable_priority" value="1" <?php checked($settings['enable_priority'], 1); ?>>
                                <label for="enable_priority"><?php _e('在Sitemap中设置页面优先级', 'visual-sitemap-baidu-seo'); ?></label>
                                <p class="description"><?php _e('启用后，可以为不同类型的页面设置不同的优先级（0.1-1.0）', 'visual-sitemap-baidu-seo'); ?></p>
                            </td>
                        </tr>
                    </table>
                    
                    <!-- Sitemap优先级配置 -->
                    <div class="vseo-subsection">
                        <h4><?php _e('Sitemap优先级配置', 'visual-sitemap-baidu-seo'); ?></h4>
                        <p class="vseo-description"><?php _e('优先级范围：0.1（最低）- 1.0（最高），建议首页设置最高优先级', 'visual-sitemap-baidu-seo'); ?></p>
                        
                        <table class="form-table">
                            <tr>
                                <th scope="row" style="width:200px;">
                                    <label for="home_priority"><?php _e('首页优先级', 'visual-sitemap-baidu-seo'); ?></label>
                                </th>
                                <td>
                                    <input type="number" name="home_priority" id="home_priority" value="<?php echo esc_attr($settings['home_priority']); ?>" min="0.1" max="1.0" step="0.1" class="small-text">
                                    <span class="description"><?php _e('建议：1.0（最高）', 'visual-sitemap-baidu-seo'); ?></span>
                                </td>
                            </tr>
                            
                            <tr>
                                <th scope="row">
                                    <label for="post_priority"><?php _e('文章页优先级', 'visual-sitemap-baidu-seo'); ?></label>
                                </th>
                                <td>
                                    <input type="number" name="post_priority" id="post_priority" value="<?php echo esc_attr($settings['post_priority']); ?>" min="0.1" max="1.0" step="0.1" class="small-text">
                                    <span class="description"><?php _e('建议：0.8', 'visual-sitemap-baidu-seo'); ?></span>
                                </td>
                            </tr>
                            
                            <tr>
                                <th scope="row">
                                    <label for="page_priority"><?php _e('页面优先级', 'visual-sitemap-baidu-seo'); ?></label>
                                </th>
                                <td>
                                    <input type="number" name="page_priority" id="page_priority" value="<?php echo esc_attr($settings['page_priority']); ?>" min="0.1" max="1.0" step="0.1" class="small-text">
                                    <span class="description"><?php _e('建议：0.7', 'visual-sitemap-baidu-seo'); ?></span>
                                </td>
                            </tr>
                            
                            <tr>
                                <th scope="row">
                                    <label for="category_priority"><?php _e('分类页优先级', 'visual-sitemap-baidu-seo'); ?></label>
                                </th>
                                <td>
                                    <input type="number" name="category_priority" id="category_priority" value="<?php echo esc_attr($settings['category_priority']); ?>" min="0.1" max="1.0" step="0.1" class="small-text">
                                    <span class="description"><?php _e('建议：0.6', 'visual-sitemap-baidu-seo'); ?></span>
                                </td>
                            </tr>
                            
                            <tr>
                                <th scope="row">
                                    <label for="tag_priority"><?php _e('标签页优先级', 'visual-sitemap-baidu-seo'); ?></label>
                                </th>
                                <td>
                                    <input type="number" name="tag_priority" id="tag_priority" value="<?php echo esc_attr($settings['tag_priority']); ?>" min="0.1" max="1.0" step="0.1" class="small-text">
                                    <span class="description"><?php _e('建议：0.5', 'visual-sitemap-baidu-seo'); ?></span>
                                </td>
                            </tr>
                        </table>
                    </div>
                    
                    <!-- 保存按钮 -->
                    <p class="submit">
                        <input type="submit" name="visual_sitemap_baidu_save_seo_settings" class="button button-primary" value="<?php _e('保存SEO配置', 'visual-sitemap-baidu-seo'); ?>">
                    </p>
                </form>
            </div>
            
            <!-- SEO说明 -->
            <div class="vseo-section vseo-info-box">
                <h3 class="vseo-section-title"><?php _e('SEO优化说明', 'visual-sitemap-baidu-seo'); ?></h3>
                <ul>
                    <li><strong><?php _e('Robots.txt优化', 'visual-sitemap-baidu-seo'); ?></strong>：<?php _e('自动生成符合百度SEO规范的robots.txt，禁止搜索引擎抓取管理后台、登录页等低质量页面', 'visual-sitemap-baidu-seo'); ?></li>
                    <li><strong><?php _e('实时推送', 'visual-sitemap-baidu-seo'); ?></strong>：<?php _e('文章发布后立即推送到百度API，相比定时推送可以提前数小时被收录', 'visual-sitemap-baidu-seo'); ?></li>
                    <li><strong><?php _e('Sitemap优先级', 'visual-sitemap-baidu-seo'); ?></strong>：<?php _e('告诉搜索引擎哪些页面更重要，首页和核心内容页应设置较高优先级', 'visual-sitemap-baidu-seo'); ?></li>
                </ul>
            </div>
        </div>
    </div>
    <?php
}
