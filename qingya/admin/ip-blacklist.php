<?php
/**
 * IP 黑名单管理页（后台菜单 + 表单 + 日志查看）。
 *
 * @package Qingya
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * 注册管理菜单。
 */
function qingya_ip_admin_menu() {
	add_menu_page(
		__( 'IP 黑名单', 'qingya' ),
		__( 'IP 黑名单', 'qingya' ),
		'manage_options',
		'qingya-ip-blacklist',
		'qingya_ip_admin_page',
		'dashicons-shield',
		80
	);
}
add_action( 'admin_menu', 'qingya_ip_admin_menu' );

/**
 * 处理表单提交（保存配置 / 清空日志）。
 */
function qingya_ip_admin_handle_post() {
	if ( ! isset( $_POST['qingya_ip_nonce'] ) ) {
		return;
	}
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( esc_html__( '权限不足。', 'qingya' ) );
	}
	check_admin_referer( 'qingya_ip_save', 'qingya_ip_nonce' );

	// 清空日志。
	if ( isset( $_POST['qingya_ip_clear_logs'] ) ) {
		global $wpdb;
		$table = $wpdb->prefix . QINGYA_IP_LOG_TABLE;
		$wpdb->query( "TRUNCATE TABLE {$table}" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL
		wp_safe_redirect( add_query_arg( 'qy-msg', 'cleared', wp_get_referer() ) );
		exit;
	}

	// 保存配置。
	$settings = array(
		'enabled'       => isset( $_POST['qingya_ip_enabled'] ) ? 'on' : 'off',
		'scope'         => isset( $_POST['qingya_ip_scope'] ) && 'all' === $_POST['qingya_ip_scope'] ? 'all' : 'front',
		'strategy'      => isset( $_POST['qingya_ip_strategy'] ) ? sanitize_key( wp_unslash( $_POST['qingya_ip_strategy'] ) ) : '403',
		'page_msg'      => isset( $_POST['qingya_ip_page_msg'] ) ? sanitize_text_field( wp_unslash( $_POST['qingya_ip_page_msg'] ) ) : '',
		'url'           => isset( $_POST['qingya_ip_url'] ) ? esc_url_raw( wp_unslash( $_POST['qingya_ip_url'] ) ) : '',
		'ips'           => isset( $_POST['qingya_ip_ips'] ) ? qingya_ip_normalize_list( wp_unslash( $_POST['qingya_ip_ips'] ) ) : array(),
		'whitelist'     => isset( $_POST['qingya_ip_whitelist'] ) ? qingya_ip_normalize_list( wp_unslash( $_POST['qingya_ip_whitelist'] ) ) : array(),
		'spider_bypass' => isset( $_POST['qingya_ip_spider_bypass'] ) ? 'on' : 'off',
		'log_enabled'   => isset( $_POST['qingya_ip_log_enabled'] ) ? 'on' : 'off',
	);
	qingya_ip_save_settings( $settings );

	wp_safe_redirect( add_query_arg( 'qy-msg', 'saved', wp_get_referer() ) );
	exit;
}
add_action( 'admin_init', 'qingya_ip_admin_handle_post' );

/**
 * 管理页渲染。
 */
