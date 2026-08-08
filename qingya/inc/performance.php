<?php
/**
 * 性能优化模块：
 * - 资源版本化（filemtime，缓存友好）
 * - 按需加载（首页不加载冗余脚本）
 * - CDN 域名替换（Customizer 可配）
 * - 图片懒加载与响应式（原生 + 开关）
 * - 首屏优化（延迟非核心资源）
 *
 * @package Qingya
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * 资源版本化：开发期用 filemtime，生产环境可用常量覆盖。
 *
 * @return string
 */
function qingya_asset_version() {
	if ( defined( 'QINGYA_ASSET_VERSION' ) ) {
		return QINGYA_ASSET_VERSION;
	}
	$file = QINGYA_DIR . '/assets/css/main.css';
	return file_exists( $file ) ? (string) filemtime( $file ) : QINGYA_VERSION;
}

/**
 * CDN 域名替换：仅替换主题 assets 目录下的静态资源（css/js/img 子目录）。
 * 主题自身资源走 CDN，上传目录与插件资源不受影响。
 *
 * @param string $url  资源 URL。
 * @param string $type 类型（css/js/img）。
 * @return string
 */
function qingya_cdn_url( $url, $type = 'img' ) {
	$cdn = get_theme_mod( 'qy_perf_cdn', '' );
	if ( empty( $cdn ) ) {
		return $url;
	}
	$cdn  = untrailingslashit( esc_url_raw( $cdn ) );
	$uri  = untrailingslashit( QINGYA_URI );
	$base = $uri . '/assets/';
	if ( 0 === strpos( $url, $base . $type . '/' ) ) {
		return $cdn . '/' . $type . '/' . substr( $url, strlen( $base . $type . '/' ) );
	}
	return $url;
}

/**
 * 注册前端资源（版本化 + CDN）。
 */
function qingya_perf_enqueue() {
	$ver = qingya_asset_version();

	// 主样式：开发期 filemtime 版本，避免缓存失效。
	wp_enqueue_style( 'qingya-main', qingya_cdn_url( QINGYA_URI . '/assets/css/main.css', 'css' ), array(), $ver );

	// 主脚本：延迟到 footer 加载，不阻塞首屏。
	// 文件名避开通用拦截规则（如 main.js 常被浏览器广告拦截误杀）。
	wp_enqueue_script( 'qingya-main', qingya_cdn_url( QINGYA_URI . '/assets/js/qingya.js', 'js' ), array(), $ver, true );

	// 评论回复脚本（仅在需要时加载）。
	if ( is_singular() && comments_open() && get_option( 'thread_comments' ) ) {
		wp_enqueue_script( 'comment-reply' );
	}

	// 内联 CSS 变量（Customizer 配色/字体/布局实时生效）。
	wp_add_inline_style( 'qingya-main', qingya_get_dynamic_css() );

	// AJAX 配置（仅前端需要）。
	wp_localize_script( 'qingya-main', 'qingyaData', array(
		'ajaxUrl' => admin_url( 'admin-ajax.php' ),
		'nonce'   => wp_create_nonce( 'qingya_ajax' ),
	) );
}
add_action( 'wp_enqueue_scripts', 'qingya_perf_enqueue', 20 );

/**
 * 首屏优先：文章页正文前不加载懒加载脚本（原生 loading=lazy 已覆盖）。
 * 这里统一处理：非必要脚本全部 defer。
 *
 * @param string $tag    标签。
 * @param string $handle 句柄。
 * @return string
 */
function qingya_defer_scripts( $tag, $handle ) {
	if ( 'qingya-main' !== $handle && 'comment-reply' !== $handle ) {
		return $tag;
	}
	if ( false !== strpos( $tag, 'defer' ) ) {
		return $tag;
	}
	// 不 defer comment-reply（依赖 DOM 就绪时序），仅 defer 主题脚本。
	if ( 'qingya-main' === $handle ) {
		return str_replace( ' src=', ' defer src=', $tag );
	}
	return $tag;
}
add_filter( 'script_loader_tag', 'qingya_defer_scripts', 10, 2 );

/**
 * 图片懒加载：WordPress 6.0+ 原生支持 loading="lazy"，
 * 此过滤器为旧图片/附件补充属性，并按 Customizer 开关控制。
 *
 * @param string $content 内容。
 * @return string
 */
