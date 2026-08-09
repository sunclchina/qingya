<?php
/**
 * 异常访问防护管理页（设置 + 临时封禁列表 + 统计）。
 *
 * @package Qingya
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * 注册管理菜单。
 */
function qingya_attack_admin_menu() {
	add_menu_page(
		__( '异常防护', 'qingya' ),
		__( '异常防护', 'qingya' ),
		'manage_options',
		'qingya-attack-guard',
		'qingya_attack_admin_page',
		'dashicons-shield-alt',
		82
	);
}
add_action( 'admin_menu', 'qingya_attack_admin_menu' );

/**
 * 处理表单提交（保存设置 / 解除封禁 / 转永久 / 清空封禁）。
 */
function qingya_attack_admin_handle_post() {
	if ( ! isset( $_POST['qingya_attack_nonce'] ) ) {
		return;
	}
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( esc_html__( '权限不足。', 'qingya' ) );
	}
	check_admin_referer( 'qingya_attack_save', 'qingya_attack_nonce' );

	// 解除单个封禁。
	if ( isset( $_POST['qingya_attack_unban'] ) && isset( $_POST['qingya_attack_ip'] ) ) {
		qingya_attack_unban( sanitize_text_field( wp_unslash( $_POST['qingya_attack_ip'] ) ) );
		wp_safe_redirect( add_query_arg( 'qy-msg', 'unbanned', wp_get_referer() ) );
		exit;
	}

	// 转永久黑名单。
	if ( isset( $_POST['qingya_attack_perm'] ) && isset( $_POST['qingya_attack_ip'] ) ) {
		$ip = sanitize_text_field( wp_unslash( $_POST['qingya_attack_ip'] ) );
		if ( function_exists( 'qingya_login_auto_blacklist' ) ) {
			qingya_login_auto_blacklist( $ip );
		}
		qingya_attack_unban( $ip );
		wp_safe_redirect( add_query_arg( 'qy-msg', 'permed', wp_get_referer() ) );
		exit;
	}

	// 清空全部临时封禁。
	if ( isset( $_POST['qingya_attack_clear'] ) ) {
		update_option( 'qingya_attack_bans', array(), false );
		wp_safe_redirect( add_query_arg( 'qy-msg', 'cleared', wp_get_referer() ) );
		exit;
	}

	// 保存设置。
	$settings = array(
		'enabled'          => isset( $_POST['qingya_attack_enabled'] ) ? 'on' : 'off',
		'rate_enabled'     => isset( $_POST['qingya_attack_rate_enabled'] ) ? 'on' : 'off',
		'rate_limit'       => isset( $_POST['qingya_attack_rate_limit'] ) ? max( 5, min( 600, (int) $_POST['qingya_attack_rate_limit'] ) ) : 30,
		'ban_minutes'      => isset( $_POST['qingya_attack_ban_minutes'] ) ? max( 5, min( 1440, (int) $_POST['qingya_attack_ban_minutes'] ) ) : 30,
		'auto_perm_ban'    => isset( $_POST['qingya_attack_auto_perm'] ) ? 'on' : 'off',
		'xmlrpc_block'     => isset( $_POST['qingya_attack_xmlrpc'] ) ? 'on' : 'off',
		'comment_enabled'  => isset( $_POST['qingya_attack_comment_enabled'] ) ? 'on' : 'off',
		'comment_rate'     => isset( $_POST['qingya_attack_comment_rate'] ) ? max( 1, min( 100, (int) $_POST['qingya_attack_comment_rate'] ) ) : 5,
		'comment_moderate' => isset( $_POST['qingya_attack_comment_moderate'] ) ? 'on' : 'off',
		'keywords'         => isset( $_POST['qingya_attack_keywords'] ) ? sanitize_textarea_field( wp_unslash( $_POST['qingya_attack_keywords'] ) ) : '',
	);
	qingya_attack_save_settings( $settings );

	wp_safe_redirect( add_query_arg( 'qy-msg', 'saved', wp_get_referer() ) );
	exit;
}
add_action( 'admin_init', 'qingya_attack_admin_handle_post' );

/**
 * 管理页渲染。
 */
