<?php
/**
 * 主题初始化：主题支持、菜单、侧边栏、图片尺寸、资源加载。
 *
 * @package Qingya
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * 主题支持与注册。
 */
function qingya_setup() {
	// 国际化（默认中文界面，预留翻译能力）。
	load_theme_textdomain( 'qingya', QINGYA_DIR . '/languages' );

	// 基础支持。
	add_theme_support( 'title-tag' );
	add_theme_support( 'post-thumbnails' );
	add_theme_support( 'automatic-feed-links' );
	add_theme_support( 'html5', array( 'search-form', 'comment-form', 'comment-list', 'gallery', 'caption', 'style', 'script' ) );
	add_theme_support( 'custom-logo', array(
		'height'      => 80,
		'width'       => 320,
		'flex-height' => true,
		'flex-width'  => true,
	) );
	add_theme_support( 'customize-selective-refresh-widgets' );
	add_theme_support( 'responsive-embeds' );
	add_theme_support( 'align-wide' );

	// 文章格式（轻量支持）。
	add_theme_support( 'post-formats', array( 'aside', 'gallery', 'link', 'image', 'quote', 'status', 'video', 'audio' ) );

	// 图片尺寸。
	add_image_size( 'qingya-card', 600, 400, true );   // 卡片图（4:3 裁剪）。
	add_image_size( 'qingya-wide', 1200, 560, true );  // 文章头图 / 轮播。

	// 菜单。
	register_nav_menus( array(
		'primary' => __( '主导航', 'qingya' ),
		'footer'  => __( '页脚导航', 'qingya' ),
	) );
}
add_action( 'after_setup_theme', 'qingya_setup' );

/**
 * 内容宽度（无侧边栏/全宽模板用）。
 */
function qingya_content_width() {
	$GLOBALS['content_width'] = 1200;
}
add_action( 'after_setup_theme', 'qingya_content_width', 0 );

/**
 * 侧边栏注册。
 */
function qingya_widgets_init() {
	register_sidebar( array(
		'name'          => __( '主侧边栏', 'qingya' ),
		'id'            => 'sidebar-1',
		'description'   => __( '博客文章页右侧栏', 'qingya' ),
		'before_widget' => '<section id="%1$s" class="widget %2$s">',
		'after_widget'  => '</section>',
		'before_title'  => '<h3 class="widget-title">',
		'after_title'   => '</h3>',
	) );
	register_sidebar( array(
		'name'          => __( '页脚 1', 'qingya' ),
		'id'            => 'footer-1',
		'description'   => __( '页脚左侧小工具', 'qingya' ),
		'before_widget' => '<section id="%1$s" class="widget %2$s">',
		'after_widget'  => '</section>',
		'before_title'  => '<h3 class="widget-title">',
		'after_title'   => '</h3>',
	) );
	register_sidebar( array(
		'name'          => __( '页脚 2', 'qingya' ),
		'id'            => 'footer-2',
		'description'   => __( '页脚中部小工具', 'qingya' ),
		'before_widget' => '<section id="%1$s" class="widget %2$s">',
		'after_widget'  => '</section>',
		'before_title'  => '<h3 class="widget-title">',
		'after_title'   => '</h3>',
	) );
	register_sidebar( array(
		'name'          => __( '页脚 3', 'qingya' ),
		'id'            => 'footer-3',
		'description'   => __( '页脚右侧小工具', 'qingya' ),
		'before_widget' => '<section id="%1$s" class="widget %2$s">',
		'after_widget'  => '</section>',
		'before_title'  => '<h3 class="widget-title">',
		'after_title'   => '</h3>',
	) );
}
add_action( 'widgets_init', 'qingya_widgets_init' );

/**
 * 前端资源加载由 performance.php 统一负责（版本化 + CDN）。
 * 此文件仅保留动态 CSS 生成与布局辅助。
 */

/**
 * 生成动态 CSS：配色方案（预设或自定义）+ 字体/布局变量。
 * 输出两组：:root（浅色）与 [data-theme="dark"]（深色），
 * 深色组置于浅色组之后，确保深色模式优先级正确（修复深色不可见问题）。
 *
 * @return string
 */
