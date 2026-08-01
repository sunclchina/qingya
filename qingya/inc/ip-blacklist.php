<?php
/**
 * IP 黑名单系统（核心逻辑）：
 * - 单 IP / IP 段（192.168.1.*）/ CIDR（123.45.67.0/24）匹配
 * - 拦截策略：403 / 跳转提示页 / 跳转指定 URL
 * - 白名单豁免（管理员办公 IP、搜索引擎蜘蛛自动放行）
 * - 访问日志（自定义表 qingya_ip_logs），后台可查可清
 * - 总开关 + 仅前台拦截（不影响 WP 后台登录）
 *
 * @package Qingya
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'QINGYA_IP_LOG_TABLE', 'qingya_ip_logs' );

/**
 * 获取黑名单配置（带默认值）。
 *
 * @return array
 */
function qingya_ip_get_settings() {
	$defaults = array(
		'enabled'       => 'off',
		'scope'         => 'front',      // front=仅前台 all=全部
		'strategy'      => '403',        // 403 | page | url
		'page_msg'      => __( '您的 IP 已被限制访问。如有疑问，请联系管理员。', 'qingya' ),
		'url'           => '',
		'ips'           => array(),      // 黑名单
		'whitelist'     => array(),      // 白名单
		'spider_bypass' => 'on',         // 蜘蛛放行
		'log_enabled'   => 'on',         // 日志
	);
	$settings = get_option( 'qingya_ip_blacklist', array() );
	return wp_parse_args( $settings, $defaults );
}

/**
 * 保存黑名单配置。
 *
 * @param array $settings 配置。
 * @return bool
 */
function qingya_ip_save_settings( $settings ) {
	$settings['ips']       = qingya_ip_normalize_list( $settings['ips'] );
	$settings['whitelist'] = qingya_ip_normalize_list( $settings['whitelist'] );
	return update_option( 'qingya_ip_blacklist', $settings );
}

/**
 * 规范化 IP 列表：去空白、去重、过滤非法格式。
 *
 * @param mixed $list 输入（数组或换行文本）。
 * @return array
 */
function qingya_ip_normalize_list( $list ) {
	if ( is_string( $list ) ) {
		$list = preg_split( '/[\r\n,]+/', $list );
	}
	if ( ! is_array( $list ) ) {
		return array();
	}
	$out = array();
	foreach ( $list as $item ) {
		$item = trim( (string) $item );
		if ( '' === $item ) {
			continue;
		}
		// 支持：单 IP、IP 段（192.168.1.*）、CIDR。
		if ( qingya_ip_valid( $item ) ) {
			$out[ $item ] = $item;
		}
	}
	return array_values( $out );
}

/**
 * 校验 IP 规则格式。
 *
 * @param string $rule 规则。
 * @return bool
 */
function qingya_ip_valid( $rule ) {
	// 单 IP。
	if ( filter_var( $rule, FILTER_VALIDATE_IP ) ) {
		return true;
	}
	// 段：192.168.1.*
	if ( preg_match( '/^(\d{1,3}\.){1,3}\*$/', $rule ) ) {
		$base = str_replace( '*', '0', $rule );
		return (bool) filter_var( $base, FILTER_VALIDATE_IP );
	}
	// CIDR：123.45.67.0/24
	if ( preg_match( '#^(\d{1,3}\.){3}\d{1,3}/\d{1,2}$#', $rule ) ) {
		list( $ip, $prefix ) = explode( '/', $rule );
		$prefix = (int) $prefix;
		return filter_var( $ip, FILTER_VALIDATE_IP ) && $prefix >= 0 && $prefix <= 32;
	}
	return false;
}

/**
 * 判断 IP 是否命中规则列表。
 *
 * @param string $ip   客户端 IP。
 * @param array  $list 规则列表。
 * @return bool
 */
function qingya_ip_match( $ip, $list ) {
	if ( empty( $list ) ) {
		return false;
	}
	$ip_long = ip2long( $ip );

	foreach ( $list as $rule ) {
		if ( false !== strpos( $rule, '/' ) ) {
			// CIDR。
			list( $net, $prefix ) = explode( '/', $rule );
			$net_long = ip2long( $net );
			$mask     = $prefix > 0 ? ( 0xFFFFFFFF << ( 32 - $prefix ) ) & 0xFFFFFFFF : 0;
			if ( false !== $ip_long && false !== $net_long && ( $ip_long & $mask ) === ( $net_long & $mask ) ) {
				return true;
			}
		} elseif ( false !== strpos( $rule, '*' ) ) {
			// IP 段。
			$pattern = str_replace( '.', '\.', str_replace( '*', '\d{1,3}', $rule ) );
			if ( preg_match( '/^' . $pattern . '$/', $ip ) ) {
				return true;
			}
		} elseif ( $rule === $ip ) {
			return true;
		}
	}
	return false;
}

