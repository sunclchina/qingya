<?php
/**
 * 全站境外 IP 拦截（可选）
 *
 * 基于 MaxMind GeoLite2-Country 数据库，拦截非中国大陆（CN）IP 的前台访问。
 * 特点：
 * - 无需第三方插件，复用 AI 客服模块内置的 MaxMind Reader 库
 * - Customizer 可开关，可配置白名单 IP（管理员/办公 IP 豁免）
 * - 登录管理员无条件放行（防误锁）
 * - 数据库缺失时自动放行（不误伤），后台有提示
 * - 默认仅拦前台，后台/登录页不受影响（除非开启"全部拦截"）
 *
 * @package Qingya
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * 注册 Customizer 设置（安全面板）。
 *
 * @param WP_Customize_Manager $wp_customize 定制器实例。
 */
function qingya_geo_customize_register( $wp_customize ) {

	$wp_customize->add_setting( 'qy_geo_enabled', array(
		'default'           => 'off',
		'sanitize_callback' => 'qingya_sanitize_onoff',
	) );
	$wp_customize->add_control( 'qy_geo_enabled', array(
		'label'       => __( '拦截境外 IP 访问前台', 'qingya' ),
		'description' => __( '基于 MaxMind GeoLite2 数据库，仅允许中国大陆 IP 访问。需将 GeoLite2-Country.mmdb 上传到 wp-content/uploads/（见 readme）。', 'qingya' ),
		'section'     => 'qingya_section_sec',
		'type'        => 'checkbox',
	) );

	$wp_customize->add_setting( 'qy_geo_scope', array(
		'default'           => 'admin',
		'sanitize_callback' => 'qingya_geo_sanitize_scope',
	) );
	$wp_customize->add_control( 'qy_geo_scope', array(
		'label'       => __( '拦截范围', 'qingya' ),
		'description' => __( '推荐「仅后台」：拦外国 IP 访问 wp-admin 与登录页，前台正常放行不误伤读者。选「全部」前务必先把管理员常用 IP 填入白名单，否则自己也可能被拒之门外（登录页豁免不生效）。', 'qingya' ),
		'section'     => 'qingya_section_sec',
		'type'        => 'select',
		'choices'     => array(
			'admin' => __( '仅后台/登录页（推荐，前台放行）', 'qingya' ),
			'front' => __( '仅前台（后台不受影响）', 'qingya' ),
			'all'   => __( '全部（含前台与后台）', 'qingya' ),
		),
	) );

	$wp_customize->add_setting( 'qy_geo_allow_hmt', array(
		'default'           => 'off',
		'sanitize_callback' => 'qingya_sanitize_onoff',
	) );
	$wp_customize->add_control( 'qy_geo_allow_hmt', array(
		'label'       => __( '放行港澳台 IP', 'qingya' ),
		'description' => __( '默认关闭：港澳台 IP 与国外 IP 一样被拦截（仅允许中国大陆 IP 访问 wp-admin）。如需放行请勾选。', 'qingya' ),
		'section'     => 'qingya_section_sec',
		'type'        => 'checkbox',
	) );

	$wp_customize->add_setting( 'qy_geo_whitelist', array(
		'default'           => '',
		'sanitize_callback' => 'sanitize_textarea_field',
	) );
	$wp_customize->add_control( 'qy_geo_whitelist', array(
		'label'       => __( '豁免白名单 IP（每行一个）', 'qingya' ) . '（' . __( '格式同 IP 黑名单：IP/段/CIDR', 'qingya' ) . '）',
		'description' => __( '这些 IP 即使境外也放行。登录管理员自动豁免，无需填写。', 'qingya' ),
		'section'     => 'qingya_section_sec',
		'type'        => 'textarea',
	) );

	$wp_customize->add_setting( 'qy_geo_redirect', array(
		'default'           => '',
		'sanitize_callback' => 'esc_url_raw',
	) );
	$wp_customize->add_control( 'qy_geo_redirect', array(
		'label'       => __( '拦截后跳转 URL（留空显示 403）', 'qingya' ),
		'section'     => 'qingya_section_sec',
		'type'        => 'url',
	) );
}
add_action( 'customize_register', 'qingya_geo_customize_register', 25 );

/**
 * 拦截范围 sanitize。
 *
 * @param mixed $value 值。
 * @return string
 */
function qingya_geo_sanitize_scope( $value ) {
	return in_array( $value, array( 'front', 'admin', 'all' ), true ) ? $value : 'admin';
}

/**
 * 读取境外拦截配置。
 *
 * @return array
 */