function qingya_attack_admin_page() {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( esc_html__( '权限不足。', 'qingya' ) );
	}

	$msg  = isset( $_GET['qy-msg'] ) ? sanitize_key( wp_unslash( $_GET['qy-msg'] ) ) : '';
	$s    = qingya_attack_get_settings();
	$bans = qingya_attack_bans();

	// 今日封禁统计（日志表 reason=attack）。
	$today = 0;
	global $wpdb;
	if ( defined( 'QINGYA_IP_LOG_TABLE' ) ) {
		$table = $wpdb->prefix . QINGYA_IP_LOG_TABLE;
		$today = (int) $wpdb->get_var( $wpdb->prepare(
			"SELECT COUNT(*) FROM {$table} WHERE reason = 'attack' AND created_at >= %s", // phpcs:ignore WordPress.DB.PreparedSQL
			gmdate( 'Y-m-d 00:00:00' )
		) );
	}
	?>
	<div class="wrap">
		<h1><?php esc_html_e( '异常访问防护', 'qingya' ); ?></h1>
		<p class="description"><?php esc_html_e( '自动拦截高频刷量、评论轰炸、XMLRPC 攻击与垃圾评论。管理员与白名单 IP 永不误伤。', 'qingya' ); ?></p>

		<?php if ( 'saved' === $msg ) : ?>
			<div class="notice notice-success is-dismissible"><p><?php esc_html_e( '设置已保存。', 'qingya' ); ?></p></div>
		<?php elseif ( 'unbanned' === $msg ) : ?>
			<div class="notice notice-success is-dismissible"><p><?php esc_html_e( '已解除该 IP 的临时封禁。', 'qingya' ); ?></p></div>
		<?php elseif ( 'permed' === $msg ) : ?>
			<div class="notice notice-success is-dismissible"><p><?php esc_html_e( '已加入永久黑名单。', 'qingya' ); ?></p></div>
		<?php elseif ( 'cleared' === $msg ) : ?>
			<div class="notice notice-success is-dismissible"><p><?php esc_html_e( '临时封禁已全部清空。', 'qingya' ); ?></p></div>
		<?php endif; ?>

		<div style="display:flex;gap:16px;flex-wrap:wrap;margin:16px 0;">
			<div style="flex:1;min-width:160px;background:#fff;border:1px solid #dcdcde;border-radius:6px;padding:16px;">
				<div style="color:#646970;font-size:13px;"><?php esc_html_e( '当前临时封禁数', 'qingya' ); ?></div>
				<div style="font-size:26px;font-weight:600;color:#1d2327;"><?php echo count( $bans ); ?></div>
			</div>
			<div style="flex:1;min-width:160px;background:#fff;border:1px solid #dcdcde;border-radius:6px;padding:16px;">
				<div style="color:#646970;font-size:13px;"><?php esc_html_e( '今日自动封禁次数', 'qingya' ); ?></div>
				<div style="font-size:26px;font-weight:600;color:#1d2327;"><?php echo number_format_i18n( $today ); ?></div>
			</div>
			<div style="flex:1;min-width:200px;background:#fff;border:1px solid #dcdcde;border-radius:6px;padding:16px;">
				<div style="color:#646970;font-size:13px;"><?php esc_html_e( '当前状态', 'qingya' ); ?></div>
				<div style="font-size:18px;font-weight:600;color:<?php echo 'on' === $s['enabled'] ? '#00a32a' : '#d63638'; ?>;">
					<?php echo 'on' === $s['enabled'] ? esc_html__( '防护运行中', 'qingya' ) : esc_html__( '已关闭', 'qingya' ); ?>
				</div>
			</div>
		</div>

		<h2 style="font-size:14px;"><?php esc_html_e( '临时封禁列表', 'qingya' ); ?></h2>
		<table class="widefat striped" style="max-width:820px;">
			<thead><tr>
				<th><?php esc_html_e( 'IP', 'qingya' ); ?></th>
				<th><?php esc_html_e( '触发次数', 'qingya' ); ?></th>
				<th><?php esc_html_e( '解除时间', 'qingya' ); ?></th>
				<th><?php esc_html_e( '操作', 'qingya' ); ?></th>
			</tr></thead>
			<tbody>
			<?php if ( ! $bans ) : ?>
				<tr><td colspan="4"><?php esc_html_e( '暂无临时封禁。', 'qingya' ); ?></td></tr>
			<?php endif; ?>
			<?php foreach ( $bans as $ip => $info ) : ?>
				<tr>
					<td><code><?php echo esc_html( $ip ); ?></code><?php echo ! empty( $info['perm'] ) ? ' <span style="color:#d63638;">(已转永久)</span>' : ''; ?></td>
					<td><?php echo (int) $info['count']; ?></td>
					<td><?php echo esc_html( wp_date( 'Y-m-d H:i', (int) $info['expiry'] ) ); ?></td>
					<td>
						<form method="post" style="display:inline;">
							<?php wp_nonce_field( 'qingya_attack_save', 'qingya_attack_nonce' ); ?>
							<input type="hidden" name="qingya_attack_ip" value="<?php echo esc_attr( $ip ); ?>" />
							<button type="submit" name="qingya_attack_unban" value="1" class="button button-small"><?php esc_html_e( '解除', 'qingya' ); ?></button>
							<button type="submit" name="qingya_attack_perm" value="1" class="button button-small"><?php esc_html_e( '转永久黑名单', 'qingya' ); ?></button>
						</form>
					</td>
				</tr>
			<?php endforeach; ?>
			</tbody>
		</table>
		<?php if ( $bans ) : ?>
			<form method="post" style="margin-top:8px;">
				<?php wp_nonce_field( 'qingya_attack_save', 'qingya_attack_nonce' ); ?>
				<button type="submit" name="qingya_attack_clear" value="1" class="button button-link-delete"
					onclick="return confirm('<?php echo esc_js( __( '确定清空全部临时封禁？', 'qingya' ) ); ?>');"><?php esc_html_e( '清空全部临时封禁', 'qingya' ); ?></button>
			</form>
		<?php endif; ?>

		<h2 style="font-size:14px;margin-top:24px;"><?php esc_html_e( '防护设置', 'qingya' ); ?></h2>
		<form method="post" style="max-width:760px;">
			<?php wp_nonce_field( 'qingya_attack_save', 'qingya_attack_nonce' ); ?>
			<table class="form-table" role="presentation">
				<tr>
					<th scope="row"><?php esc_html_e( '启用防护', 'qingya' ); ?></th>
					<td><label><input type="checkbox" name="qingya_attack_enabled" value="1" <?php checked( 'on', $s['enabled'] ); ?> /> <?php esc_html_e( '开启异常访问防护（总开关）', 'qingya' ); ?></label></td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( '全站频率限制', 'qingya' ); ?></th>
					<td>
						<label><input type="checkbox" name="qingya_attack_rate_enabled" value="1" <?php checked( 'on', $s['rate_enabled'] ); ?> /> <?php esc_html_e( '单 IP 每分钟超过阈值自动临时封禁', 'qingya' ); ?></label>
						<p class="description">
							<?php esc_html_e( '阈值（次/分钟）', 'qingya' ); ?>：
							<input type="number" name="qingya_attack_rate_limit" min="5" max="600" value="<?php echo esc_attr( $s['rate_limit'] ); ?>" class="small-text" />
							<?php esc_html_e( '｜临时封禁时长（分钟）', 'qingya' ); ?>：
							<input type="number" name="qingya_attack_ban_minutes" min="5" max="1440" value="<?php echo esc_attr( $s['ban_minutes'] ); ?>" class="small-text" />
						</p>
						<p class="description"><?php esc_html_e( '连续 3 次触发临时封禁会自动转永久黑名单；正常读者不会达到该频率。', 'qingya' ); ?></p>
						<p class="description">
							<label><input type="checkbox" name="qingya_attack_auto_perm" value="1" <?php checked( 'on', $s['auto_perm_ban'] ); ?> /> <?php esc_html_e( '超限立即加入永久黑名单（不推荐，NAT 共享 IP 可能误伤）', 'qingya' ); ?></label>
						</p>
					</td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( '禁用 XMLRPC', 'qingya' ); ?></th>
					<td><label><input type="checkbox" name="qingya_attack_xmlrpc" value="1" <?php checked( 'on', $s['xmlrpc_block'] ); ?> /> <?php esc_html_e( '掐断 xmlrpc.php（防 pingback 放大与暴力破解；不用 Jetpack/离线发布可关）', 'qingya' ); ?></label></td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( '评论防护', 'qingya' ); ?></th>
					<td>
						<label><input type="checkbox" name="qingya_attack_comment_enabled" value="1" <?php checked( 'on', $s['comment_enabled'] ); ?> /> <?php esc_html_e( '开启评论防护', 'qingya' ); ?></label>
						<p class="description">
							<?php esc_html_e( '同 IP 每 10 分钟最多评论', 'qingya' ); ?>：
							<input type="number" name="qingya_attack_comment_rate" min="1" max="100" value="<?php echo esc_attr( $s['comment_rate'] ); ?>" class="small-text" />
							<?php esc_html_e( '条；前 N 条按默认规则发布，超出的自动转入人工审核（不拒绝）', 'qingya' ); ?>
						</p>
						<p class="description"><label><input type="checkbox" name="qingya_attack_comment_moderate" value="1" <?php checked( 'on', $s['comment_moderate'] ); ?> /> <?php esc_html_e( '评论超频后强制人工审核（管理员账户免审，直接通过）', 'qingya' ); ?></label></p>
						<p class="description"><?php esc_html_e( '自动识别：垃圾词（内置词库 + 下方自定义）、含 ≥2 个外链的评论直接标记为垃圾。', 'qingya' ); ?></p>
					</td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( '自定义垃圾词', 'qingya' ); ?></th>
					<td>
						<textarea name="qingya_attack_keywords" rows="4" class="large-text" placeholder="加微信&#10;博彩&#10;兼职"><?php echo esc_textarea( $s['keywords'] ); ?></textarea>
						<p class="description"><?php esc_html_e( '每行一个，叠加内置词库，命中即标记评论为垃圾。', 'qingya' ); ?></p>
					</td>
				</tr>
			</table>
			<?php submit_button( __( '保存设置', 'qingya' ) ); ?>
		</form>
	</div>
	<?php
}
