<?php
/**
 * 异常访问防护（Qingya Attack Guard）
 *
 * 针对线上真实攻击场景（2026-08-09 腾讯云事故：评论页翻页 CC 刷量 +
 * xmlrpc 轰炸 + wp-login 爆破 + 垃圾评论机器人）内置的自动化防护：
 *
 * 1. 全站请求频率限制：单 IP 每分钟超阈值 → 自动临时封禁（默认 30 分钟），
 *    连续 3 次触发自动转「永久黑名单」（复用 IP 黑名单系统，白名单豁免）
 * 2. XMLRPC 直接掐断（默认开启）：杜绝 pingback 放大/暴力破解通道
 * 3. 评论防护：同 IP 评论频率限制 + 垃圾关键词过滤（复用 AI 客服词库）+ 外链检测
 *    （≥2 个外链直接 spam）+ 新评论强制人工审核
 * 4. 封禁日志写入 IP 黑名单日志表（reason=attack），后台可解除/转永久
 *
 * 设计原则：管理员与白名单永不误伤；蜘蛛放行；全部设置可关可调。
 *
 * @package Qingya
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * 获取防护设置。
 *
 * @return array
 */
function qingya_attack_get_settings() {
	$defaults = array(
		'enabled'         => 'on',   // 总开关。
		'rate_enabled'    => 'on',   // 全站频率限制。
		'rate_limit'      => 30,     // 次/分钟 阈值（真人阅读 1~10 页/分钟，30 足够宽松且能拦截刷量）。
		'ban_minutes'     => 30,     // 临时封禁分钟。
		'auto_perm_ban'   => 'off',  // 超限直接入永久黑名单（默认否：连续 3 次临时封禁才转永久）。
		'xmlrpc_block'    => 'on',   // 禁用 XMLRPC。
		'comment_enabled' => 'on',   // 评论防护。
		'comment_rate'    => 5,      // 同 IP 10 分钟最多评论数。
		'comment_moderate'=> 'on',   // 非管理员超频后强制人工审核。
		'comment_meaningless' => 'on', // 拦截无意义评论（凑字/纯符号/乱码/键盘乱敲）。
		'keywords'        => '',     // 自定义垃圾词（每行一个，叠加内置词库）。
	);
	$saved = get_option( 'qingya_attack_guard', array() );
	return wp_parse_args( is_array( $saved ) ? $saved : array(), $defaults );
}

/**
 * 保存设置。
 *
 * @param array $settings 设置。
 * @return bool
 */
function qingya_attack_save_settings( $settings ) {
	return update_option( 'qingya_attack_guard', $settings, false );
}

/**
 * 内置垃圾评论词库（叠加 AI 客服敏感词，独立可用）。
 *
 * @return array
 */
function qingya_attack_spam_words() {
	$words = array(
		// 广告与推广。
		'加微信', '加v信', '加V', '联系微信', '微信号', '加qq', '加 qq', 'qq群',
		'代开发票', '开发票', '办证', '刻章', '博彩', '赌博', '开户送', '兼职刷单',
		'刷单', '日赚', '月入', '稳赚', '免费领取', '点击抽奖', '中奖信息', '恭喜您中奖',
		'贷款秒批', '无抵押贷款', '网贷', '裸聊', '一夜情', '约炮', '包养', '援交', '卖淫',
		'迷药', '枪支', '假币', '假证', '刷流量', '刷粉丝', '外挂', '破解版', '私服',
		'代购', '代理加盟', '详情加', 'telegram', 'tg:', 't.me/', 'whatsapp',
		// 灌水。
		'顶顶顶', '灌水', '水贴', '沙发沙发', '前排围观', '飘过',
	);
	// 合并 AI 客服词库（模块存在时）。
	if ( function_exists( 'qingya_ai_sensitive_words' ) ) {
		$words = array_merge( $words, qingya_ai_sensitive_words() );
	}
	// 合并自定义词库。
	$custom = qingya_attack_get_settings()['keywords'];
	foreach ( preg_split( '/[\r\n,]+/', (string) $custom ) as $w ) {
		$w = trim( $w );
		if ( $w ) {
			$words[] = $w;
		}
	}
	return array_values( array_unique( $words ) );
}

/**
 * 临时封禁列表。
 *
 * @return array ip => array( 'expiry' => int, 'count' => int )
 */
function qingya_attack_bans() {
	$bans = get_option( 'qingya_attack_bans', array() );
	return is_array( $bans ) ? $bans : array();
}

