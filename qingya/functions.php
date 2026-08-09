<?php
/**
 * 青崖主题（Qingya）—— 主加载器
 *
 * 模块化低耦合：本文件只负责加载模块，不承载业务逻辑。
 * 各模块职责单一，按需注册钩子。
 *
 * @package Qingya
 * @version 1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // 禁止直接访问。
}

define( 'QINGYA_VERSION', '1.8.4' );
define( 'QINGYA_DIR', get_template_directory() );
define( 'QINGYA_URI', get_template_directory_uri() );

/**
 * 强制使用经典小工具界面（传统 WP_Widget 拖拽排序，区块式界面拖拽不顺畅）。
 */
add_filter( 'use_widgets_block_editor', '__return_false' );

/**
 * 模块清单（顺序即加载顺序）。
 * setup 先行，其余模块依赖其注册的基础设施。
 */
$qingya_modules = array(
	'setup',
	'template-tags',
	'performance',
	'seo',
	'security',
	'ip-blacklist',
	'attack-guard',
	'customizer',
	'meta-boxes',
	'avatar',
	'widgets',
	'ajax',
	'ai-chatbot',
	'geo-block',
	'home-layouts',
	'stock-news',
	'analytics',
	'updater',
);

foreach ( $qingya_modules as $qingya_module ) {
	$qingya_file = QINGYA_DIR . '/inc/' . $qingya_module . '.php';
	if ( file_exists( $qingya_file ) ) {
		require_once $qingya_file;
	}
}
unset( $qingya_modules, $qingya_module, $qingya_file );

// 后台专用模块（仅管理员上下文加载，前台零开销）。
if ( is_admin() ) {
	$qingya_admin_files = array(
		QINGYA_DIR . '/admin/ip-blacklist.php',
		QINGYA_DIR . '/admin/analytics.php',
		QINGYA_DIR . '/admin/attack-guard.php',
	);
	foreach ( $qingya_admin_files as $qingya_admin_file ) {
		if ( file_exists( $qingya_admin_file ) ) {
			require_once $qingya_admin_file;
		}
	}
	unset( $qingya_admin_files, $qingya_admin_file );
}