function qingya_get_dynamic_css() {
	// 配色方案预设。
	$palettes = array(
		'qingjian' => array( // 竹青书卷（默认）
			'light' => array(
				'primary' => '#2f7d63', 'bg' => '#f2ede3', 'content' => '#ffffff',
				'sidebar' => '#f8f4ea', 'text' => '#33302b', 'header' => '#ffffff',
			),
			'dark'  => array(
				'primary' => '#4da683', 'bg' => '#1a1f1d', 'content' => '#1a1f1d',
				'sidebar' => '#232a27', 'text' => '#d9dedb', 'header' => '#1d2421',
			),
		),
		'ink'      => array( // 水墨素简
			'light' => array(
				'primary' => '#3a3a36', 'bg' => '#f6f6f3', 'content' => '#ffffff',
				'sidebar' => '#efefea', 'text' => '#1f1f1c', 'header' => '#ffffff',
			),
			'dark'  => array(
				'primary' => '#b9b9b2', 'bg' => '#161616', 'content' => '#161616',
				'sidebar' => '#202020', 'text' => '#e6e6e2', 'header' => '#1c1c1c',
			),
		),
		'gray'     => array( // 青灰现代
			'light' => array(
				'primary' => '#2f5d8a', 'bg' => '#eef1f4', 'content' => '#ffffff',
				'sidebar' => '#f2f5f8', 'text' => '#22303c', 'header' => '#ffffff',
			),
			'dark'  => array(
				'primary' => '#5b8db8', 'bg' => '#141a20', 'content' => '#141a20',
				'sidebar' => '#1d252d', 'text' => '#d7dee4', 'header' => '#181f26',
			),
		),
		'coffee'   => array( // 暖咖复古
			'light' => array(
				'primary' => '#8a5a3b', 'bg' => '#f5efe4', 'content' => '#fffdf8',
				'sidebar' => '#efe6d6', 'text' => '#3a322a', 'header' => '#fffdf8',
			),
			'dark'  => array(
				'primary' => '#c08a5f', 'bg' => '#1d1712', 'content' => '#1d1712',
				'sidebar' => '#262019', 'text' => '#e2d8ca', 'header' => '#211a14',
			),
		),
	);

	$palette = get_theme_mod( 'qy_palette', 'qingjian' );
	if ( 'custom' !== $palette && isset( $palettes[ $palette ] ) ) {
		$light = $palettes[ $palette ]['light'];
		$dark  = $palettes[ $palette ]['dark'];
	} else {
		// 自定义：单色设置 + 配套深色。
		$light = array(
			'primary' => get_theme_mod( 'qy_color_primary', '#2f7d63' ),
			'bg'      => get_theme_mod( 'qy_color_bg', '#f2ede3' ),
			'content' => get_theme_mod( 'qy_color_content', '#ffffff' ),
			'sidebar' => get_theme_mod( 'qy_color_sidebar', '#f8f4ea' ),
			'text'    => get_theme_mod( 'qy_color_text', '#33302b' ),
			'header'  => get_theme_mod( 'qy_color_header', '#ffffff' ),
		);
		$dark  = array(
			'primary' => '#4da683', 'bg' => '#1a1f1d', 'content' => '#1a1f1d',
			'sidebar' => '#232a27', 'text' => '#d9dedb', 'header' => '#1d2421',
		);
	}

	$font   = get_theme_mod( 'qy_font_family', 'sans' );
	$size   = get_theme_mod( 'qy_font_size', 16 );
	$logo_h = get_theme_mod( 'qy_basic_logo_height', 60 );

	// 字体栈（系统字体，无外部字体依赖）。
	switch ( $font ) {
		case 'serif':
			$stack = 'Georgia, "Songti SC", "SimSun", "Noto Serif CJK SC", serif';
			break;
		case 'mono':
			$stack = '"SF Mono", Consolas, "Courier New", monospace';
			break;
		default:
			$stack = '-apple-system, BlinkMacSystemFont, "Segoe UI", "PingFang SC", "Hiragino Sans GB", "Microsoft YaHei", "Noto Sans CJK SC", sans-serif';
	}

	$sanitize = function ( $color ) {
		return sanitize_hex_color( $color ) ?: '#33302b';
	};

	// 浅色组（含字体/字号/LOGO 高度）。
	$css  = ':root{';
	$css .= '--qy-primary:' . $sanitize( $light['primary'] ) . ';';
	$css .= '--qy-bg:' . $sanitize( $light['bg'] ) . ';';
	$css .= '--qy-content-bg:' . $sanitize( $light['content'] ) . ';';
	$css .= '--qy-sidebar-bg:' . $sanitize( $light['sidebar'] ) . ';';
	$css .= '--qy-text:' . $sanitize( $light['text'] ) . ';';
	$css .= '--qy-header-bg:' . $sanitize( $light['header'] ) . ';';
	$css .= '--qy-font-size:' . absint( $size ) . 'px;';
	$css .= '--qy-logo-height:' . absint( $logo_h ) . 'px;';
	$css .= '--qy-font-body:' . $stack . ';';
	$css .= '--qy-font-heading:' . $stack . ';';
	$css .= '}';

	// 深色组（置于浅色组后，保证优先级）。
	$css .= '[data-theme="dark"]{';
	$css .= '--qy-primary:' . $sanitize( $dark['primary'] ) . ';';
	$css .= '--qy-bg:' . $sanitize( $dark['bg'] ) . ';';
	$css .= '--qy-content-bg:' . $sanitize( $dark['content'] ) . ';';
	$css .= '--qy-sidebar-bg:' . $sanitize( $dark['sidebar'] ) . ';';
	$css .= '--qy-text:' . $sanitize( $dark['text'] ) . ';';
	$css .= '--qy-header-bg:' . $sanitize( $dark['header'] ) . ';';
	$css .= '}';

	return $css;
}

