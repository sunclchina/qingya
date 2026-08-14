<?php
/**
 * 青崖统计（Qingya Stats）—— 本地隐私分析（设计参考 Burst Statistics）
 *
 * 特点（对齐 Burst 免费版核心 + 部分 Pro 能力，全部内置主题）：
 * - 无 Cookie、无外部依赖、无第三方服务：数据全部存本机 WP 数据库
 * - 追踪脚本 <4KB（assets/js/qingya-stats.js），REST 端点上报，兼容 sendBeacon
 * - IP 仅存「加盐哈希」（visitor_hash = HMAC-SHA256(IP|UA)），原始 IP 永不落库
 * - 内置防护（吸取 Burst 历史 CVE 教训）：REST 端点限流 + 同源校验 + 登录态 nonce；
 *   所有 SQL 走 $wpdb->prepare 预编译，输出全部转义
 * - 报表：浏览量/访客趋势、实时在线、热门内容、来源、设备、浏览器、
 *   国家分布（复用 MaxMind GeoLite2，Pro 级能力）、UTM 活动（Pro 级）、
 *   目标转化（URL 包含词命中）、数据保留自动清理
 *
 * @package Qingya
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** 数据表名（不含前缀）。 */
if ( ! defined( 'QINGYA_STATS_TABLE' ) ) {
	define( 'QINGYA_STATS_TABLE', 'qy_stats_views' );
}

/**
 * 获取统计设置。
 *
 * @return array
 */
function qingya_stats_get_settings() {
	$defaults = array(
		'enabled'        => 'on',                    // 总开关。
		'exclude_roles'  => array( 'administrator' ),// 不统计的登录角色（默认管理员）。
		'retention_days' => 180,                     // 数据保留天数。
		'respect_dnt'    => 'on',                    // 尊重 Do Not Track。
		'goals'          => '',                      // 目标：每行「标签|URL包含词」。
	);
	$saved = get_option( 'qingya_stats_settings', array() );
	return wp_parse_args( is_array( $saved ) ? $saved : array(), $defaults );
}

/**
 * 建表（dbDelta，幂等）。
 */
function qingya_stats_maybe_create_table() {
	$version = get_option( 'qingya_stats_db_version', 0 );
	if ( $version >= 1 ) {
		return;
	}
	global $wpdb;
	require_once ABSPATH . 'wp-admin/includes/upgrade.php';

	$table      = $wpdb->prefix . QINGYA_STATS_TABLE;
	$charset    = $wpdb->get_charset_collate();
	$sql        = "CREATE TABLE {$table} (
		id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
		ts DATETIME NOT NULL,
		url VARCHAR(500) NOT NULL DEFAULT '',
		url_hash CHAR(32) NOT NULL DEFAULT '',
		title VARCHAR(255) NOT NULL DEFAULT '',
		referrer VARCHAR(500) NOT NULL DEFAULT '',
		ref_hash CHAR(32) NOT NULL DEFAULT '',
		device VARCHAR(10) NOT NULL DEFAULT '',
		browser VARCHAR(32) NOT NULL DEFAULT '',
		country CHAR(2) NULL,
		visitor_hash CHAR(64) NOT NULL DEFAULT '',
		utm_source VARCHAR(100) NOT NULL DEFAULT '',
		utm_medium VARCHAR(100) NOT NULL DEFAULT '',
		utm_campaign VARCHAR(100) NOT NULL DEFAULT '',
		PRIMARY KEY  (id),
		KEY ts (ts),
		KEY url_hash (url_hash),
		KEY ref_hash (ref_hash),
		KEY visitor_hash (visitor_hash),
		KEY country (country),
		KEY device (device)
	) {$charset};"; // phpcs:ignore WordPress.DB.PreparedSQL
	dbDelta( $sql );
	update_option( 'qingya_stats_db_version', 1, false );
}
add_action( 'init', 'qingya_stats_maybe_create_table', 20 );