function qingya_image_lazyload( $content ) {
	if ( 'off' === get_theme_mod( 'qy_perf_lazyload', 'on' ) ) {
		return $content;
	}
	if ( is_admin() || is_feed() || ( function_exists( 'is_amp_endpoint' ) && is_amp_endpoint() ) ) {
		return $content;
	}
	// 仅对未带 loading 属性的 <img> 补充（原生属性优先）。
	return preg_replace( '/<img(?![^>]*loading=)/i', '<img loading="lazy" decoding="async"', $content );
}
add_filter( 'the_content', 'qingya_image_lazyload', 20 );

/**
 * 图片 ALT 自动获取：无 alt 的图片用附件标题/文件名填充（SEO 要求）。
 *
 * @param string $content 内容。
 * @return string
 */
function qingya_image_alt( $content ) {
	if ( is_admin() || is_feed() ) {
		return $content;
	}
	return preg_replace_callback(
		'/<img(?![^>]*alt=)[^>]*src=["\']([^"\']+)["\'][^>]*>/i',
		function ( $m ) {
			$alt = '';
			$src = $m[1];
			// 尝试从附件库取标题。
			$attachment_id = attachment_url_to_postid( $src );
			if ( $attachment_id ) {
				$alt = get_the_title( $attachment_id );
			}
			if ( empty( $alt ) ) {
				// 用文件名（去扩展名、去连字符）。
				$base = basename( parse_url( $src, PHP_URL_PATH ) );
				$alt  = ucwords( str_replace( array( '-', '_', '.' ), ' ', pathinfo( $base, PATHINFO_FILENAME ) ) );
			}
			return str_replace( '<img', '<img alt="' . esc_attr( $alt ) . '"', $m[0] );
		},
		$content
	);
}
add_filter( 'the_content', 'qingya_image_alt', 21 );

/**
 * 移除 WP 核心冗余资源（可选，Customizer 开关）：
 * - emoji 脚本（页面轻量化）
 * - 全局样式表（block-library 等，经典主题按需加载）
 * 默认全部关闭，避免影响插件兼容；由用户显式开启。
 */
function qingya_trim_assets() {
	if ( 'on' !== get_theme_mod( 'qy_perf_trim', 'off' ) ) {
		return;
	}
	// 禁用 emoji。
	remove_action( 'wp_head', 'print_emoji_detection_script', 7 );
	remove_action( 'wp_print_styles', 'print_emoji_styles' );
	remove_action( 'admin_print_scripts', 'print_emoji_detection_script' );
	remove_action( 'admin_print_styles', 'print_emoji_styles' );
	remove_filter( 'the_content_feed', 'wp_staticize_emoji' );
	remove_filter( 'comment_text_rss', 'wp_staticize_emoji' );
	remove_filter( 'wp_mail', 'wp_staticize_emoji_for_email' );

	// 禁用 embed 脚本。
	wp_deregister_script( 'wp-embed' );
}
add_action( 'init', 'qingya_trim_assets' );

/**
 * 禁用 jQuery Migrate（轻量化，若主题/插件不依赖旧 API）。
 * 默认关闭——兼容优先。
 */
function qingya_no_jquery_migrate( $scripts ) {
	if ( 'on' !== get_theme_mod( 'qy_perf_nomigrate', 'off' ) ) {
		return;
	}
	if ( ! is_admin() && isset( $scripts->registered['jquery'] ) ) {
		$script = $scripts->registered['jquery'];
		if ( $script->deps ) {
			$script->deps = array_diff( $script->deps, array( 'jquery-migrate' ) );
		}
	}
}
add_action( 'wp_default_scripts', 'qingya_no_jquery_migrate' );

/**
 * 为样式/脚本 URL 移除版本参数（配合服务器缓存规则，可选）。
 * 默认关闭——开启后主题更新可能触发缓存旧资源，由 CDN/服务器缓存策略兜底。
 */
function qingya_strip_asset_ver( $src ) {
	if ( 'on' !== get_theme_mod( 'qy_perf_ver', 'off' ) ) {
		return $src;
	}
	if ( false !== strpos( $src, QINGYA_URI ) ) {
		$src = remove_query_arg( 'ver', $src );
	}
	return $src;
}
add_filter( 'style_loader_src', 'qingya_strip_asset_ver', 10, 1 );
add_filter( 'script_loader_src', 'qingya_strip_asset_ver', 10, 1 );
