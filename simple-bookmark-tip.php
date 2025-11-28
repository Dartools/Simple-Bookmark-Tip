<?php
/**
 * Plugin Name: Simple Bookmark Tip (收藏引导提示)
 * Plugin URI:  https://toolsdar.cn/
 * Description: 一个轻量级的 Ctrl+D / Cmd+D 收藏引导提示，支持自定义位置、颜色、设备显示限制及复现时间。
 * Version:     1.2.0
 * Author:      工具达人
 * License:     GPL2
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit; // 防止直接访问
}

// 1. 注册后台设置菜单
add_action('admin_menu', 'sbt_add_admin_menu');
function sbt_add_admin_menu() {
    add_options_page(
        '收藏引导设置', 
        '收藏引导设置', 
        'manage_options', 
        'simple-bookmark-tip', 
        'sbt_options_page'
    );
}

// 2. 注册设置选项
add_action('admin_init', 'sbt_settings_init');
function sbt_settings_init() {
    register_setting('sbtPlugin', 'sbt_settings');
    
    add_settings_section(
        'sbt_plugin_section', 
        '外观与显示设置', 
        'sbt_settings_section_callback', 
        'simple-bookmark-tip'
    );

    add_settings_field('sbt_position_top', '距离顶部距离 (px)', 'sbt_position_top_render', 'simple-bookmark-tip', 'sbt_plugin_section');
    add_settings_field('sbt_position_left', '距离左侧距离 (px)', 'sbt_position_left_render', 'simple-bookmark-tip', 'sbt_plugin_section');
    add_settings_field('sbt_bg_color', '背景颜色', 'sbt_bg_color_render', 'simple-bookmark-tip', 'sbt_plugin_section');
    add_settings_field('sbt_min_width', '最小屏幕宽度限制 (px)', 'sbt_min_width_render', 'simple-bookmark-tip', 'sbt_plugin_section');
    
    // 【新增】关闭后重新出现时间
    add_settings_field('sbt_reappear_days', '关闭后重新出现 (天)', 'sbt_reappear_days_render', 'simple-bookmark-tip', 'sbt_plugin_section');
}

// 回调函数：渲染字段
function sbt_settings_section_callback() { 
    echo '在此处调整弹窗的位置、颜色以及显示的设备规则。'; 
}

function sbt_position_top_render() { 
    $options = get_option('sbt_settings');
    $val = isset($options['sbt_position_top']) ? $options['sbt_position_top'] : '80';
    echo "<input type='number' name='sbt_settings[sbt_position_top]' value='{$val}'> px";
}

function sbt_position_left_render() { 
    $options = get_option('sbt_settings');
    $val = isset($options['sbt_position_left']) ? $options['sbt_position_left'] : '20';
    echo "<input type='number' name='sbt_settings[sbt_position_left]' value='{$val}'> px";
}

function sbt_bg_color_render() { 
    $options = get_option('sbt_settings');
    $val = isset($options['sbt_bg_color']) ? $options['sbt_bg_color'] : '#3B4CCA';
    echo "<input type='color' name='sbt_settings[sbt_bg_color]' value='{$val}'>";
}

function sbt_min_width_render() { 
    $options = get_option('sbt_settings');
    $val = isset($options['sbt_min_width']) ? $options['sbt_min_width'] : '768';
    echo "<input type='number' name='sbt_settings[sbt_min_width]' value='{$val}'> px";
    echo "<p class='description'>屏幕宽度小于此数值时不显示。填 768 可屏蔽手机；填 0 则全设备显示。</p>";
}

// 【新增】重新出现天数渲染函数
function sbt_reappear_days_render() {
    $options = get_option('sbt_settings');
    // 默认为 0 (不重新出现)
    $val = isset($options['sbt_reappear_days']) ? $options['sbt_reappear_days'] : '0';
    echo "<input type='number' name='sbt_settings[sbt_reappear_days]' value='{$val}' min='0'> 天";
    echo "<p class='description'>用户点击关闭后，经过多少天再次显示？<br><strong>0</strong> = 永久不再显示（除非清除缓存）。<br><strong>7</strong> = 7天后再次提示。</p>";
}

// 3. 后台页面 HTML
function sbt_options_page() {
    ?>
    <div class="wrap">
        <h1>收藏引导提示 (Bookmark Tip) 设置</h1>
        <form action="options.php" method="post">
            <?php
            settings_fields('sbtPlugin');
            do_settings_sections('simple-bookmark-tip');
            submit_button();
            ?>
        </form>
        <div style="margin-top: 20px; padding: 15px; background: #fff; border: 1px solid #ccc;">
            <strong>调试小技巧：</strong><br>
            如果你点击过“关闭”，想再次测试效果，请在浏览器按 F12 打开控制台，输入下面的代码并回车，然后刷新页面：<br>
            <code>localStorage.removeItem('bookmark_tip_last_closed_time');</code>
        </div>
    </div>
    <?php
}

// 4. 前台输出代码
add_action('wp_footer', 'sbt_render_frontend_popup');
function sbt_render_frontend_popup() {
    // 获取设置
    $options = get_option('sbt_settings');
    $top = isset($options['sbt_position_top']) ? $options['sbt_position_top'] : '80';
    $left = isset($options['sbt_position_left']) ? $options['sbt_position_left'] : '20';
    $color = isset($options['sbt_bg_color']) ? $options['sbt_bg_color'] : '#3B4CCA';
    $min_width = isset($options['sbt_min_width']) ? $options['sbt_min_width'] : '768';
    
    // 【新增】获取重现天数
    $reappear_days = isset($options['sbt_reappear_days']) ? $options['sbt_reappear_days'] : '0';
    ?>
    
    <div id="bookmark-tip-container" style="display:none;">
        <div class="bookmark-tip-wrapper">
            <div class="bookmark-arrow"></div>
            <button onclick="closeBookmarkTip()" class="bookmark-close-btn" aria-label="关闭">
                <svg width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path d="M6 18L18 6M6 6l12 12" stroke-linecap="round" stroke-linejoin="round"></path>
                </svg>
            </button>
            <div class="bookmark-content">
                <p class="bookmark-text">
                    拖拽左侧LOGO到浏览器「书签栏」<br>下次访问更便捷～
                </p>
                <div class="bookmark-keys">
                    <kbd class="bookmark-key" id="key-modifier">Ctrl</kbd>
                    <span class="bookmark-plus">+</span>
                    <kbd class="bookmark-key">D</kbd>
                </div>
            </div>
        </div>
    </div>

    <style>
        #bookmark-tip-container {
            position: fixed;
            top: <?php echo esc_attr($top); ?>px;
            left: <?php echo esc_attr($left); ?>px;
            z-index: 99999;
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
            animation: bookmarkFadeIn 0.5s ease-out;
        }
        .bookmark-tip-wrapper {
            background-color: <?php echo esc_attr($color); ?>;
            color: white;
            padding: 1.5rem;
            border-radius: 0.75rem;
            box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05);
            position: relative;
            max-width: 320px;
        }
        .bookmark-arrow {
            position: absolute;
            width: 0;
            height: 0;
            border-top: 10px solid transparent;
            border-bottom: 10px solid transparent;
            border-right: 10px solid <?php echo esc_attr($color); ?>;
            left: -10px;
            top: 20px;
        }
        .bookmark-close-btn {
            position: absolute;
            top: 8px;
            right: 8px;
            background: none;
            border: none;
            color: rgba(255, 255, 255, 0.7);
            cursor: pointer;
            padding: 0;
        }
        .bookmark-close-btn:hover { color: #fff; }
        .bookmark-content { display: flex; flex-direction: column; gap: 1rem; }
        .bookmark-text { font-size: 14px; line-height: 1.6; margin: 0; padding-right: 15px; }
        .bookmark-keys { display: flex; align-items: center; gap: 0.5rem; }
        .bookmark-key {
            background-color: #000;
            color: #FDE047;
            border: 1px solid #374151;
            border-radius: 0.375rem;
            padding: 0.3rem 0.6rem;
            font-size: 0.75rem;
            font-weight: 600;
            box-shadow: 0 1px 0 #374151;
        }
        @keyframes bookmarkFadeIn {
            from { opacity: 0; transform: translateX(-10px); }
            to { opacity: 1; transform: translateX(0); }
        }
    </style>

    <script>
    document.addEventListener("DOMContentLoaded", function() {
        var minWidth = <?php echo intval($min_width); ?>;
        var reappearDays = <?php echo floatval($reappear_days); ?>; // 获取后台设置的天数

        // 1. 屏幕宽度检查
        if (window.innerWidth < minWidth) {
            return;
        }

        // 2. 检查是否显示逻辑
        var shouldShow = true;
        var lastClosedTime = localStorage.getItem('bookmark_tip_last_closed_time');

        if (lastClosedTime) {
            if (reappearDays === 0) {
                // 如果设置为0，且有关闭记录，则永久不显示
                shouldShow = false;
            } else {
                // 计算时间差
                var now = new Date().getTime();
                var timeDiff = now - parseInt(lastClosedTime);
                var daysDiff = timeDiff / (1000 * 3600 * 24); // 转换为天数

                // 如果过去的天数还没达到设定值，则不显示
                if (daysDiff < reappearDays) {
                    shouldShow = false;
                }
            }
        }

        // 3. 执行显示
        if (shouldShow) {
            // Mac 系统文案适配
            var isMac = navigator.platform.toUpperCase().indexOf('MAC') >= 0;
            if (isMac) {
                var modKey = document.getElementById('key-modifier');
                if(modKey) modKey.innerText = 'Cmd';
            }
            
            var container = document.getElementById('bookmark-tip-container');
            if(container) container.style.display = 'block';
        }
    });

    function closeBookmarkTip() {
        document.getElementById('bookmark-tip-container').style.display = 'none';
        // 【关键修改】存储当前时间戳，而不是简单的 true
        localStorage.setItem('bookmark_tip_last_closed_time', new Date().getTime());
    }
    </script>
    <?php
}