/**
 * 当前访客是否应被统计（角色排除）。
 *
 * @return bool
 */
function qingya_stats_should_track() {
	$s = qingya_stats_get_settings();
	if ( 'on' !== $s['enabled'] ) {
		return false;
	}
	if ( is_user_logged_in() ) {
		$user  = wp_get_current_user();
		$roles = (array) $user->roles;
		$excl  = (array) $s['exclude_roles'];
		if ( array_intersect( $roles, $excl ) ) {
			return false;
		}
	}
	return true;
}

/**
 * REST 端点限流（单 IP 每 30 秒 150 次，防刷库）。
 *
 * @param string $ip IP。
 * @return bool true=放行。
 */
function qingya_stats_rate_limit( $ip ) {
	$key   = 'qy_stats_rl_' . md5( $ip );
	$count = (int) get_transient( $key );
	if ( $count >= 150 ) {
		return false;
	}
	set_transient( $key, $count + 1, 30 );
	return true;
}

/**
 * 同源校验：Origin/Referer 与本站同源才接受；两者都缺失时仅靠限流兜底。
 *
 * @return bool
 */
function qingya_stats_check_same_origin() {
	$home = wp_parse_url( home_url(), PHP_URL_HOST );
	if ( ! $home ) {
		return true;
	}
	$origin  = isset( $_SERVER['HTTP_ORIGIN'] ) ? wp_unslash( $_SERVER['HTTP_ORIGIN'] ) : '';
	$referer = isset( $_SERVER['HTTP_REFERER'] ) ? wp_unslash( $_SERVER['HTTP_REFERER'] ) : '';
	$host    = '';
	if ( $origin ) {
		$host = wp_parse_url( $origin, PHP_URL_HOST );
	} elseif ( $referer ) {
		$host = wp_parse_url( $referer, PHP_URL_HOST );
	}
	if ( '' !== $host && strtolower( $host ) !== strtolower( $home ) ) {
		return false;
	}
	return true;
}

/**
 * 解析 User-Agent 得到浏览器名。
 *
 * @param string $ua UA。
 * @return string
 */
function qingya_stats_browser( $ua ) {
	if ( false !== strpos( $ua, 'Edg/' ) ) {
		return 'Edge';
	}
	if ( false !== strpos( $ua, 'OPR/' ) || false !== strpos( $ua, 'Opera' ) ) {
		return 'Opera';
	}
	if ( false !== strpos( $ua, 'Chrome/' ) ) {
		return 'Chrome';
	}
	if ( false !== strpos( $ua, 'Firefox/' ) ) {
		return 'Firefox';
	}
	if ( false !== strpos( $ua, 'Safari/' ) ) {
		return 'Safari';
	}
	if ( false !== strpos( $ua, 'MSIE' ) || false !== strpos( $ua, 'Trident/' ) ) {
		return 'IE';
	}
	return '其他';
}

/**
 * 按屏宽推断设备类型。
 *
 * @param int $width 屏宽。
 * @return string
 */
function qingya_stats_device( $width ) {
	if ( $width >= 1024 ) {
		return 'desktop';
	}
	if ( $width >= 768 ) {
		return 'tablet';
	}
	return 'mobile';
}

/**
 * REST 追踪回调：接收一条页面浏览记录。
 *
 * @param WP_REST_Request $request 请求。
 * @return WP_REST_Response
 */
