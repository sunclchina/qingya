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
 * 首页布局方案：返回当前生效的三件套（配色 / 布局 / 列表样式）。
 * 旧版布局值（classic/portal/magazine/minimal）自动映射到对应方案。
 *
 * @return array {palette:string, layout:string, list_style:string}
 */
function qingya_home_scheme() {
	$schemes = array(
		'qingjian-classic' => array( // 书卷经典
			'palette' => 'qingjian', 'layout' => 'classic', 'list_style' => 'list',
		),
		'ink-minimal'      => array( // 素简文章
			'palette' => 'ink', 'layout' => 'minimal', 'list_style' => 'list',
		),
		'gray-portal'      => array( // 现代门户
			'palette' => 'gray', 'layout' => 'portal', 'list_style' => 'card',
		),
		'coffee-magazine'  => array( // 复古杂志
			'palette' => 'coffee', 'layout' => 'magazine', 'list_style' => 'card',
		),
	);

	$scheme = get_theme_mod( 'qy_home_layout', 'qingjian-classic' );
	if ( ! isset( $schemes[ $scheme ] ) ) {
		// 旧版布局值迁移映射。
		$legacy = array(
			'classic'  => 'qingjian-classic',
			'minimal'  => 'ink-minimal',
			'portal'   => 'gray-portal',
			'magazine' => 'coffee-magazine',
		);
		$scheme = isset( $legacy[ $scheme ] ) ? $legacy[ $scheme ] : 'qingjian-classic';
	}
	return $schemes[ $scheme ];
}

/**
 * 生成动态 CSS：配色方案（由首页布局方案决定）+ 字体/布局变量。
 * 输出两组：:root（浅色）与 [data-theme="dark"]（深色），
 * 深色组置于浅色组之后，确保深色模式优先级正确（修复深色不可见问题）。
 *
 * @return string
 */
