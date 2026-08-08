<?php
/**
 * 首页入口（布局分派器）。
 *
 * 在「外观 → 自定义 → 首页 → 首页布局」中选择整站方案：
 * - qingjian-classic 书卷经典（竹青书卷 + 经典布局 + 文字列表）
 * - ink-minimal     素简文章（水墨素简 + 极简布局 + 文字列表）
 * - gray-portal     现代门户（青灰现代 + 门户综合 + 图文卡片）
 * - coffee-magazine 复古杂志（暖咖复古 + 杂志画报 + 瀑布流卡片）
 *
 * 一套方案 = 配色 + 板块布局 + 列表样式，切换即整套换肤。
 *
 * @package Qingya
 */

get_header();

// 方案拆解：取布局模板名（旧版值自动映射到新方案）。
$qingya_home_layout = qingya_home_scheme()['layout'];

// 目录形式片段：get_template_part('template-parts/home/portal') → template-parts/home/portal.php。
get_template_part( 'template-parts/home/' . $qingya_home_layout );

get_footer();