function qingya_stats_rest_record( $request ) {
	$resp = array( 'ok' => 1 );

	// 1) 总开关。
	if ( 'on' !== qingya_stats_get_settings()['enabled'] ) {
		return rest_ensure_response( $resp );
	}

	$ip = qingya_client_ip();

	// 2) 限流（防刷）。
	if ( ! qingya_stats_rate_limit( $ip ) ) {
		return new WP_REST_Response( array( 'error' => 'rate_limited' ), 429 );
	}

	// 3) 同源校验。
	if ( ! qingya_stats_check_same_origin() ) {
		return new WP_REST_Response( array( 'error' => 'origin_mismatch' ), 403 );
	}

	// 4) 登录态：校验 nonce（访客 nonce 全站共享无意义，靠限流+同源兜底）。
	if ( is_user_logged_in() ) {
		$nonce = $request->get_header( 'X-WP-Nonce' );
		if ( ! $nonce || ! wp_verify_nonce( $nonce, 'qingya_stats' ) ) {
			return new WP_REST_Response( array( 'error' => 'bad_nonce' ), 403 );
		}
		// 排除角色不统计。
		if ( ! qingya_stats_should_track() ) {
			return rest_ensure_response( $resp );
		}
	}

	// 5) 尊重 Do Not Track。
	$dnt = (int) $request->get_param( 'dnt' );
	if ( 1 === $dnt && 'on' === qingya_stats_get_settings()['respect_dnt'] ) {
		return rest_ensure_response( $resp );
	}

	// 6) 字段清洗与截断。
	$url      = sanitize_text_field( (string) $request->get_param( 'url' ) );
	$title    = sanitize_text_field( (string) $request->get_param( 'title' ) );
	$referrer = sanitize_text_field( (string) $request->get_param( 'referrer' ) );
	$width    = max( 0, (int) $request->get_param( 'width' ) );
	if ( ! $url ) {
		$url = ( isset( $_SERVER['REQUEST_URI'] ) ? esc_url_raw( wp_unslash( $_SERVER['REQUEST_URI'] ) ) : '' );
	}
	$url      = substr( $url, 0, 500 );
	$title    = substr( $title, 0, 255 );
	$referrer = substr( $referrer, 0, 500 );
	if ( $referrer && 0 === strpos( $referrer, home_url() ) ) {
		$referrer = ''; // 站内跳转不计来源。
	}
	$utm_source   = substr( sanitize_text_field( (string) $request->get_param( 'utm_source' ) ), 0, 100 );
	$utm_medium   = substr( sanitize_text_field( (string) $request->get_param( 'utm_medium' ) ), 0, 100 );
	$utm_campaign = substr( sanitize_text_field( (string) $request->get_param( 'utm_campaign' ) ), 0, 100 );

	// 7) 访客哈希（IP+UA 加盐，原始 IP 不落库）。
	$ua           = isset( $_SERVER['HTTP_USER_AGENT'] ) ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_USER_AGENT'] ) ) : '';
	$visitor_hash = hash_hmac( 'sha256', $ip . '|' . $ua, wp_salt( 'auth' ) );

	// 8) 国家（GeoIP 库缺失时留空，界面自动隐藏地理区块）。
	$country = null;
	if ( function_exists( 'qingya_ai_geo_country' ) ) {
		$c = qingya_ai_geo_country( $ip );
		if ( is_string( $c ) && 2 === strlen( $c ) ) {
			$country = $c;
		}
	}

	// 9) 入库（预编译）。
	global $wpdb;
	$table = $wpdb->prefix . QINGYA_STATS_TABLE;
	$wpdb->insert(
		$table,
		array(
			'ts'           => current_time( 'mysql' ),
			'url'          => $url,
			'url_hash'     => md5( $url ),
			'title'        => $title,
			'referrer'     => $referrer,
			'ref_hash'     => $referrer ? md5( $referrer ) : '',
			'device'       => qingya_stats_device( $width ),
			'browser'      => qingya_stats_browser( $ua ),
			'country'      => $country,
			'visitor_hash' => $visitor_hash,
			'utm_source'   => $utm_source,
			'utm_medium'   => $utm_medium,
			'utm_campaign' => $utm_campaign,
		),
		array( '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s' )
	);

	// 10) 概率性清理过期数据（保留期外的行），免定时任务。
	if ( wp_rand( 1, 100 ) === 1 ) {
		qingya_stats_cleanup( (int) qingya_stats_get_settings()['retention_days'] );
	}

	return rest_ensure_response( $resp );
}