/**
 * 检查 IP 是否被临时封禁。
 *
 * @param string $ip IP。
 * @return bool
 */
function qingya_attack_is_banned( $ip ) {
	$bans = qingya_attack_bans();
	if ( empty( $bans[ $ip ] ) ) {
		return false;
	}
	if ( (int) $bans[ $ip ]['expiry'] < time() ) {
		unset( $bans[ $ip ] );
		update_option( 'qingya_attack_bans', $bans, false );
		return false;
	}
	return true;
}

/**
 * 临时封禁 IP。
 *
 * @param string $ip IP。
 */
function qingya_attack_ban( $ip ) {
	$bans = qingya_attack_bans();
	$s    = qingya_attack_get_settings();
	$now  = time();

	$count = isset( $bans[ $ip ] ) ? (int) $bans[ $ip ]['count'] + 1 : 1;
	$bans[ $ip ] = array(
		'expiry' => $now + max( 5, (int) $s['ban_minutes'] ) * MINUTE_IN_SECONDS,
		'count'  => $count,
	);

	// 连续 3 次触发临时封禁 → 转永久黑名单（白名单豁免，见 qingya_login_auto_blacklist）。
	if ( $count >= 3 && function_exists( 'qingya_login_auto_blacklist' ) ) {
		qingya_login_auto_blacklist( $ip );
		$bans[ $ip ]['perm'] = 1;
	}
	update_option( 'qingya_attack_bans', $bans, false );
}

/**
 * 解除临时封禁。
 *
 * @param string $ip IP。
 */
function qingya_attack_unban( $ip ) {
	$bans = qingya_attack_bans();
	unset( $bans[ $ip ] );
	update_option( 'qingya_attack_bans', $bans, false );
}

/**
 * 403 拒绝响应。
 *
 * @param string $ip     IP。
 * @param string $reason 原因（rate=频率超限 ban=临时封禁）。
 */
function qingya_attack_deny( $ip, $reason ) {
	// 记日志（reason=attack）。
	if ( function_exists( 'qingya_ip_log' ) ) {
		$url = isset( $_SERVER['REQUEST_URI'] ) ? esc_url_raw( wp_unslash( $_SERVER['REQUEST_URI'] ) ) : '';
		$ua  = isset( $_SERVER['HTTP_USER_AGENT'] ) ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_USER_AGENT'] ) ) : '';
		qingya_ip_log( $ip, $url . ' [' . $reason . ']', $ua, 'attack' );
	}
	status_header( 403 );
	nocache_headers();
	wp_die(
		esc_html__( '访问过于频繁，请稍后再试。', 'qingya' ),
		esc_html__( '403 请求受限', 'qingya' ),
		array( 'response' => 403, 'link_url' => home_url( '/' ), 'link_text' => __( '返回首页', 'qingya' ) )
	);
}

/**
 * 防护主入口（init 早期钩子）。
 */
function qingya_attack_guard_run() {
	$s = qingya_attack_get_settings();
	if ( 'on' !== $s['enabled'] ) {
		return;
	}

	// 管理员无条件豁免（防误锁站长）。
	if ( is_user_logged_in() && current_user_can( 'manage_options' ) ) {
		return;
	}

	// XMLRPC：认证通过的管理员放行（AI 工具/客户端正常使用），其余一律 403。
	// 注意：用户名+密码认证发生在请求后半段，此处只挂检查钩子，认证后再判。
	if ( 'on' === $s['xmlrpc_block'] && defined( 'XMLRPC_REQUEST' ) && XMLRPC_REQUEST ) {
		add_action( 'xmlrpc_call', 'qingya_attack_xmlrpc_guard', 1 );
	}

	$ip = function_exists( 'qingya_client_ip' ) ? qingya_client_ip() : ( isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '0.0.0.0' );

	// 白名单豁免（黑名单 + 境外拦截白名单互通）。
	if ( function_exists( 'qingya_ip_whitelisted' ) && qingya_ip_whitelisted( $ip ) ) {
		return;
	}

	// 蜘蛛放行。
	if ( function_exists( 'qingya_ip_is_spider' ) && ! empty( $_SERVER['HTTP_USER_AGENT'] ) && qingya_ip_is_spider( wp_unslash( $_SERVER['HTTP_USER_AGENT'] ) ) ) {
		return;
	}

	// 临时封禁检查。
	if ( qingya_attack_is_banned( $ip ) ) {
		qingya_attack_deny( $ip, 'ban' );
	}

	// 全站频率限制。
	if ( 'on' === $s['rate_enabled'] ) {
		$limit = max( 5, (int) $s['rate_limit'] );
		$key   = 'qy_ag_rate_' . md5( $ip );
		$count = (int) get_transient( $key );
		$count++;
		if ( $count > $limit ) {
			qingya_attack_ban( $ip );
			// 可选：直接入永久黑名单。
			if ( 'on' === $s['auto_perm_ban'] && function_exists( 'qingya_login_auto_blacklist' ) ) {
				qingya_login_auto_blacklist( $ip );
			}
			qingya_attack_deny( $ip, 'rate' );
		}
		set_transient( $key, $count, 60 );
	}
}
add_action( 'init', 'qingya_attack_guard_run', 2 );