function qingya_geo_get_settings() {
	return array(
		'enabled'     => get_theme_mod( 'qy_geo_enabled', 'off' ),
		'scope'       => get_theme_mod( 'qy_geo_scope', 'admin' ), // admin=仅后台 front=仅前台 all=全部
		'whitelist'   => get_theme_mod( 'qy_geo_whitelist', '' ),  // 每行一个 IP/段
		'allow_hmt'   => get_theme_mod( 'qy_geo_allow_hmt', 'off' ),// 放行港澳台（默认关：港澳台也拦截）
		'redirect'    => get_theme_mod( 'qy_geo_redirect', '' ),   // 跳转 URL（留空=403）
	);
}

/**
 * 检查 IP 是否在豁免白名单。
 *
 * @param string $ip IP。
 * @return bool
 */
function qingya_geo_whitelisted( $ip ) {
	$raw = qingya_geo_get_settings()['whitelist'];
	if ( ! trim( (string) $raw ) ) {
		return false;
	}
	foreach ( preg_split( '/[\r\n,]+/', $raw ) as $rule ) {
		$rule = trim( $rule );
		if ( ! $rule ) {
			continue;
		}
		// 完整 IP。
		if ( $rule === $ip ) {
			return true;
		}
		// 段：192.168.1.*
		if ( false !== strpos( $rule, '*' ) ) {
			$pattern = str_replace( '.', '\.', str_replace( '*', '\d{1,3}', $rule ) );
			if ( preg_match( '/^' . $pattern . '$/', $ip ) ) {
				return true;
			}
		}
		// CIDR：123.45.67.0/24
		if ( false !== strpos( $rule, '/' ) ) {
			list( $net, $prefix ) = explode( '/', $rule );
			$ip_long  = ip2long( $ip );
			$net_long = ip2long( $net );
			$mask     = (int) $prefix > 0 ? ( 0xFFFFFFFF << ( 32 - (int) $prefix ) ) & 0xFFFFFFFF : 0;
			if ( false !== $ip_long && false !== $net_long && ( $ip_long & $mask ) === ( $net_long & $mask ) ) {
				return true;
			}
		}
	}
	return false;
}

/**
 * 执行境外 IP 拦截（挂 init 早期钩子，可覆盖 wp-admin 与 wp-login.php）。
 */
function qingya_geo_maybe_block() {
	$s = qingya_geo_get_settings();

	// 总开关。
	if ( 'on' !== $s['enabled'] ) {
		return;
	}

	// 后台上下文判断。
	$php_self = isset( $_SERVER['PHP_SELF'] ) ? wp_unslash( $_SERVER['PHP_SELF'] ) : '';
	$is_login = ( false !== strpos( $php_self, 'wp-login.php' ) );
	$is_admin = is_admin();

	// 范围模式：
	// front = 仅前台（后台/登录页不受影响）
	// admin = 仅后台/登录页（前台放行）—— 保护后台防攻击，不误伤海外读者
	// all   = 全部
	if ( 'front' === $s['scope'] && ( $is_admin || $is_login ) ) {
		return;
	}
	if ( 'admin' === $s['scope'] && ! $is_admin && ! $is_login ) {
		return;
	}

	// 已登录管理员无条件豁免（注意：登录页 wp-login.php 时尚未登录，豁免不生效，
	// 因此「全部」模式下务必先把管理员常用 IP 加入白名单，避免误锁）。
	if ( is_user_logged_in() && current_user_can( 'manage_options' ) ) {
		return;
	}

	$ip = qingya_client_ip();

	// 白名单豁免。
	if ( qingya_geo_whitelisted( $ip ) ) {
		return;
	}

	// 查询国家。
	$country = qingya_ai_geo_country( $ip );
	if ( false === $country ) {
		return; // 无法判断（无数据库）→ 放行，避免误伤。
	}

	// 放行中国。
	if ( 'CN' === $country ) {
		return;
	}
	// 可选放行港澳台。
	if ( 'on' === $s['allow_hmt'] && in_array( $country, array( 'HK', 'MO', 'TW' ), true ) ) {
		return;
	}

	// 境外 → 拦截。
	if ( $s['redirect'] ) {
		wp_safe_redirect( esc_url_raw( $s['redirect'] ), 302 );
		exit;
	}
	status_header( 403 );
	nocache_headers();
	wp_die(
		esc_html__( '当前区域暂不支持访问本站。', 'qingya' ),
		esc_html__( '403 禁止访问', 'qingya' ),
		array( 'response' => 403, 'link_url' => home_url( '/' ), 'link_text' => __( '返回首页', 'qingya' ) )
	);
}
add_action( 'init', 'qingya_geo_maybe_block', 0 );