/**
 * 首页主查询改写：当首页为静态页面且使用「首页」模板时，
 * 将主查询改为文章列表（轮播 + 图文 + 列表布局）。
 *
 * @param WP_Query $query 查询对象。
 */
function qingya_front_page_query( $query ) {
	if ( is_admin() || ! $query->is_main_query() || ! $query->is_front_page() ) {
		return;
	}
	if ( 'page' !== get_option( 'show_on_front' ) ) {
		return;
	}
	$front_id = (int) get_option( 'page_on_front' );
	if ( ! $front_id || 'page-templates/front-page.php' !== get_page_template_slug( $front_id ) ) {
		return;
	}
	$query->set( 'post_type', 'post' );
	$query->set( 'paged', max( 1, (int) get_query_var( 'page' ) ) );
	$query->set( 'posts_per_page', (int) get_option( 'posts_per_page' ) );
}
add_action( 'pre_get_posts', 'qingya_front_page_query' );

/**
 * 正文区域类名（根据布局设置输出 col-1c / col-2cl / col-2cr）。
 *
 * @return string
 */
function qingya_layout_class() {
	$layout = get_theme_mod( 'qy_layout', 'right' );

	if ( is_singular() ) {
		$meta = get_post_meta( get_the_ID(), '_qingya_layout', true );
		if ( $meta && in_array( $meta, array( 'left', 'right', 'none', 'full' ), true ) ) {
			$layout = $meta;
		}
	}

	switch ( $layout ) {
		case 'left':
			return 'col-2cl';
		case 'none':
			return 'col-1c';
		case 'full':
			return 'col-full';
		default:
			return 'col-2cr';
	}
}

/**
 * 过滤残留的无效短代码文本（如 [chatbot style="floating"]）。
 * 未注册的短代码 WP 会原样输出成代码，这里直接从渲染队列移除。
 *
 * @param array $sidebars_widgets 侧边栏小工具分配。
 * @return array
 */
function qingya_filter_stray_shortcodes( $sidebars_widgets ) {
	if ( ! is_array( $sidebars_widgets ) ) {
		return $sidebars_widgets;
	}
	foreach ( $sidebars_widgets as $sidebar => $widgets ) {
		if ( ! is_array( $widgets ) ) {
			continue;
		}
		foreach ( $widgets as $i => $widget_id ) {
			// 仅检查区块小工具（widget_block）。
			if ( 0 !== strpos( (string) $widget_id, 'block-' ) ) {
				continue;
			}
			$blocks  = get_option( 'widget_block', array() );
			$num     = (int) substr( (string) $widget_id, 6 );
			$content = isset( $blocks[ $num ]['content'] ) ? (string) $blocks[ $num ]['content'] : '';
			if ( '' !== $content && false !== strpos( $content, '[chatbot' ) ) {
				unset( $sidebars_widgets[ $sidebar ][ $i ] );
			}
		}
		$sidebars_widgets[ $sidebar ] = array_values( $sidebars_widgets[ $sidebar ] );
	}
	return $sidebars_widgets;
}
add_filter( 'sidebars_widgets', 'qingya_filter_stray_shortcodes' );