/**
 * 是否为搜索引擎蜘蛛 UA（白名单豁免）。
 *
 * @param string $ua UA。
 * @return bool
 */
function qingya_ip_is_spider( $ua ) {
	$spiders = array( 'baiduspider', 'googlebot', 'bingbot', 'sogou', '360spider', 'yisouspider', 'bytespider', 'toutiaospider', 'petalbot', 'yandexbot' );
	$ua      = strtolower( $ua );
	foreach ( $spiders as $spider ) {
		if ( false !== strpos( $ua, $spider ) ) {
			return true;
		}
	}
	return false;
}

/**
 * 建日志表（dbDelta）。
 */
function qingya_ip_create_log_table() {
	global $wpdb;
	$table           = $wpdb->prefix . QINGYA_IP_LOG_TABLE;
	$charset_collate = $wpdb->get_charset_collate();
	$sql             = "CREATE TABLE {$table} (
		id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
		ip VARCHAR(45) NOT NULL DEFAULT '',
		url VARCHAR(255) NOT NULL DEFAULT '',
		ua VARCHAR(255) NOT NULL DEFAULT '',
		created_at DATETIME NOT NULL,
		PRIMARY KEY  (id),
		KEY ip (ip),
		KEY created_at (created_at)
	) {$charset_collate};";
	require_once ABSPATH . 'wp-admin/includes/upgrade.php';
	dbDelta( $sql );
}
add_action( 'after_setup_theme', 'qingya_ip_create_log_table' );

/**
 * 记录拦截日志。
 *
 * @param string $ip  IP。
 * @param string $url URL。
 * @param string $ua  UA。
 */
function qingya_ip_log( $ip, $url, $ua ) {
	global $wpdb;
	$table = $wpdb->prefix . QINGYA_IP_LOG_TABLE;
	$wpdb->insert( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$table,
		array(
			'ip'         => substr( $ip, 0, 45 ),
			'url'        => substr( $url, 0, 255 ),
			'ua'         => substr( $ua, 0, 255 ),
			'created_at' => current_time( 'mysql' ),
		),
		array( '%s', '%s', '%s', '%s' )
	);
}

/**
 * 执行拦截（入口）：挂 init 早期钩子，可覆盖 wp-admin 与 wp-login.php。
 */
function qingya_ip_maybe_block() {
	$settings = qingya_ip_get_settings();

	// 总开关。
	if ( 'on' !== $settings['enabled'] ) {
		return;
	}

	// 已登录管理员无条件豁免：防止误锁后台/误拦站长（安全兜底）。
	if ( is_user_logged_in() && current_user_can( 'manage_options' ) ) {
		return;
	}

	// 上下文判断：后台登录页与 wp-admin。
	$php_self = isset( $_SERVER['PHP_SELF'] ) ? wp_unslash( $_SERVER['PHP_SELF'] ) : '';
	$is_login = ( false !== strpos( $php_self, 'wp-login.php' ) );
	if ( is_admin() || $is_login ) {
		// 仅前台拦截模式：后台与登录页一律放行。
		if ( 'front' === $settings['scope'] ) {
			return;
		}
		// 全部拦截模式：后台也查，但管理员白名单在下方统一处理。
	}

	$ip = qingya_client_ip();

	// 白名单优先。
	if ( qingya_ip_match( $ip, $settings['whitelist'] ) ) {
		return;
	}

	// 蜘蛛放行。
	if ( 'on' === $settings['spider_bypass'] && ! empty( $_SERVER['HTTP_USER_AGENT'] ) && qingya_ip_is_spider( wp_unslash( $_SERVER['HTTP_USER_AGENT'] ) ) ) {
		return;
	}

	// 黑名单命中 → 拦截。
	if ( qingya_ip_match( $ip, $settings['ips'] ) ) {
		// 日志。
		if ( 'on' === $settings['log_enabled'] ) {
			$url = isset( $_SERVER['REQUEST_URI'] ) ? esc_url_raw( wp_unslash( $_SERVER['REQUEST_URI'] ) ) : '';
			$ua  = isset( $_SERVER['HTTP_USER_AGENT'] ) ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_USER_AGENT'] ) ) : '';
			qingya_ip_log( $ip, $url, $ua );
		}

		switch ( $settings['strategy'] ) {
			case 'url':
				$target = esc_url_raw( $settings['url'] );
				if ( $target ) {
					wp_safe_redirect( $target, 302 );
					exit;
				}
				break;
			case 'page':
				status_header( 403 );
				nocache_headers();
				wp_die(
					esc_html( $settings['page_msg'] ),
					esc_html__( '403 禁止访问', 'qingya' ),
					array( 'response' => 403, 'link_url' => home_url( '/' ), 'link_text' => __( '返回首页', 'qingya' ) )
				);
				break;
			default:
				status_header( 403 );
				nocache_headers();
				exit;
		}
	}
}
add_action( 'init', 'qingya_ip_maybe_block', 0 );