/**
 * XMLRPC 方法调用守卫（认证后触发）：管理员放行，其余 403。
 */
function qingya_attack_xmlrpc_guard() {
	if ( ! ( is_user_logged_in() && current_user_can( 'manage_options' ) ) ) {
		status_header( 403 );
		nocache_headers();
		exit;
	}
}

/**
 * 禁用 XMLRPC（双重保险：WordPress 层面 + init 层面）。
 */
function qingya_attack_xmlrpc() {
	return false;
}
add_filter( 'xmlrpc_enabled', 'qingya_attack_xmlrpc' );

/* =====================================================
 * 评论防护
 * ===================================================== */

/**
 * 无意义评论检测（启发式，零外部依赖）。
 *
 * 判定为无意义（无观点/无实质内容）的特征：
 * - 去空白后不足 3 字符
 * - 去空白后唯一字符 ≤2 且长度 <20（如 111111 / 哈哈哈哈 / 666666）
 * - 纯符号/数字/表情（不含中文也不含字母）
 * - 编码乱码（U+FFFD 替换符、€、常见 GBK→UTF8 乱码区汉字）
 * - 连续 ≥8 个无元音辅音（键盘乱敲 asdfghjkl）
 * - 最常见字符占比 >60% 且长度 <30（高频凑字）
 *
 * @param string $text 评论内容。
 * @return bool true=无意义应拦截。
 */
function qingya_attack_content_meaningless( $text ) {
	$clean = preg_replace( '/\s+/u', '', (string) $text );
	$len   = mb_strlen( $clean );

	// 过短。
	if ( $len < 3 ) {
		return true;
	}

	// 唯一字符极少（111111 / 哈哈哈哈 / 666666 / 。。。）。
	$chars  = preg_split( '//u', $clean, -1, PREG_SPLIT_NO_EMPTY );
	$unique = count( array_unique( $chars ) );
	if ( $unique <= 2 && $len < 20 ) {
		return true;
	}

	// 无中文且无字母（纯符号/数字/表情）。
	$has_cjk    = (bool) preg_match( '/[\x{4e00}-\x{9fff}]/u', $clean );
	$has_letter = (bool) preg_match( '/[a-zA-Z]/u', $clean );
	if ( ! $has_cjk && ! $has_letter ) {
		return true;
	}

	// 编码乱码（U+FFFD / € / GBK→UTF8 常见乱码区）。
	if ( preg_match( '/[\x{FFFD}\x{20AC}\x{9D00}-\x{9FFF}]/u', $clean ) ) {
		return true;
	}

	// 键盘乱敲：≥8 个无元音辅音。
	if ( preg_match( '/[bcdfghjklmnpqrstvwxzBCDFGHJKLMNPQRSTVWXZ]{8,}/', $clean ) ) {
		return true;
	}

	// 高频凑字：最常见字符占比 >60% 且不长（顶顶顶顶顶顶 / aaaaaaa）。
	$freq = array_count_values( $chars );
	$max  = max( $freq );
	if ( $max / $len > 0.6 && $len < 30 ) {
		return true;
	}

	return false;
}

/**
 * 评论提交防护：频率限制 + 垃圾检测 + 无意义检测。
 *
 * @param array $comment 评论数据。
 * @return array
 */
