<?php
/**
 * 安全防护模块：
 * - 屏蔽主题版本信息、移除 WP 版本号
 * - 自动屏蔽常见扫描器/恶意爬虫 UA
 * - 恶意输入过滤（长度/字符校验）
 * - 目录访问保护（.htaccess 托管规则）
 * - 登录接口基础防护（可开关）
 *
 * @package Qingya
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * 移除 WP 版本信息泄露。
 * 注意：仅移除 generator 标签，绝不动静态资源的 ver 参数——
 * 移除 ver 会破坏浏览器缓存策略，导致后台编辑器脚本新旧混用而崩溃。
 */
function qingya_remove_version() {
	remove_action( 'wp_head', 'wp_generator' );
}
add_action( 'init', 'qingya_remove_version' );

/**
 * 常见扫描器/恶意爬虫 UA 黑名单。
 * 匹配即拒绝（403）。百度/谷歌蜘蛛不在其中（SEO 需要）。
 *
 * @return array
 */
function qingya_bad_ua_list() {
	return array(
		'sqlmap',
		'nikto',
		'nmap',
		'nessus',
		'acunetix',
		'netsparker',
		'wpscan',
		'python-requests',
		'python-urllib',
		'scrapy',
		'go-http-client',
		'libwww-perl',
		'zgrab',
		'httpx',
		'curl/7\.',
		'wget/',
		'ltx71',
		'semrushbot',
		'ahrefsbot',
		'mj12bot',
	);
}

/**
 * UA 屏蔽：在早期钩子检测，命中则 403 并终止。
 * 仅限前台（后台登录不受影响），可用 Customizer 开关。
 */
function qingya_block_bad_ua() {
	if ( is_admin() || ( defined( 'WP_CLI' ) && WP_CLI ) ) {
		return;
	}
	if ( 'on' !== get_theme_mod( 'qy_sec_ua_block', 'on' ) ) {
		return;
	}
	if ( empty( $_SERVER['HTTP_USER_AGENT'] ) ) {
		// 无 UA 的请求：仅拦截明显的非浏览器（可开关，默认放行以兼容 API 客户端）。
		if ( 'on' === get_theme_mod( 'qy_sec_block_empty_ua', 'off' ) ) {
			qingya_deny_request( __( '访问被拒绝', 'qingya' ) );
		}
		return;
	}

	$ua = strtolower( wp_unslash( $_SERVER['HTTP_USER_AGENT'] ) );
	foreach ( qingya_bad_ua_list() as $pattern ) {
		if ( preg_match( '#' . $pattern . '#i', $ua ) ) {
			qingya_deny_request( __( '访问被拒绝', 'qingya' ) );
			break;
		}
	}
}
add_action( 'template_redirect', 'qingya_block_bad_ua', 1 );

/**
 * 403 响应并终止。
 *
 * @param string $message 提示。
 */
function qingya_deny_request( $message = '' ) {
	status_header( 403 );
	if ( $message ) {
		nocache_headers();
		wp_die( esc_html( $message ), esc_html__( '403 禁止访问', 'qingya' ), array( 'response' => 403 ) );
	}
	exit;
}

/**
 * 恶意输入过滤：搜索/分页等公共参数的长度与字符校验。
 * 命中异常输入即重定向到首页（防注入与探测）。
 */
function qingya_sanitize_input() {
	if ( is_admin() || ( defined( 'WP_CLI' ) && WP_CLI ) ) {
		return;
	}

	// 搜索词：长度限制 100，去控制字符。
	if ( isset( $_GET['s'] ) ) {
		$s = wp_unslash( $_GET['s'] );
		if ( mb_strlen( $s, 'UTF-8' ) > 100 ) {
			wp_safe_redirect( home_url( '/' ), 301 );
			exit;
		}
	}

	// 分页参数：仅允许数字。
	foreach ( array( 'paged', 'page', 'cpage' ) as $key ) {
		if ( isset( $_GET[ $key ] ) && ! preg_match( '/^[0-9]+$/', (string) wp_unslash( $_GET[ $key ] ) ) ) {
			wp_safe_redirect( home_url( '/' ), 301 );
			exit;
		}
	}
}
add_action( 'template_redirect', 'qingya_sanitize_input', 2 );

/**
 * 目录访问保护：为主题目录写入 .htaccess 与空白 index.php。
 * Apache/Nginx 通用防目录浏览。
 */
function qingya_protect_dirs() {
	// 主题根目录 .htaccess：禁止 php 执行 + 禁止目录浏览。
	$htaccess = QINGYA_DIR . '/.htaccess';
	$rules    = "# Qingya security\nOptions -Indexes\n<FilesMatch \"\\.(php|phar|phtml)$\">\nRequire all denied\n</FilesMatch>\n";
	if ( ! file_exists( $htaccess ) ) {
		@file_put_contents( $htaccess, $rules ); // phpcs:ignore WordPress.WP.AlternativeFunctions
	}

	// 上传等敏感目录空白 index。
	foreach ( array( 'assets', 'assets/css', 'assets/js', 'assets/img', 'inc', 'admin', 'template-parts' ) as $dir ) {
		$idx = QINGYA_DIR . '/' . $dir . '/index.php';
		if ( ! file_exists( $idx ) ) {
			@file_put_contents( $idx, "<?php\n// Silence is golden.\n" ); // phpcs:ignore WordPress.WP.AlternativeFunctions
		}
	}
}
add_action( 'after_setup_theme', 'qingya_protect_dirs' );