/**
 * 注册 REST 路由。
 */
function qingya_stats_register_rest() {
	register_rest_route(
		'qingya/v1',
		'/stats',
		array(
			'methods'             => 'POST',
			'permission_callback' => '__return_true',
			'callback'            => 'qingya_stats_rest_record',
		)
	);
}
add_action( 'rest_api_init', 'qingya_stats_register_rest' );

/**
 * 前台加载追踪脚本。
 */
function qingya_stats_frontend_assets() {
	if ( is_admin() || is_feed() || is_robots() || is_preview() ) {
		return;
	}
	if ( ! qingya_stats_should_track() ) {
		return;
	}
	wp_enqueue_script(
		'qingya-stats',
		QINGYA_URI . '/assets/js/qingya-stats.js',
		array(),
		QINGYA_VERSION,
		true
	);
	wp_localize_script(
		'qingya-stats',
		'qingyaStats',
		array(
			'url'   => esc_url_raw( rest_url( 'qingya/v1/stats' ) ),
			'nonce' => wp_create_nonce( 'qingya_stats' ),
		)
	);
}
add_action( 'wp_enqueue_scripts', 'qingya_stats_frontend_assets', 20 );

/* =====================================================
 * 报表查询 API（供 admin/analytics.php 使用）
 * ===================================================== */

/**
 * 数据表全名。
 *
 * @return string
 */
function qingya_stats_table() {
	global $wpdb;
	return $wpdb->prefix . QINGYA_STATS_TABLE;
}

/**
 * 规范化时间范围。
 *
 * @param string $from Y-m-d。
 * @param string $to   Y-m-d。
 * @return array [from, to, 含当天结束的 to_mysql]
 */
function qingya_stats_range( $from = '', $to = '' ) {
	$tz = new DateTimeZone( wp_timezone_string() );
	if ( ! $from ) {
		$from = wp_date( 'Y-m-d', time() - 7 * DAY_IN_SECONDS );
	}
	if ( ! $to ) {
		$to = wp_date( 'Y-m-d' );
	}
	$d1 = DateTime::createFromFormat( 'Y-m-d', $from, $tz );
	$d2 = DateTime::createFromFormat( 'Y-m-d', $to, $tz );
	if ( ! $d1 || ! $d2 || $d1 > $d2 ) {
		$from = wp_date( 'Y-m-d', time() - 7 * DAY_IN_SECONDS );
		$to   = wp_date( 'Y-m-d' );
		$d1   = DateTime::createFromFormat( 'Y-m-d', $from, $tz );
		$d2   = DateTime::createFromFormat( 'Y-m-d', $to, $tz );
	}
	return array(
		'from' => $from,
		'to'   => $to,
		'to_m' => $to . ' 23:59:59',
	);
}

/**
 * 总量：浏览量 / 独立访客。
 *
 * @param string $from Y-m-d。
 * @param string $to   Y-m-d。
 * @return array
 */
function qingya_stats_totals( $from = '', $to = '' ) {
	$r = qingya_stats_range( $from, $to );
	global $wpdb;
	$t  = qingya_stats_table();
	$pv = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$t} WHERE ts BETWEEN %s AND %s", $r['from'] . ' 00:00:00', $r['to_m'] ) ); // phpcs:ignore WordPress.DB.PreparedSQL
	$uv = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(DISTINCT visitor_hash) FROM {$t} WHERE ts BETWEEN %s AND %s", $r['from'] . ' 00:00:00', $r['to_m'] ) ); // phpcs:ignore WordPress.DB.PreparedSQL
	return array( 'pageviews' => $pv, 'visitors' => $uv );
}

/**
 * 按天趋势（用于图表）。
 *
 * @param string $from Y-m-d。
 * @param string $to   Y-m-d。
 * @return array 每天 [date, pageviews, visitors]
 */