function qingya_attack_check_comment( $comment ) {
	$s = qingya_attack_get_settings();
	if ( 'on' !== $s['comment_enabled'] ) {
		return $comment;
	}
	if ( is_user_logged_in() && current_user_can( 'manage_options' ) ) {
		return $comment;
	}

	$ip = function_exists( 'qingya_client_ip' ) ? qingya_client_ip() : ( isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '' );

	// 白名单豁免。
	if ( $ip && function_exists( 'qingya_ip_whitelisted' ) && qingya_ip_whitelisted( $ip ) ) {
		return $comment;
	}

	// ① 频率限制：同 IP 10 分钟窗口。超频不拒绝，转为强制人工审核（管理员/白名单已豁免）。
	if ( $ip && (int) $s['comment_rate'] > 0 ) {
		$key   = 'qy_ag_cmt_' . md5( $ip );
		$count = (int) get_transient( $key );
		if ( $count >= (int) $s['comment_rate'] ) {
			$comment['comment_approved'] = '0';
		}
		set_transient( $key, $count + 1, 10 * MINUTE_IN_SECONDS );
	}

	// ② 无意义评论检测（无观点/凑字/纯符号/乱码/键盘乱敲）。
	$content = isset( $comment['comment_content'] ) ? (string) $comment['comment_content'] : '';
	$author  = isset( $comment['comment_author'] ) ? (string) $comment['comment_author'] : '';
	if ( 'on' === $s['comment_meaningless'] && qingya_attack_content_meaningless( $content ) ) {
		$comment['comment_approved'] = 'spam';
		return $comment;
	}

	// ③ 垃圾关键词检测 → 直接标记 spam。
	foreach ( qingya_attack_spam_words() as $word ) {
		if ( false !== mb_strpos( $content . ' ' . $author, $word ) ) {
			$comment['comment_approved'] = 'spam';
			return $comment;
		}
	}

	// ③ 外链检测：
	// a) 手写 <a href> HTML 标签 → 直接 spam（正常评论者不会手写标签，WP 会自动转链）。
	if ( preg_match( '/<a\s+[^>]*href\s*=/i', $content ) ) {
		$comment['comment_approved'] = 'spam';
		return $comment;
	}

	// b) 纯链接/链接为主：
	//    - 去掉 URL 后几乎无实质文字 → spam（典型机器人评论）；
	//    - 无中文字符且剩余文字很少（英文废话 + 外链，中文站常见机器人模式）→ spam。
	$plain = trim( wp_strip_all_tags( preg_replace( '#https?://[^\s<>"\']+#i', '', $content ) ) );
	if ( substr_count( $content, 'http://' ) + substr_count( $content, 'https://' ) >= 1 ) {
		$len = mb_strlen( $plain );
		if ( $len < 10 ) {
			$comment['comment_approved'] = 'spam';
			return $comment;
		}
		if ( $len < 50 && ! preg_match( '/[\x{4e00}-\x{9fff}]/u', $plain ) ) {
			$comment['comment_approved'] = 'spam';
			return $comment;
		}
	}

	// c) ≥2 个链接 → spam。
	if ( substr_count( $content, 'http://' ) + substr_count( $content, 'https://' ) >= 2 ) {
		$comment['comment_approved'] = 'spam';
		return $comment;
	}

	return $comment;
}
add_filter( 'preprocess_comment', 'qingya_attack_check_comment' );

/**
 * 评论审批策略：管理员免审；垃圾保持垃圾；超频评论保持待审；其余交 WP 默认规则。
 *
 * @param string $approved 审批状态。
 * @param array  $comment  评论数据。
 * @return string
 */
function qingya_attack_comment_approved( $approved, $comment ) {
	$s = qingya_attack_get_settings();
	if ( 'on' !== $s['comment_enabled'] ) {
		return $approved;
	}
	// 管理员账户例外：直接通过，不进入人工审核。
	if ( ! empty( $comment['user_id'] ) && user_can( $comment['user_id'], 'manage_options' ) ) {
		return '1';
	}
	// 命中垃圾词/外链：保持 spam。
	if ( 'spam' === $approved ) {
		return $approved;
	}
	// 非管理员评论超过频率限制（已由 preprocess_comment 标记 0）：保持待审。
	if ( 'on' === $s['comment_moderate'] && '0' === $approved ) {
		return '0';
	}
	// 未超频：不干预，交给 WordPress 默认评论策略。
	return $approved;
}
add_filter( 'pre_comment_approved', 'qingya_attack_comment_approved', 10, 2 );

/**
 * 清理过期临时封禁（概率性，免定时任务）。
 */
function qingya_attack_cleanup() {
	if ( wp_rand( 1, 100 ) !== 1 ) {
		return;
	}
	$bans = qingya_attack_bans();
	$now  = time();
	$dirty = false;
	foreach ( $bans as $ip => $info ) {
		if ( (int) $info['expiry'] < $now ) {
			unset( $bans[ $ip ] );
			$dirty = true;
		}
	}
	if ( $dirty ) {
		update_option( 'qingya_attack_bans', $bans, false );
	}
}
add_action( 'init', 'qingya_attack_cleanup', 99 );