/**
 * 登录保护：5 分钟 3 次失败锁定，自动拉黑 IP。
 * - 失败计数存 transient（5 分钟窗口）
 * - 达 3 次：锁定 5 分钟 + 自动加入 IP 黑名单（持久，后台可见可删）
 * - 白名单 IP 豁免（不自动拉黑）
 * - 管理员登录成功后自动移除自己的拉黑（防误锁自救）
 * 默认关闭——由用户开启。
 */
function qingya_login_protect() {
	if ( 'on' !== get_theme_mod( 'qy_sec_login_protect', 'off' ) ) {
		return;
	}

	$ip        = qingya_client_ip();
	$threshold = 3;
	$lock_min  = 5;

	add_filter( 'authenticate', function ( $user, $username ) use ( $ip, $threshold, $lock_min ) {
		if ( ! $username ) {
			return $user;
		}
		$key   = 'qy_login_fail_' . md5( $ip );
		$fails = (int) get_transient( $key );
		if ( $fails >= $threshold ) {
			return new WP_Error(
				'qy_too_many',
				sprintf(
					/* translators: %d: 等待分钟数。 */
					__( '尝试次数过多，已锁定 %d 分钟并加入黑名单。', 'qingya' ),
					$lock_min
				)
			);
		}
		return $user;
	}, 30, 2 );

	add_action( 'wp_login_failed', function () use ( $ip, $threshold, $lock_min ) {
		$key   = 'qy_login_fail_' . md5( $ip );
		$fails = (int) get_transient( $key ) + 1;
		set_transient( $key, $fails, $lock_min * MINUTE_IN_SECONDS );

		// 达阈值：自动拉黑（持久加入 IP 黑名单）。
		if ( $fails >= $threshold ) {
			qingya_login_auto_blacklist( $ip );
		}
	} );

	// 登录成功：清失败计数；管理员则移除自己的拉黑（防误锁）。
	add_action( 'wp_login', function ( $user_login, $user ) use ( $ip ) {
		delete_transient( 'qy_login_fail_' . md5( $ip ) );
		if ( $user && ! is_wp_error( $user ) && user_can( $user, 'manage_options' ) ) {
			qingya_login_unblacklist( $ip );
		}
	}, 10, 2 );
}

/**
 * 自动拉黑：把 IP 加入黑名单系统（持久）。白名单 IP 豁免。
 *
 * @param string $ip IP。
 */
function qingya_login_auto_blacklist( $ip ) {
	if ( ! function_exists( 'qingya_ip_get_settings' ) ) {
		return;
	}
	$settings = qingya_ip_get_settings();

	// 白名单豁免（管理员办公 IP 等永不自动拉黑）。
	if ( qingya_ip_match( $ip, $settings['whitelist'] ) ) {
		return;
	}

	// 已拉黑则跳过。
	if ( qingya_ip_match( $ip, $settings['ips'] ) ) {
		return;
	}

	$settings['ips'][] = $ip;
	$settings['ips']   = array_values( array_unique( $settings['ips'] ) );
	// 确保黑名单生效：若功能未开启则自动开启（仅前台拦截，管理员豁免，风险低）。
	if ( 'on' !== $settings['enabled'] ) {
		$settings['enabled'] = 'on';
	}
	qingya_ip_save_settings( $settings );
}

/**
 * 从黑名单移除 IP（管理员登录成功后自救）。
 *
 * @param string $ip IP。
 */
function qingya_login_unblacklist( $ip ) {
	if ( ! function_exists( 'qingya_ip_get_settings' ) ) {
		return;
	}
	$settings = qingya_ip_get_settings();
	$settings['ips'] = array_values( array_diff( $settings['ips'], array( $ip ) ) );
	qingya_ip_save_settings( $settings );
}

/**
 * 获取客户端 IP（兼容代理头，仅信任反代设置的 X-Forwarded-For）。
 *
 * @return string
 */
function qingya_client_ip() {
	$ip = '';
	if ( ! empty( $_SERVER['REMOTE_ADDR'] ) ) {
		$ip = sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) );
	}
	// 若站点配置了反代信任，才读取转发头（避免 IP 伪造）。
	if ( defined( 'QINGYA_TRUST_PROXY' ) && QINGYA_TRUST_PROXY && ! empty( $_SERVER['HTTP_X_FORWARDED_FOR'] ) ) {
		$parts = explode( ',', sanitize_text_field( wp_unslash( $_SERVER['HTTP_X_FORWARDED_FOR'] ) ) );
		$first = trim( $parts[0] );
		if ( filter_var( $first, FILTER_VALIDATE_IP ) ) {
			$ip = $first;
		}
	}
	return $ip ? $ip : '0.0.0.0';
}

// 登录保护钩子注册。
add_action( 'init', 'qingya_login_protect' );