function qingya_stats_trend( $from = '', $to = '' ) {
	$r = qingya_stats_range( $from, $to );
	global $wpdb;
	$t = qingya_stats_table();
	$rows = $wpdb->get_results( $wpdb->prepare(
		"SELECT DATE(ts) AS d, COUNT(*) AS pv, COUNT(DISTINCT visitor_hash) AS uv
		 FROM {$t} WHERE ts BETWEEN %s AND %s GROUP BY DATE(ts) ORDER BY d", // phpcs:ignore WordPress.DB.PreparedSQL
		$r['from'] . ' 00:00:00',
		$r['to_m']
	), ARRAY_A );

	$by_day = array();
	foreach ( $rows as $row ) {
		$by_day[ $row['d'] ] = array(
			'pageviews' => (int) $row['pv'],
			'visitors'  => (int) $row['uv'],
		);
	}

	$tz    = new DateTimeZone( wp_timezone_string() );
	$start = DateTime::createFromFormat( 'Y-m-d', $r['from'], $tz );
	$end   = DateTime::createFromFormat( 'Y-m-d', $r['to'], $tz );
	$out   = array();
	for ( $d = clone $start; $d <= $end; $d->modify( '+1 day' ) ) {
		$key         = $d->format( 'Y-m-d' );
		$out[]       = array(
			'date'      => $key,
			'pageviews' => isset( $by_day[ $key ] ) ? $by_day[ $key ]['pageviews'] : 0,
			'visitors'  => isset( $by_day[ $key ] ) ? $by_day[ $key ]['visitors'] : 0,
		);
	}
	return $out;
}

/**
 * 热门内容。
 *
 * @param string $from  Y-m-d。
 * @param string $to    Y-m-d。
 * @param int    $limit 条数。
 * @return array
 */
function qingya_stats_pages( $from = '', $to = '', $limit = 15 ) {
	$r = qingya_stats_range( $from, $to );
	global $wpdb;
	$t = qingya_stats_table();
	return $wpdb->get_results( $wpdb->prepare(
		"SELECT url_hash, MAX(url) AS url, MAX(title) AS title, COUNT(*) AS views
		 FROM {$t} WHERE ts BETWEEN %s AND %s GROUP BY url_hash ORDER BY views DESC LIMIT %d", // phpcs:ignore WordPress.DB.PreparedSQL
		$r['from'] . ' 00:00:00',
		$r['to_m'],
		(int) $limit
	), ARRAY_A );
}

/**
 * 来源（站外 referrer 按域名聚合）。
 *
 * @param string $from  Y-m-d。
 * @param string $to    Y-m-d。
 * @param int    $limit 条数。
 * @return array
 */
function qingya_stats_referrers( $from = '', $to = '', $limit = 15 ) {
	$r = qingya_stats_range( $from, $to );
	global $wpdb;
	$t    = qingya_stats_table();
	$rows = $wpdb->get_results( $wpdb->prepare(
		"SELECT ref_hash, MAX(referrer) AS referrer, COUNT(*) AS views
		 FROM {$t} WHERE ts BETWEEN %s AND %s AND referrer <> '' GROUP BY ref_hash ORDER BY views DESC LIMIT %d", // phpcs:ignore WordPress.DB.PreparedSQL
		$r['from'] . ' 00:00:00',
		$r['to_m'],
		(int) $limit
	), ARRAY_A );
	$out = array();
	foreach ( $rows as $row ) {
		$host = wp_parse_url( $row['referrer'], PHP_URL_HOST );
		$out[] = array(
			'referrer' => $row['referrer'],
			'domain'   => $host ? $host : $row['referrer'],
			'views'    => (int) $row['views'],
		);
	}
	return $out;
}

/**
 * 设备分布。
 *
 * @param string $from Y-m-d。
 * @param string $to   Y-m-d。
 * @return array
 */
