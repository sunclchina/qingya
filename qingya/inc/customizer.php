<?php
/**
 * 后台可视化配置（Customizer）：
 * 基础设置 / 配色 / 首页 / 布局 / 字体 / 性能 / SEO / 安全
 * 全部实时预览、即时生效。
 *
 * @package Qingya
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * 注册 Customizer 面板与设置。
 *
 * @param WP_Customize_Manager $wp_customize 定制器实例。
 */
function qingya_customize_register( $wp_customize ) {

	// 面板分组。
	$wp_customize->add_panel( 'qingya_panel', array(
		'title'       => __( '青崖主题设置', 'qingya' ),
		'description' => __( '主题可视化配置，修改实时预览。', 'qingya' ),
		'priority'    => 10,
	) );

	/* ========== 1. 基础设置 ========== */
	$wp_customize->add_section( 'qingya_section_basic', array(
		'title' => __( '基础设置', 'qingya' ),
		'panel' => 'qingya_panel',
	) );

	// 站点 LOGO（原生 custom-logo 已支持，此处补充尺寸提示）。
	$wp_customize->add_setting( 'qy_basic_logo_height', array(
		'default'           => 60,
		'sanitize_callback' => 'absint',
	) );
	$wp_customize->add_control( 'qy_basic_logo_height', array(
		'label'       => __( 'LOGO 显示高度（px）', 'qingya' ),
		'section'     => 'qingya_section_basic',
		'type'        => 'number',
		'input_attrs' => array( 'min' => 30, 'max' => 200 ),
	) );

	// 顶部公告。
	$wp_customize->add_setting( 'qy_basic_notice', array(
		'default'           => '',
		'sanitize_callback' => 'sanitize_text_field',
	) );
	$wp_customize->add_control( 'qy_basic_notice', array(
		'label'       => __( '顶部公告文字', 'qingya' ),
		'description' => __( '留空则不显示公告栏。', 'qingya' ),
		'section'     => 'qingya_section_basic',
		'type'        => 'text',
	) );

	// 联系方式（电话）。
	$wp_customize->add_setting( 'qy_basic_phone', array(
		'default'           => '',
		'sanitize_callback' => 'sanitize_text_field',
	) );
	$wp_customize->add_control( 'qy_basic_phone', array(
		'label'   => __( '联系电话', 'qingya' ),
		'section' => 'qingya_section_basic',
		'type'    => 'text',
	) );

	// 联系方式（邮箱）。
	$wp_customize->add_setting( 'qy_basic_email', array(
		'default'           => '',
		'sanitize_callback' => 'sanitize_email',
	) );
	$wp_customize->add_control( 'qy_basic_email', array(
		'label'   => __( '联系邮箱', 'qingya' ),
		'section' => 'qingya_section_basic',
		'type'    => 'email',
	) );

	// 底部版权。
	$wp_customize->add_setting( 'qy_basic_copyright', array(
		'default'           => '',
		'sanitize_callback' => 'wp_kses_post',
	) );
	$wp_customize->add_control( 'qy_basic_copyright', array(
		'label'       => __( '底部版权文字', 'qingya' ),
		'description' => __( '支持 HTML，如 © 2026 某某工作室。留空则显示默认版权。', 'qingya' ),
		'section'     => 'qingya_section_basic',
		'type'        => 'textarea',
	) );

	// 备案号。
	$wp_customize->add_setting( 'qy_basic_icp', array(
		'default'           => '',
		'sanitize_callback' => 'sanitize_text_field',
	) );
	$wp_customize->add_control( 'qy_basic_icp', array(
		'label'       => __( 'ICP 备案号', 'qingya' ),
		'description' => __( '如：京ICP备00000000号，将链接至工信部备案查询。', 'qingya' ),
		'section'     => 'qingya_section_basic',
		'type'        => 'text',
	) );

	// 页脚友情链接。
	$wp_customize->add_setting( 'qy_basic_links', array(
		'default'           => '',
		'sanitize_callback' => 'sanitize_textarea_field',
	) );
	$wp_customize->add_control( 'qy_basic_links', array(
		'label'       => __( '页脚友情链接', 'qingya' ),
		'description' => __( '每行一条，格式：名称|https://网址', 'qingya' ),
		'section'     => 'qingya_section_basic',
		'type'        => 'textarea',
	) );

	/* ========== 2. 配色 ========== */
	$wp_customize->add_section( 'qingya_section_color', array(
		'title' => __( '配色', 'qingya' ),
		'panel' => 'qingya_panel',
	) );

	// 说明：四套配色已并入「首页布局」方案（书卷经典/素简文章/现代门户/复古杂志）。
	$wp_customize->add_setting( 'qy_color_note', array(
		'default'           => '',
		'sanitize_callback' => 'sanitize_text_field',
	) );
	$wp_customize->add_control( 'qy_color_note', array(
		'label'       => __( '配色方案', 'qingya' ),
		'description' => __( '四套配色已并入「首页 → 首页布局」方案：书卷经典（竹青书卷）/ 素简文章（水墨素简）/ 现代门户（青灰现代）/ 复古杂志（暖咖复古）。切换首页布局即整套换肤。', 'qingya' ),
		'section'     => 'qingya_section_color',
		'type'        => 'hidden',
	) );

	// 深色模式开关。
	$wp_customize->add_setting( 'qy_color_darkmode', array(
		'default'           => 'on',
		'sanitize_callback' => 'qingya_sanitize_onoff',
	) );
	$wp_customize->add_control( 'qy_color_darkmode', array(
		'label'   => __( '启用深色模式切换按钮', 'qingya' ),
		'section' => 'qingya_section_color',
		'type'    => 'checkbox',
	) );

	/* ========== 3. 首页 ========== */
	$wp_customize->add_section( 'qingya_section_front', array(
		'title' => __( '首页', 'qingya' ),
		'panel' => 'qingya_panel',
	) );

	// 首页布局：四套整站方案（配色 + 板块布局 + 列表样式一键切换）。
	$wp_customize->add_setting( 'qy_home_layout', array(
		'default'           => 'qingjian-classic',
		'sanitize_callback' => 'qingya_home_layout_sanitize',
	) );
	$wp_customize->add_control( 'qy_home_layout', array(
		'label'       => __( '首页布局', 'qingya' ),
		'description' => __( '一套方案 = 配色 + 板块布局 + 列表样式，切换即整套换肤。', 'qingya' ),
		'section'     => 'qingya_section_front',
		'type'        => 'select',
		'choices'     => array(
			'qingjian-classic' => __( '书卷经典', 'qingya' ),
			'ink-minimal'      => __( '素简文章', 'qingya' ),
			'gray-portal'      => __( '现代门户', 'qingya' ),
			'coffee-magazine'  => __( '复古杂志', 'qingya' ),
		),
	) );

	// 轮播开关。
	$wp_customize->add_setting( 'qy_front_carousel', array(
		'default'           => 'on',
		'sanitize_callback' => 'qingya_sanitize_onoff',
	) );
	$wp_customize->add_control( 'qy_front_carousel', array(
		'label'   => __( '启用首页轮播图', 'qingya' ),
		'section' => 'qingya_section_front',
		'type'    => 'checkbox',
	) );

	// 轮播项：固定 4 个位置（轻量、无第三方库）。
	for ( $i = 1; $i <= 4; $i++ ) {
		$wp_customize->add_setting( 'qy_front_slide_' . $i . '_image', array(
			'default'           => '',
			'sanitize_callback' => 'esc_url_raw',
		) );
		$wp_customize->add_control( new WP_Customize_Image_Control( $wp_customize, 'qy_front_slide_' . $i . '_image', array(
			'label'       => sprintf( __( '轮播图 %d', 'qingya' ), $i ),
			'description' => __( '建议 1600×600', 'qingya' ),
			'section'     => 'qingya_section_front',
		) ) );

		$wp_customize->add_setting( 'qy_front_slide_' . $i . '_title', array(
			'default'           => '',
			'sanitize_callback' => 'sanitize_text_field',
		) );
		$wp_customize->add_control( 'qy_front_slide_' . $i . '_title', array(
			'label'   => sprintf( __( '轮播 %d 标题', 'qingya' ), $i ),
			'section' => 'qingya_section_front',
			'type'    => 'text',
		) );

		$wp_customize->add_setting( 'qy_front_slide_' . $i . '_desc', array(
			'default'           => '',
			'sanitize_callback' => 'sanitize_text_field',
		) );
		$wp_customize->add_control( 'qy_front_slide_' . $i . '_desc', array(
			'label'   => sprintf( __( '轮播 %d 简介', 'qingya' ), $i ),
			'section' => 'qingya_section_front',
			'type'    => 'text',
		) );

		$wp_customize->add_setting( 'qy_front_slide_' . $i . '_link', array(
			'default'           => '',
			'sanitize_callback' => 'esc_url_raw',
		) );
		$wp_customize->add_control( 'qy_front_slide_' . $i . '_link', array(
			'label'   => sprintf( __( '轮播 %d 链接', 'qingya' ), $i ),
			'section' => 'qingya_section_front',
			'type'    => 'url',
		) );
	}

	// 首页专题模块标题。
	$wp_customize->add_setting( 'qy_front_section_title', array(
		'default'           => __( '最新文章', 'qingya' ),
		'sanitize_callback' => 'sanitize_text_field',
	) );
	$wp_customize->add_control( 'qy_front_section_title', array(
		'label'   => __( '首页内容区标题', 'qingya' ),
		'section' => 'qingya_section_front',
		'type'    => 'text',
	) );

	// 首页分区配置（门户/杂志/极简布局）。
	$wp_customize->add_setting( 'qy_home_cat_count', array(
		'default'           => 6,
		'sanitize_callback' => 'absint',
	) );
	$wp_customize->add_control( 'qy_home_cat_count', array(
		'label'       => __( '分类直达数量', 'qingya' ),
		'description' => __( '按文章数取最多的前 N 个分类（门户/极简布局）。', 'qingya' ),
		'section'     => 'qingya_section_front',
		'type'        => 'number',
		'input_attrs' => array( 'min' => 3, 'max' => 12 ),
	) );

	$wp_customize->add_setting( 'qy_home_hot_count', array(
		'default'           => 5,
		'sanitize_callback' => 'absint',
	) );
	$wp_customize->add_control( 'qy_home_hot_count', array(
		'label'       => __( '热门高赞数量', 'qingya' ),
		'description' => __( '按点赞数排序（门户布局）。', 'qingya' ),
		'section'     => 'qingya_section_front',
		'type'        => 'number',
		'input_attrs' => array( 'min' => 3, 'max' => 12 ),
	) );

	$wp_customize->add_setting( 'qy_home_project_cats', array(
		'default'           => '推荐,IT',
		'sanitize_callback' => 'sanitize_text_field',
	) );
	$wp_customize->add_control( 'qy_home_project_cats', array(
		'label'       => __( '开源项目区分类', 'qingya' ),
		'description' => __( '用逗号分隔，如：推荐,IT', 'qingya' ),
		'section'     => 'qingya_section_front',
		'type'        => 'text',
	) );

	$wp_customize->add_setting( 'qy_home_stock_cats', array(
		'default'           => '股票,行业',
		'sanitize_callback' => 'sanitize_text_field',
	) );
	$wp_customize->add_control( 'qy_home_stock_cats', array(
		'label'       => __( '股市区本站分类', 'qingya' ),
		'description' => __( '用逗号分隔，如：股票,行业', 'qingya' ),
		'section'     => 'qingya_section_front',
		'type'        => 'text',
	) );

	$wp_customize->add_setting( 'qy_home_stock_on', array(
		'default'           => 'on',
		'sanitize_callback' => 'qingya_sanitize_onoff',
	) );
	$wp_customize->add_control( 'qy_home_stock_on', array(
		'label'   => __( '显示股市消息区', 'qingya' ),
		'section' => 'qingya_section_front',
		'type'    => 'checkbox',
	) );

	$wp_customize->add_setting( 'qy_stock_cache_min', array(
		'default'           => 30,
		'sanitize_callback' => 'absint',
	) );
	$wp_customize->add_control( 'qy_stock_cache_min', array(
		'label'       => __( '股市数据缓存（分钟）', 'qingya' ),
		'description' => __( '外部接口抓取间隔，最低 5 分钟，避免频繁请求被封。', 'qingya' ),
		'section'     => 'qingya_section_front',
		'type'        => 'number',
		'input_attrs' => array( 'min' => 5, 'max' => 240 ),
	) );

	/* ========== 4. 布局 ========== */
	$wp_customize->add_section( 'qingya_section_layout', array(
		'title' => __( '布局', 'qingya' ),
		'panel' => 'qingya_panel',
	) );

	$wp_customize->add_setting( 'qy_layout', array(
		'default'           => 'right',
		'sanitize_callback' => 'qingya_sanitize_layout',
	) );
	$wp_customize->add_control( 'qy_layout', array(
		'label'   => __( '全局侧边栏位置', 'qingya' ),
		'section' => 'qingya_section_layout',
		'type'    => 'select',
		'choices' => array(
			'right' => __( '右侧边栏', 'qingya' ),
			'left'  => __( '左侧边栏', 'qingya' ),
			'none'  => __( '无侧边栏（单栏）', 'qingya' ),
			'full'  => __( '全宽（无侧边栏）', 'qingya' ),
		),
	) );

	// 容器宽度。
	$wp_customize->add_setting( 'qy_layout_width', array(
		'default'           => 1200,
		'sanitize_callback' => 'absint',
	) );
	$wp_customize->add_control( 'qy_layout_width', array(
		'label'       => __( '页面最大宽度（px）', 'qingya' ),
		'section'     => 'qingya_section_layout',
		'type'        => 'number',
		'input_attrs' => array( 'min' => 960, 'max' => 1600, 'step' => 10 ),
	) );

	// 首页模块显示。
	$wp_customize->add_setting( 'qy_layout_show_featured', array(
		'default'           => 'on',
		'sanitize_callback' => 'qingya_sanitize_onoff',
	) );
	$wp_customize->add_control( 'qy_layout_show_featured', array(
		'label'   => __( '首页显示置顶/推荐区', 'qingya' ),
		'section' => 'qingya_section_layout',
		'type'    => 'checkbox',
	) );

	/* ========== 5. 字体 ========== */
	$wp_customize->add_section( 'qingya_section_font', array(
		'title' => __( '字体', 'qingya' ),
		'panel' => 'qingya_panel',
	) );

	$wp_customize->add_setting( 'qy_font_family', array(
		'default'           => 'sans',
		'sanitize_callback' => 'qingya_sanitize_font',
	) );
	$wp_customize->add_control( 'qy_font_family', array(
		'label'   => __( '全局字体', 'qingya' ),
		'section' => 'qingya_section_font',
		'type'    => 'select',
		'choices' => array(
			'sans'  => __( '无衬线（黑体，默认）', 'qingya' ),
			'serif' => __( '衬线（宋体，书卷风）', 'qingya' ),
			'mono'  => __( '等宽', 'qingya' ),
		),
	) );

	$wp_customize->add_setting( 'qy_font_size', array(
		'default'           => 16,
		'sanitize_callback' => 'absint',
	) );
	$wp_customize->add_control( 'qy_font_size', array(
		'label'       => __( '正文字号（px）', 'qingya' ),
		'section'     => 'qingya_section_font',
		'type'        => 'number',
		'input_attrs' => array( 'min' => 14, 'max' => 20 ),
	) );

	/* ========== 6. 性能 ========== */
	$wp_customize->add_section( 'qingya_section_perf', array(
		'title' => __( '性能', 'qingya' ),
		'panel' => 'qingya_panel',
	) );

	$wp_customize->add_setting( 'qy_perf_cdn', array(
		'default'           => '',
		'sanitize_callback' => 'esc_url_raw',
	) );
	$wp_customize->add_control( 'qy_perf_cdn', array(
		'label'       => __( '静态资源 CDN 域名', 'qingya' ),
		'description' => __( '如 https://cdn.example.com，主题 CSS/JS/图片将走该域名。留空不使用。', 'qingya' ),
		'section'     => 'qingya_section_perf',
		'type'        => 'url',
	) );

	$wp_customize->add_setting( 'qy_perf_lazyload', array(
		'default'           => 'on',
		'sanitize_callback' => 'qingya_sanitize_onoff',
	) );
	$wp_customize->add_control( 'qy_perf_lazyload', array(
		'label'   => __( '图片懒加载', 'qingya' ),
		'section' => 'qingya_section_perf',
		'type'    => 'checkbox',
	) );

	$wp_customize->add_setting( 'qy_perf_trim', array(
		'default'           => 'off',
		'sanitize_callback' => 'qingya_sanitize_onoff',
	) );
	$wp_customize->add_control( 'qy_perf_trim', array(
		'label'       => __( '精简模式（移除 emoji/embed 脚本）', 'qingya' ),
		'description' => __( '可显著减少页面体积；若个别插件依赖 emoji 功能请保持关闭。', 'qingya' ),
		'section'     => 'qingya_section_perf',
		'type'        => 'checkbox',
	) );

	$wp_customize->add_setting( 'qy_perf_nomigrate', array(
		'default'           => 'off',
		'sanitize_callback' => 'qingya_sanitize_onoff',
	) );
	$wp_customize->add_control( 'qy_perf_nomigrate', array(
		'label'       => __( '禁用 jQuery Migrate（前端）', 'qingya' ),
		'description' => __( '主题本身不依赖旧 jQuery API，若其他插件需要请保持关闭。', 'qingya' ),
		'section'     => 'qingya_section_perf',
		'type'        => 'checkbox',
	) );

	/* ========== 7. SEO ========== */
	$wp_customize->add_section( 'qingya_section_seo', array(
		'title' => __( 'SEO', 'qingya' ),
		'panel' => 'qingya_panel',
	) );

	$wp_customize->add_setting( 'qy_seo_home_title', array(
		'default'           => '',
		'sanitize_callback' => 'sanitize_text_field',
	) );
	$wp_customize->add_control( 'qy_seo_home_title', array(
		'label'       => __( '首页 SEO 标题', 'qingya' ),
		'description' => __( '留空则使用「站点名 - 副标题」。', 'qingya' ),
		'section'     => 'qingya_section_seo',
		'type'        => 'text',
	) );

	$wp_customize->add_setting( 'qy_seo_home_desc', array(
		'default'           => '',
		'sanitize_callback' => 'sanitize_textarea_field',
	) );
	$wp_customize->add_control( 'qy_seo_home_desc', array(
		'label'   => __( '首页 meta 描述', 'qingya' ),
		'section' => 'qingya_section_seo',
		'type'    => 'textarea',
	) );

	$wp_customize->add_setting( 'qy_seo_keywords', array(
		'default'           => '',
		'sanitize_callback' => 'sanitize_text_field',
	) );
	$wp_customize->add_control( 'qy_seo_keywords', array(
		'label'       => __( '站点关键词（逗号分隔）', 'qingya' ),
		'section'     => 'qingya_section_seo',
		'type'        => 'text',
	) );

	// 统计代码。
	$wp_customize->add_setting( 'qy_seo_tracking', array(
		'default'           => '',
		'sanitize_callback' => 'wp_kses_post',
	) );
	$wp_customize->add_control( 'qy_seo_tracking', array(
		'label'       => __( '统计/验证代码', 'qingya' ),
		'description' => __( '百度统计、Google Analytics 等脚本，将输出到页脚。', 'qingya' ),
		'section'     => 'qingya_section_seo',
		'type'        => 'textarea',
	) );

	/* ========== 8. 安全 ========== */
	$wp_customize->add_section( 'qingya_section_sec', array(
		'title' => __( '安全', 'qingya' ),
		'panel' => 'qingya_panel',
	) );

	$wp_customize->add_setting( 'qy_sec_ua_block', array(
		'default'           => 'on',
		'sanitize_callback' => 'qingya_sanitize_onoff',
	) );
	$wp_customize->add_control( 'qy_sec_ua_block', array(
		'label'       => __( '屏蔽恶意扫描器 UA', 'qingya' ),
		'description' => __( '自动 403 常见扫描器（sqlmap、nikto、wpscan 等）。', 'qingya' ),
		'section'     => 'qingya_section_sec',
		'type'        => 'checkbox',
	) );

	$wp_customize->add_setting( 'qy_sec_block_empty_ua', array(
		'default'           => 'off',
		'sanitize_callback' => 'qingya_sanitize_onoff',
	) );
	$wp_customize->add_control( 'qy_sec_block_empty_ua', array(
		'label'       => __( '拦截无 UA 的请求', 'qingya' ),
		'description' => __( '可能误伤部分 API 客户端，默认关闭。', 'qingya' ),
		'section'     => 'qingya_section_sec',
		'type'        => 'checkbox',
	) );

	$wp_customize->add_setting( 'qy_sec_login_protect', array(
		'default'           => 'off',
		'sanitize_callback' => 'qingya_sanitize_onoff',
	) );
	$wp_customize->add_control( 'qy_sec_login_protect', array(
		'label'       => __( '登录失败锁定并自动拉黑（5 分钟 3 次）', 'qingya' ),
		'description' => __( '同一 IP 登录失败 3 次：锁定 5 分钟 + 自动加入 IP 黑名单（可在 IP 黑名单页查看/删除）。白名单 IP 豁免；管理员登录成功后自动解除自己的拉黑。', 'qingya' ),
		'section'     => 'qingya_section_sec',
		'type'        => 'checkbox',
	) );
}
add_action( 'customize_register', 'qingya_customize_register' );

