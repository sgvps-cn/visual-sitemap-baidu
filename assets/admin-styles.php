<?php
if (!defined('ABSPATH')) {
    exit('禁止直接访问！');
}

function visual_sitemap_baidu_load_admin_assets() {
    add_action('admin_enqueue_scripts', 'visual_sitemap_baidu_admin_assets');
}

function visual_sitemap_baidu_admin_assets($hook) {
    $css = "
        .vseo-card {
            background: #fff;
            border-radius: 8px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
            margin: 20px 0;
            padding: 20px;
            transition: box-shadow 0.3s ease;
        }
        .vseo-card:hover {
            box-shadow: 0 3px 10px rgba(0,0,0,0.15);
        }
        .vseo-card-header {
            border-bottom: 1px solid #eee;
            padding-bottom: 15px;
            margin-bottom: 20px;
        }
        .vseo-card-title {
            margin: 0;
            color: #1d2327;
            font-size: 1.3em;
        }
        .vseo-status-success {
            border-left: 4px solid #46b450;
            background: #f6ffed;
            padding: 15px;
            margin-bottom: 20px;
        }
        .vseo-status-error {
            border-left: 4px solid #dc3232;
            background: #fef0f0;
            padding: 15px;
            margin-bottom: 20px;
        }
        .vseo-status-info {
            border-left: 4px solid #007cba;
            background: #e8f0fe;
            padding: 15px;
            margin-bottom: 20px;
        }
        .vseo-btn-group {
            margin: 15px 0;
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
        }
        .vseo-table {
            width: 100%;
            border-collapse: collapse;
            margin: 15px 0;
        }
        .vseo-table th {
            text-align: left;
            padding: 12px 15px;
            background: #f8f9fa;
            border-bottom: 1px solid #eee;
        }
        .vseo-table td {
            padding: 12px 15px;
            border-bottom: 1px solid #eee;
            vertical-align: top;
        }
        .vseo-table tr:last-child td {
            border-bottom: none;
        }
        .vseo-table tr:hover td {
            background: #f8f9fa;
        }
        .vseo-section {
            margin-bottom: 30px;
        }
        .vseo-section-title {
            font-size: 1.1em;
            margin: 0 0 15px 0;
            color: #1d2327;
            padding-bottom: 8px;
            border-bottom: 1px solid #eee;
        }
        .vseo-description {
            color: #646970;
            margin: 5px 0 15px 0;
            font-size: 0.9em;
        }
        .vseo-notice {
            padding: 12px 15px;
            border-radius: 4px;
            margin: 15px 0;
            font-size: 0.9em;
        }
        .vseo-log-container {
            max-height: 400px;
            overflow-y: auto;
            border: 1px solid #eee;
            border-radius: 4px;
        }
        .vseo-permission-warning {
            color: #dc3232;
            font-weight: bold;
            margin: 10px 0;
        }
        .vseo-priority-input {
            width: 60px !important;
            text-align: center;
        }
        @media (max-width: 768px) {
            .vseo-btn-group {
                flex-direction: column;
            }
            .vseo-btn-group .button {
                width: 100%;
            }
            .vseo-table th, .vseo-table td {
                padding: 8px 10px;
                font-size: 0.9em;
            }
        }
    ";
    
    wp_add_inline_style('wp-admin', $css);
    
    // 添加简单的JS增强交互
    $js = "
        document.addEventListener('DOMContentLoaded', function() {
            // 优先级开关联动
            const prioritySwitch = document.querySelector('input[name=\"enable_priority\"]');
            if (prioritySwitch) {
                const priorityInputs = document.querySelectorAll('.vseo-priority-input');
                const togglePriorityInputs = function() {
                    priorityInputs.forEach(input => {
                        input.disabled = !prioritySwitch.checked;
                    });
                };
                
                togglePriorityInputs();
                prioritySwitch.addEventListener('change', togglePriorityInputs);
            }
            
            // 确认操作提示
            const clearLogBtn = document.querySelector('input[name=\"visual_sitemap_baidu_clear_log\"]');
            if (clearLogBtn) {
                clearLogBtn.addEventListener('click', function(e) {
                    if (!confirm('确定要清空所有日志吗？此操作不可恢复！')) {
                        e.preventDefault();
                    }
                });
            }
        });
    ";
    
    wp_add_inline_script('jquery', $js);
}