function qingya_stats_devices( $from = '', $to = '' ) {
	$r = qingya_stats_range( $from, $to );
	global $wpdb;
	$t = qingya_stats_table();
	return $wpdb->get_results( $wpdb->prepare(
		"SELECT device, COUNT(*) AS views FROM {$t} WHERE ts BETWEEN %s AND %s GROUP BY device ORDER BY views DESC", // phpcs:ignore WordPress.DB.PreparedSQL
		$r['from'] . ' 00:00:00',
		$r['to_m']
	), ARRAY_A );
}

/**
 * 浏览器分布。
 *
 * @param string $from Y-m-d。
 * @param string $to   Y-m-d。
 * @return array
 */
function qingya_stats_browsers( $from = '', $to = '' ) {
	$r = qingya_stats_range( $from, $to );
	global $wpdb;
	$t = qingya_stats_table();
	return $wpdb->get_results( $wpdb->prepare(
		"SELECT browser, COUNT(*) AS views FROM {$t} WHERE ts BETWEEN %s AND %s GROUP BY browser ORDER BY views DESC", // phpcs:ignore WordPress.DB.PreparedSQL
		$r['from'] . ' 00:00:00',
		$r['to_m']
	), ARRAY_A );
}

/**
 * 国家分布（GeoIP 可用时）。
 *
 * @param string $from Y-m-d。
 * @param string $to   Y-m-d。
 * @return array
 */
function qingya_stats_countries( $from = '', $to = '' ) {
	$r = qingya_stats_range( $from, $to );
	global $wpdb;
	$t = qingya_stats_table();
	return $wpdb->get_results( $wpdb->prepare(
		"SELECT country, COUNT(*) AS views FROM {$t} WHERE ts BETWEEN %s AND %s AND country IS NOT NULL GROUP BY country ORDER BY views DESC", // phpcs:ignore WordPress.DB.PreparedSQL
		$r['from'] . ' 00:00:00',
		$r['to_m']
	), ARRAY_A );
}

/**
 * 国家码 → 中文名（常用映射，其余回退显示码）。
 *
 * @param string $code 国家码。
 * @return string
 */
function qingya_stats_country_name( $code ) {
	$map = array(
		'CN' => '中国', 'HK' => '香港', 'MO' => '澳门', 'TW' => '台湾',
		'US' => '美国', 'JP' => '日本', 'KR' => '韩国', 'SG' => '新加坡',
		'MY' => '马来西亚', 'TH' => '泰国', 'VN' => '越南', 'PH' => '菲律宾',
		'ID' => '印尼', 'IN' => '印度', 'DE' => '德国', 'GB' => '英国',
		'FR' => '法国', 'CA' => '加拿大', 'AU' => '澳大利亚', 'NL' => '荷兰',
		'RU' => '俄罗斯', 'IT' => '意大利', 'ES' => '西班牙', 'AE' => '阿联酋',
		'SA' => '沙特', 'BR' => '巴西', 'MX' => '墨西哥',
	);
	return isset( $map[ $code ] ) ? $map[ $code ] : $code;
}

/**
 * UTM 活动汇总。
 *
 * @param string $from Y-m-d。
 * @param string $to   Y-m-d。
 * @return array
 */
function qingya_stats_utms( $from = '', $to = '' ) {
	$r = qingya_stats_range( $from, $to );
	global $wpdb;
	$t = qingya_stats_table();
	return $wpdb->get_results( $wpdb->prepare(
		"SELECT utm_source, utm_medium, utm_campaign, COUNT(*) AS views
		 FROM {$t} WHERE ts BETWEEN %s AND %s AND utm_source <> '' GROUP BY utm_source, utm_medium, utm_campaign ORDER BY views DESC LIMIT 30", // phpcs:ignore WordPress.DB.PreparedSQL
		$r['from'] . ' 00:00:00',
		$r['to_m']
	), ARRAY_A );
}

