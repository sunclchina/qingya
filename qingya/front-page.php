<?php
/**
 * 首页入口（布局分派器）。
 *
 * 在「外观 → 自定义 → 首页」中选择布局：
 * - classic  经典首页（原版：轮播 + 推荐 + 最新列表）
 * - portal   门户综合（分类直达 / 热门高赞 / 开源项目 / 股市消息）
 * - magazine 杂志资讯（头条 + 网格 + 财经快讯条）
 * - minimal  极简文章（分类直达 + 文章列表 + 快讯折叠）
 *
 * 随时可在 Customizer 中切回「经典」恢复原首页。
 *
 * @package Qingya
 */

get_header();

$qingya_home_layout = get_theme_mod( 'qy_home_layout', 'classic' );
if ( ! in_array( $qingya_home_layout, array( 'classic', 'portal', 'magazine', 'minimal' ), true ) ) {
	$qingya_home_layout = 'classic';
}

// 目录形式片段：get_template_part('template-parts/home/portal') → template-parts/home/portal.php。
get_template_part( 'template-parts/home/' . $qingya_home_layout );

get_footer();