function qingya_get_dynamic_css() {
	// 配色方案预设：完整颜色变量集（与 main.css 的 13 个颜色变量一一对应），
	// 浅色 + 深色各一组，确保切换方案时全站（含页脚/边框/悬停色等）整体换肤。
	$palettes = array(
		'qingjian' => array( // 竹青书卷（默认）
			'light' => array(
				'primary' => '#2f7d63', 'primary-dark' => '#25624e', 'accent' => '#b03a2e',
				'bg' => '#f2ede3', 'card' => '#ffffff', 'content' => '#ffffff',
				'sidebar' => '#f8f4ea', 'text' => '#33302b', 'text-light' => '#6b6558',
				'border' => '#e0d8c8', 'header' => '#ffffff', 'footer' => '#2b332f', 'footer-text' => '#c9d2cd',
			),
			'dark'  => array(
				'primary' => '#4da683', 'primary-dark' => '#3d8a6c', 'accent' => '#d07a6e',
				'bg' => '#1a1f1d', 'card' => '#232a27', 'content' => '#1a1f1d',
				'sidebar' => '#232a27', 'text' => '#d9dedb', 'text-light' => '#9aa49f',
				'border' => '#333c38', 'header' => '#1d2421', 'footer' => '#141816', 'footer-text' => '#aeb8b3',
			),
		),
		'ink'      => array( // 水墨素简
			'light' => array(
				'primary' => '#3a3a36', 'primary-dark' => '#232320', 'accent' => '#b03a2e',
				'bg' => '#ecece8', 'card' => '#ffffff', 'content' => '#ffffff',
				'sidebar' => '#e5e5df', 'text' => '#1f1f1c', 'text-light' => '#75756d',
				'border' => '#d2d2c9', 'header' => '#ffffff', 'footer' => '#2a2a26', 'footer-text' => '#c9c9c1',
			),
			'dark'  => array(
				'primary' => '#b9b9b2', 'primary-dark' => '#d8d8d1', 'accent' => '#d07a6e',
				'bg' => '#161616', 'card' => '#202020', 'content' => '#161616',
				'sidebar' => '#202020', 'text' => '#e6e6e2', 'text-light' => '#9b9b94',
				'border' => '#33332e', 'header' => '#1c1c1c', 'footer' => '#0f0f0f', 'footer-text' => '#b8b8b0',
			),
		),
		'gray'     => array( // 青灰现代
			'light' => array(
				'primary' => '#2f5d8a', 'primary-dark' => '#24496e', 'accent' => '#b03a2e',
				'bg' => '#e2e8ef', 'card' => '#ffffff', 'content' => '#ffffff',
				'sidebar' => '#e8edf3', 'text' => '#22303c', 'text-light' => '#5d6b78',
				'border' => '#c8d2dc', 'header' => '#ffffff', 'footer' => '#1e2a36', 'footer-text' => '#c2cbd3',
			),
			'dark'  => array(
				'primary' => '#5b8db8', 'primary-dark' => '#7ea8ce', 'accent' => '#d07a6e',
				'bg' => '#141a20', 'card' => '#1d252d', 'content' => '#141a20',
				'sidebar' => '#1d252d', 'text' => '#d7dee4', 'text-light' => '#8fa0ae',
				'border' => '#2b3743', 'header' => '#181f26', 'footer' => '#0e141a', 'footer-text' => '#aeb9c3',
			),
		),
		'coffee'   => array( // 暖咖复古
			'light' => array(
				'primary' => '#8a5a3b', 'primary-dark' => '#6d452c', 'accent' => '#b03a2e',
				'bg' => '#f0e2cb', 'card' => '#fffdf8', 'content' => '#fffdf8',
				'sidebar' => '#e7d8bc', 'text' => '#3a322a', 'text-light' => '#88785f',
				'border' => '#dbc7a4', 'header' => '#fffdf8', 'footer' => '#3a2f24', 'footer-text' => '#d8c8b2',
			),
			'dark'  => array(
				'primary' => '#c08a5f', 'primary-dark' => '#d6a37c', 'accent' => '#d07a6e',
				'bg' => '#1d1712', 'card' => '#262019', 'content' => '#1d1712',
				'sidebar' => '#262019', 'text' => '#e2d8ca', 'text-light' => '#a8988a',
				'border' => '#3a2f24', 'header' => '#211a14', 'footer' => '#14100b', 'footer-text' => '#c2b199',
			),
		),
	);

	// 配色由首页布局方案决定（书卷经典/素简文章/现代门户/复古杂志）。
	$palette = qingya_home_scheme()['palette'];
	if ( isset( $palettes[ $palette ] ) ) {
		$light = $palettes[ $palette ]['light'];
		$dark  = $palettes[ $palette ]['dark'];
	} else {
		$light = $palettes['qingjian']['light'];
		$dark  = $palettes['qingjian']['dark'];
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

	// 变量名映射：内部键 → CSS 变量名（与 main.css 保持一致）。
	$map = array(
		'primary' => '--qy-primary', 'primary-dark' => '--qy-primary-dark', 'accent' => '--qy-accent',
		'bg' => '--qy-bg', 'card' => '--qy-card', 'content' => '--qy-content-bg',
		'sidebar' => '--qy-sidebar-bg', 'text' => '--qy-text', 'text-light' => '--qy-text-light',
		'border' => '--qy-border', 'header' => '--qy-header-bg', 'footer' => '--qy-footer-bg',
		'footer-text' => '--qy-footer-text',
	);

	// 合法 hex 或 CSS 表达式（var()/color-mix()）放行，其余回退默认文字色。
	$sanitize = function ( $value ) {
		if ( is_string( $value ) && ( 0 === strpos( $value, 'var(' ) || 0 === strpos( $value, 'color-mix(' ) ) ) {
			return $value;
		}
		return sanitize_hex_color( $value ) ?: '#33302b';
	};

	// 浅色组（含字体/字号/LOGO 高度）。
	$css = ':root{';
	foreach ( $map as $key => $var ) {
		if ( isset( $light[ $key ] ) ) {
			$css .= $var . ':' . $sanitize( $light[ $key ] ) . ';';
		}
	}
	$css .= '--qy-font-size:' . absint( $size ) . 'px;';
	$css .= '--qy-logo-height:' . absint( $logo_h ) . 'px;';
	$css .= '--qy-font-body:' . $stack . ';';
	$css .= '--qy-font-heading:' . $stack . ';';
	$css .= '}';

	// 深色组（置于浅色组后，保证优先级）。
	$css .= '[data-theme="dark"]{';
	foreach ( $map as $key => $var ) {
		if ( isset( $dark[ $key ] ) ) {
			$css .= $var . ':' . $sanitize( $dark[ $key ] ) . ';';
		}
	}
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

/**
 * 兼容星河AI工具箱：其摘要存于 _xhai_excerpt postmeta，而非标准 post_excerpt。
 *
 * 优先级：标准摘要（A-Blog 发布/手工填写）> 星河摘要；均无则不显示（翁老规则：
 * 不拿正文自动截取充数）。
 * 星河摘要在 350 字上下，统一规范为 110 字符（与 A-Blog 摘要风格一致），
 * 避免列表页排版被长摘要撑破。
 *
 * @param string $excerpt 当前摘要。
 * @param WP_Post|object $post  文章对象。
 * @return string
 */
function qingya_excerpt_compat( $excerpt, $post ) {
	if ( ! is_object( $post ) || empty( $post->ID ) ) {
		return $excerpt;
	}
	// 标准 post_excerpt 非空 → 优先（A-Blog 新文/手工摘要）。
	if ( isset( $post->post_excerpt ) && '' !== trim( (string) $post->post_excerpt ) ) {
		return $excerpt;
	}
	// 星河 AI 工具箱摘要兜底。
	$xhai = get_post_meta( (int) $post->ID, '_xhai_excerpt', true );
	if ( is_string( $xhai ) ) {
		$xhai = trim( preg_replace( '/\s+/u', ' ', $xhai ) );
	}
	if ( '' === $xhai ) {
		// 翁老规则：没有 AI/手工/星河摘要就不显示摘要，不做正文自动截取充数。
		return '';
	}
	if ( function_exists( 'mb_strlen' ) && mb_strlen( $xhai, 'UTF-8' ) > 110 ) {
		return mb_substr( $xhai, 0, 110, 'UTF-8' ) . '…';
	}
	return $xhai;
}
add_filter( 'get_the_excerpt', 'qingya_excerpt_compat', 10, 2 );