/**
 * 目标转化：统计每个「URL 包含词」的命中次数。
 *
 * @param string $from Y-m-d。
 * @param string $to   Y-m-d。
 * @return array [ [label, fragment, views] ]
 */
function qingya_stats_goals( $from = '', $to = '' ) {
	$r    = qingya_stats_range( $from, $to );
	$goals = qingya_stats_parse_goals();
	if ( ! $goals ) {
		return array();
	}
	global $wpdb;
	$t    = qingya_stats_table();
	$out  = array();
	foreach ( $goals as $goal ) {
		$like = '%' . $wpdb->esc_like( $goal['fragment'] ) . '%';
		$views = (int) $wpdb->get_var( $wpdb->prepare(
			"SELECT COUNT(*) FROM {$t} WHERE ts BETWEEN %s AND %s AND url LIKE %s", // phpcs:ignore WordPress.DB.PreparedSQL
			$r['from'] . ' 00:00:00',
			$r['to_m'],
			$like
		) );
		$out[] = array(
			'label'    => $goal['label'],
			'fragment' => $goal['fragment'],
			'views'    => $views,
		);
	}
	return $out;
}

/**
 * 解析目标配置（每行「标签|URL包含词」）。
 *
 * @return array
 */
function qingya_stats_parse_goals() {
	$raw = (string) qingya_stats_get_settings()['goals'];
	$out = array();
	foreach ( preg_split( '/[\r\n]+/', $raw ) as $line ) {
		$line = trim( $line );
		if ( ! $line ) {
			continue;
		}
		$parts = array_map( 'trim', explode( '|', $line, 2 ) );
		if ( empty( $parts[1] ) ) {
			$out[] = array( 'label' => $line, 'fragment' => $line );
		} else {
			$out[] = array( 'label' => $parts[0], 'fragment' => $parts[1] );
		}
	}
	return $out;
}

/**
 * 实时在线：最近 N 分钟独立访客 + 明细。
 *
 * @param int $minutes 分钟数。
 * @return array
 */
function qingya_stats_online( $minutes = 10 ) {
	$since = wp_date( 'Y-m-d H:i:s', time() - $minutes * MINUTE_IN_SECONDS );
	global $wpdb;
	$t    = qingya_stats_table();
	$uv   = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(DISTINCT visitor_hash) FROM {$t} WHERE ts >= %s", $since ) ); // phpcs:ignore WordPress.DB.PreparedSQL
	$rows = $wpdb->get_results( $wpdb->prepare(
		"SELECT ts, url, title, device, country FROM {$t} WHERE ts >= %s ORDER BY id DESC LIMIT 20", // phpcs:ignore WordPress.DB.PreparedSQL
		$since
	), ARRAY_A );
	return array( 'online' => $uv, 'rows' => $rows );
}

/**
 * 清理保留期外数据。
 *
 * @param int $days 保留天数。
 */
function qingya_stats_cleanup( $days ) {
	$days = max( 1, (int) $days );
	global $wpdb;
	$cutoff = wp_date( 'Y-m-d H:i:s', time() - $days * DAY_IN_SECONDS );
	$wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->prefix}" . QINGYA_STATS_TABLE . ' WHERE ts < %s', $cutoff ) ); // phpcs:ignore WordPress.DB.PreparedSQL
}

/**
 * 清空全部统计。
 */
function qingya_stats_clear_all() {
	global $wpdb;
	$wpdb->query( 'TRUNCATE TABLE ' . qingya_stats_table() ); // phpcs:ignore WordPress.DB.PreparedSQL,WordPress.DB.DirectDatabaseQuery
}

/**
 * 当前数据行数。
 *
 * @return int
 */
function qingya_stats_row_count() {
	global $wpdb;
	return (int) $wpdb->get_var( 'SELECT COUNT(*) FROM ' . qingya_stats_table() ); // phpcs:ignore WordPress.DB.PreparedSQL,WordPress.DB.DirectDatabaseQuery
}