function qingya_ip_admin_page() {
	$settings = qingya_ip_get_settings();
	$msg      = isset( $_GET['qy-msg'] ) ? sanitize_key( wp_unslash( $_GET['qy-msg'] ) ) : '';

	global $wpdb;
	$table = $wpdb->prefix . QINGYA_IP_LOG_TABLE;
	$logs  = $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		"SELECT * FROM {$table} ORDER BY created_at DESC LIMIT 100", // phpcs:ignore WordPress.DB.PreparedSQL
		ARRAY_A
	);
	?>
	<div class="wrap">
		<h1><?php esc_html_e( 'IP 黑名单管理', 'qingya' ); ?></h1>

		<?php if ( 'saved' === $msg ) : ?>
			<div class="notice notice-success is-dismissible"><p><?php esc_html_e( '设置已保存。', 'qingya' ); ?></p></div>
		<?php elseif ( 'cleared' === $msg ) : ?>
			<div class="notice notice-success is-dismissible"><p><?php esc_html_e( '日志已清空。', 'qingya' ); ?></p></div>
		<?php endif; ?>

		<form method="post" action="">
			<?php wp_nonce_field( 'qingya_ip_save', 'qingya_ip_nonce' ); ?>

			<table class="form-table" role="presentation">
				<tr>
					<th scope="row"><?php esc_html_e( '启用黑名单', 'qingya' ); ?></th>
					<td>
						<label><input type="checkbox" name="qingya_ip_enabled" value="1" <?php checked( 'on', $settings['enabled'] ); ?>> <?php esc_html_e( '开启拦截（可随时临时关闭）', 'qingya' ); ?></label>
					</td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( '拦截范围', 'qingya' ); ?></th>
					<td>
						<label><input type="radio" name="qingya_ip_scope" value="front" <?php checked( 'front', $settings['scope'] ); ?>> <?php esc_html_e( '仅前台（不影响后台登录）', 'qingya' ); ?></label><br>
						<label><input type="radio" name="qingya_ip_scope" value="all" <?php checked( 'all', $settings['scope'] ); ?>> <?php esc_html_e( '全部（含后台与登录页）', 'qingya' ); ?></label>
						<p class="description"><?php esc_html_e( '选择「全部」时请务必先在白名单中加入自己的 IP，避免误锁。', 'qingya' ); ?></p>
					</td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( '拦截策略', 'qingya' ); ?></th>
					<td>
						<label><input type="radio" name="qingya_ip_strategy" value="403" <?php checked( '403', $settings['strategy'] ); ?>> <?php esc_html_e( '403 禁止访问', 'qingya' ); ?></label><br>
						<label><input type="radio" name="qingya_ip_strategy" value="page" <?php checked( 'page', $settings['strategy'] ); ?>> <?php esc_html_e( '跳转提示页', 'qingya' ); ?></label><br>
						<label><input type="radio" name="qingya_ip_strategy" value="url" <?php checked( 'url', $settings['strategy'] ); ?>> <?php esc_html_e( '跳转指定 URL', 'qingya' ); ?></label>
					</td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( '提示文案', 'qingya' ); ?></th>
					<td>
						<input type="text" class="regular-text" name="qingya_ip_page_msg" value="<?php echo esc_attr( $settings['page_msg'] ); ?>">
						<p class="description"><?php esc_html_e( '「跳转提示页」策略下显示的文字。', 'qingya' ); ?></p>
					</td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( '跳转 URL', 'qingya' ); ?></th>
					<td>
						<input type="url" class="regular-text" name="qingya_ip_url" value="<?php echo esc_attr( $settings['url'] ); ?>" placeholder="https://example.com">
					</td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( '黑名单 IP 列表', 'qingya' ); ?></th>
					<td>
						<textarea name="qingya_ip_ips" rows="6" class="large-text code" placeholder="192.168.1.5&#10;192.168.1.*&#10;123.45.67.0/24"><?php echo esc_textarea( implode( "\n", $settings['ips'] ) ); ?></textarea>
						<p class="description"><?php esc_html_e( '每行一条，支持：单个 IP、IP 段（192.168.1.*）、CIDR（123.45.67.0/24）。', 'qingya' ); ?></p>
					</td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( '白名单 IP 列表', 'qingya' ); ?></th>
					<td>
						<textarea name="qingya_ip_whitelist" rows="4" class="large-text code" placeholder="管理员办公 IP 等"><?php echo esc_textarea( implode( "\n", $settings['whitelist'] ) ); ?></textarea>
						<p class="description"><?php esc_html_e( '白名单优先于黑名单，命中即放行。格式同上。', 'qingya' ); ?></p>
					</td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( '搜索引擎蜘蛛', 'qingya' ); ?></th>
					<td>
						<label><input type="checkbox" name="qingya_ip_spider_bypass" value="1" <?php checked( 'on', $settings['spider_bypass'] ); ?>> <?php esc_html_e( '自动放行百度 / 谷歌等搜索引擎蜘蛛', 'qingya' ); ?></label>
					</td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( '访问日志', 'qingya' ); ?></th>
					<td>
						<label><input type="checkbox" name="qingya_ip_log_enabled" value="1" <?php checked( 'on', $settings['log_enabled'] ); ?>> <?php esc_html_e( '记录被拦截 IP 的访问信息', 'qingya' ); ?></label>
					</td>
				</tr>
			</table>

			<?php submit_button( __( '保存设置', 'qingya' ) ); ?>
		</form>

		<hr>

		<h2><?php esc_html_e( '拦截日志（最近 100 条）', 'qingya' ); ?></h2>
		<form method="post" action="" onsubmit="return confirm('<?php echo esc_js( __( '确定清空全部日志？', 'qingya' ) ); ?>');">
			<?php wp_nonce_field( 'qingya_ip_save', 'qingya_ip_nonce' ); ?>
			<button type="submit" name="qingya_ip_clear_logs" value="1" class="button button-secondary"><?php esc_html_e( '一键清空日志', 'qingya' ); ?></button>
		</form>
		<table class="widefat striped" style="margin-top:12px;">
			<thead>
				<tr>
					<th><?php esc_html_e( '时间', 'qingya' ); ?></th>
					<th><?php esc_html_e( 'IP', 'qingya' ); ?></th>
					<th><?php esc_html_e( '访问地址', 'qingya' ); ?></th>
					<th><?php esc_html_e( 'UA', 'qingya' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php if ( empty( $logs ) ) : ?>
					<tr><td colspan="4"><?php esc_html_e( '暂无日志。', 'qingya' ); ?></td></tr>
				<?php else : ?>
					<?php foreach ( $logs as $log ) : ?>
						<tr>
							<td><?php echo esc_html( $log['created_at'] ); ?></td>
							<td><code><?php echo esc_html( $log['ip'] ); ?></code></td>
							<td><?php echo esc_html( $log['url'] ); ?></td>
							<td title="<?php echo esc_attr( $log['ua'] ); ?>"><?php echo esc_html( mb_substr( $log['ua'], 0, 60 ) ); ?></td>
						</tr>
					<?php endforeach; ?>
				<?php endif; ?>
			</tbody>
		</table>
	</div>
	<?php
}