/**
 * 首页布局 sanitize（四套整站方案，兼容旧版 classic/portal/magazine/minimal 值）。
 *
 * @param mixed $value 值。
 * @return string
 */
function qingya_home_layout_sanitize( $value ) {
	$new = array( 'qingjian-classic', 'ink-minimal', 'gray-portal', 'coffee-magazine' );
	if ( in_array( $value, $new, true ) ) {
		return $value;
	}
	// 旧版布局值迁移到对应方案。
	$legacy = array(
		'classic'  => 'qingjian-classic',
		'minimal'  => 'ink-minimal',
		'portal'   => 'gray-portal',
		'magazine' => 'coffee-magazine',
	);
	return isset( $legacy[ $value ] ) ? $legacy[ $value ] : 'qingjian-classic';
}

/**
 * 旧版首页布局值一次性迁移（customize_register 之前执行，保证下拉框显示正确）。
 */
function qingya_migrate_home_layout() {
	$value = get_theme_mod( 'qy_home_layout', '' );
	if ( in_array( $value, array( 'classic', 'portal', 'magazine', 'minimal' ), true ) ) {
		set_theme_mod( 'qy_home_layout', qingya_home_layout_sanitize( $value ) );
	}
}
add_action( 'after_setup_theme', 'qingya_migrate_home_layout' );

/**
 * 复选框 sanitize。
 * Customizer 的 checkbox 控件勾选时提交布尔值 true（JS：prop('checked')），
 * 未勾选提交 false；同时兼容字符串 'on'/'off'、'1'/'' 等历史值。
 *
 * @param mixed $value 值。
 * @return string
 */
function qingya_sanitize_onoff( $value ) {
	if ( true === $value || 'on' === $value || '1' === $value || 1 === $value ) {
		return 'on';
	}
	return 'off';
}

/**
 * 布局 sanitize。
 *
 * @param mixed $value 值。
 * @return string
 */
function qingya_sanitize_layout( $value ) {
	return in_array( $value, array( 'right', 'left', 'none', 'full' ), true ) ? $value : 'right';
}

/**
 * 字体 sanitize。
 *
 * @param mixed $value 值。
 * @return string
 */
function qingya_sanitize_font( $value ) {
	return in_array( $value, array( 'sans', 'serif', 'mono' ), true ) ? $value : 'sans';
}
