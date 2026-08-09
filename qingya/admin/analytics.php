<?php
/**
 * 青崖统计后台看板（菜单 + 各报表页 + 设置）。
 *
 * @package Qingya
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * 注册管理菜单。
 */
function qingya_stats_admin_menu() {
	add_menu_page(
		__( '青崖统计', 'qingya' ),
		__( '青崖统计', 'qingya' ),
		'manage_options',
		'qingya-stats',
		'qingya_stats_admin_page',
		'dashicons-chart-area',
		81
	);
}
add_action( 'admin_menu', 'qingya_stats_admin_menu' );

/**
 * 处理设置保存 / 清空数据。
 */
function qingya_stats_admin_handle_post() {
	if ( ! isset( $_POST['qingya_stats_nonce'] ) ) {
		return;
	}
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( esc_html__( '权限不足。', 'qingya' ) );
	}
	check_admin_referer( 'qingya_stats_save', 'qingya_stats_nonce' );

	// 清空数据。
	if ( isset( $_POST['qingya_stats_clear'] ) ) {
		qingya_stats_clear_all();
		wp_safe_redirect( add_query_arg( 'tab', 'settings', add_query_arg( 'qy-msg', 'cleared', wp_get_referer() ) ) );
		exit;
	}

	// 保存设置。
	$exclude = array();
	if ( ! empty( $_POST['qingya_stats_exclude_roles'] ) && is_array( $_POST['qingya_stats_exclude_roles'] ) ) {
		$valid = array( 'administrator', 'editor', 'author', 'contributor', 'subscriber' );
		foreach ( wp_unslash( $_POST['qingya_stats_exclude_roles'] ) as $role ) { // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
			$role = sanitize_key( $role );
			if ( in_array( $role, $valid, true ) ) {
				$exclude[] = $role;
			}
		}
	}
	$settings = array(
		'enabled'        => isset( $_POST['qingya_stats_enabled'] ) ? 'on' : 'off',
		'exclude_roles'  => $exclude,
		'retention_days' => isset( $_POST['qingya_stats_retention'] ) ? max( 7, min( 3650, (int) $_POST['qingya_stats_retention'] ) ) : 180,
		'respect_dnt'    => isset( $_POST['qingya_stats_dnt'] ) ? 'on' : 'off',
		'goals'          => isset( $_POST['qingya_stats_goals'] ) ? sanitize_textarea_field( wp_unslash( $_POST['qingya_stats_goals'] ) ) : '',
	);
	update_option( 'qingya_stats_settings', $settings, false );

	wp_safe_redirect( add_query_arg( 'tab', 'settings', add_query_arg( 'qy-msg', 'saved', wp_get_referer() ) ) );
	exit;
}
add_action( 'admin_init', 'qingya_stats_admin_handle_post' );

/**
 * 时间范围预置。
 *
 * @return array label => [from, to]
 */
function qingya_stats_presets() {
	$today = gmdate( 'Y-m-d' );
	return array(
		'today'     => array( __( '今日', 'qingya' ), $today, $today ),
		'yesterday' => array( __( '昨天', 'qingya' ), gmdate( 'Y-m-d', time() - DAY_IN_SECONDS ), gmdate( 'Y-m-d', time() - DAY_IN_SECONDS ) ),
		'7d'        => array( __( '近 7 天', 'qingya' ), gmdate( 'Y-m-d', time() - 6 * DAY_IN_SECONDS ), $today ),
		'30d'       => array( __( '近 30 天', 'qingya' ), gmdate( 'Y-m-d', time() - 29 * DAY_IN_SECONDS ), $today ),
		'month'     => array( __( '本月', 'qingya' ), gmdate( 'Y-m-01' ), $today ),
	);
}

/**
 * 管理页渲染。
 */
function qingya_stats_admin_page() {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( esc_html__( '权限不足。', 'qingya' ) );
	}

	$msg  = isset( $_GET['qy-msg'] ) ? sanitize_key( wp_unslash( $_GET['qy-msg'] ) ) : '';
	$tab  = isset( $_GET['tab'] ) ? sanitize_key( wp_unslash( $_GET['tab'] ) ) : 'overview';
	$tabs = array(
		'overview' => __( '概览', 'qingya' ),
		'realtime' => __( '实时', 'qingya' ),
		'pages'    => __( '热门内容', 'qingya' ),
		'referrers'=> __( '来源', 'qingya' ),
		'devices'  => __( '设备', 'qingya' ),
		'geo'      => __( '地理', 'qingya' ),
		'utms'     => __( 'UTM 活动', 'qingya' ),
		'goals'    => __( '目标', 'qingya' ),
		'settings' => __( '设置', 'qingya' ),
	);
	if ( ! isset( $tabs[ $tab ] ) ) {
		$tab = 'overview';
	}

	// 时间范围。
	$presets = qingya_stats_presets();
	$range   = isset( $_GET['range'] ) ? sanitize_key( wp_unslash( $_GET['range'] ) ) : '7d';
	if ( ! isset( $presets[ $range ] ) ) {
		$range = '7d';
	}
	$from = $presets[ $range ][1];
	$to   = $presets[ $range ][2];

	// GeoIP 可用性（决定地理页是否展示）。
	$geo_ok = function_exists( 'qingya_ai_geo_country' ) && false !== qingya_ai_geo_country( '114.114.114.114' );

	$settings = qingya_stats_get_settings();
	?>
	<div class="wrap">
		<h1><?php esc_html_e( '青崖统计', 'qingya' ); ?></h1>
		<p class="description"><?php esc_html_e( '本地隐私分析：无 Cookie、无第三方服务，数据只存本机数据库，IP 仅保存加盐哈希。', 'qingya' ); ?></p>

		<?php if ( 'saved' === $msg ) : ?>
			<div class="notice notice-success is-dismissible"><p><?php esc_html_e( '设置已保存。', 'qingya' ); ?></p></div>
		<?php elseif ( 'cleared' === $msg ) : ?>
			<div class="notice notice-success is-dismissible"><p><?php esc_html_e( '统计数据已全部清空。', 'qingya' ); ?></p></div>
		<?php endif; ?>

		<nav class="nav-tab-wrapper">
			<?php foreach ( $tabs as $key => $label ) : ?>
				<a class="nav-tab<?php echo $tab === $key ? ' nav-tab-active' : ''; ?>"
					href="<?php echo esc_url( admin_url( 'admin.php?page=qingya-stats&tab=' . $key ) ); ?>"><?php echo esc_html( $label ); ?></a>
			<?php endforeach; ?>
		</nav>

		<?php if ( 'settings' === $tab ) : ?>
			<?php qingya_stats_render_settings( $settings ); ?>
		<?php else : ?>
			<div style="margin:12px 0;">
				<?php foreach ( $presets as $key => $p ) : ?>
					<a class="button<?php echo $range === $key ? ' button-primary' : ''; ?>"
						href="<?php echo esc_url( admin_url( 'admin.php?page=qingya-stats&tab=' . $tab . '&range=' . $key ) ); ?>"
						style="margin-right:4px;"><?php echo esc_html( $p[0] ); ?></a>
				<?php endforeach; ?>
			</div>

			<?php
			switch ( $tab ) {
				case 'realtime':
					qingya_stats_render_realtime();
					break;
				case 'pages':
					qingya_stats_render_list( __( '热门内容', 'qingya' ), qingya_stats_pages( $from, $to, 50 ), 'url' );
					break;
				case 'referrers':
					qingya_stats_render_list( __( '来源', 'qingya' ), qingya_stats_referrers( $from, $to, 50 ), 'referrer' );
					break;
				case 'devices':
					qingya_stats_render_devices( $from, $to );
					break;
				case 'geo':
					qingya_stats_render_geo( $from, $to, $geo_ok );
					break;
				case 'utms':
					qingya_stats_render_utms( $from, $to );
					break;
				case 'goals':
					qingya_stats_render_goals( $from, $to );
					break;
				default:
					qingya_stats_render_overview( $from, $to, $range, $geo_ok );
			}
			?>
		<?php endif; ?>
	</div>
	<?php
}

/**
 * 概览页。
 *
 * @param string $from   Y-m-d。
 * @param string $to     Y-m-d。
 * @param string $range  预置键。
 * @param bool   $geo_ok GeoIP 是否可用。
 */
function qingya_stats_render_overview( $from, $to, $range, $geo_ok ) {
	$totals = qingya_stats_totals( $from, $to );
	$trend  = qingya_stats_trend( $from, $to );
	$pages  = qingya_stats_pages( $from, $to, 10 );
	$refs   = qingya_stats_referrers( $from, $to, 10 );
	?>
	<div style="display:flex;gap:16px;flex-wrap:wrap;margin:16px 0;">
		<div style="flex:1;min-width:180px;background:#fff;border:1px solid #dcdcde;border-radius:6px;padding:16px;">
			<div style="color:#646970;font-size:13px;"><?php esc_html_e( '浏览量（PV）', 'qingya' ); ?></div>
			<div style="font-size:30px;font-weight:600;color:#1d2327;"><?php echo number_format_i18n( $totals['pageviews'] ); ?></div>
		</div>
		<div style="flex:1;min-width:180px;background:#fff;border:1px solid #dcdcde;border-radius:6px;padding:16px;">
			<div style="color:#646970;font-size:13px;"><?php esc_html_e( '独立访客（UV）', 'qingya' ); ?></div>
			<div style="font-size:30px;font-weight:600;color:#1d2327;"><?php echo number_format_i18n( $totals['visitors'] ); ?></div>
		</div>
		<div style="flex:1;min-width:180px;background:#fff;border:1px solid #dcdcde;border-radius:6px;padding:16px;">
			<div style="color:#646970;font-size:13px;"><?php esc_html_e( '人均浏览', 'qingya' ); ?></div>
			<div style="font-size:30px;font-weight:600;color:#1d2327;"><?php echo $totals['visitors'] ? round( $totals['pageviews'] / $totals['visitors'], 1 ) : 0; ?></div>
		</div>
		<?php if ( $geo_ok ) : ?>
			<div style="flex:1;min-width:180px;background:#fff;border:1px solid #dcdcde;border-radius:6px;padding:16px;">
				<div style="color:#646970;font-size:13px;"><?php esc_html_e( '主要来源国家', 'qingya' ); ?></div>
				<div style="font-size:24px;font-weight:600;color:#1d2327;">
					<?php
					$countries = qingya_stats_countries( $from, $to );
					echo $countries ? esc_html( qingya_stats_country_name( $countries[0]['country'] ) ) : '—';
					?>
				</div>
			</div>
		<?php endif; ?>
	</div>

	<div style="background:#fff;border:1px solid #dcdcde;border-radius:6px;padding:16px;margin-bottom:16px;">
		<h2 style="margin-top:0;font-size:14px;"><?php esc_html_e( '访问趋势', 'qingya' ); ?></h2>
		<?php qingya_stats_render_trend_chart( $trend ); ?>
	</div>

	<div style="display:flex;gap:16px;flex-wrap:wrap;">
		<div style="flex:1;min-width:320px;background:#fff;border:1px solid #dcdcde;border-radius:6px;padding:16px;">
			<h2 style="margin-top:0;font-size:14px;"><?php esc_html_e( '热门内容 TOP 10', 'qingya' ); ?></h2>
			<?php qingya_stats_render_list( '', $pages, 'url', true ); ?>
		</div>
		<div style="flex:1;min-width:320px;background:#fff;border:1px solid #dcdcde;border-radius:6px;padding:16px;">
			<h2 style="margin-top:0;font-size:14px;"><?php esc_html_e( '来源 TOP 10', 'qingya' ); ?></h2>
			<?php qingya_stats_render_list( '', $refs, 'referrer', true ); ?>
		</div>
	</div>
	<?php
}

/**
 * 趋势图表（纯 CSS 柱状，双系列）。
 *
 * @param array $trend 趋势数据。
 */
function qingya_stats_render_trend_chart( $trend ) {
	if ( ! $trend ) {
		echo '<p class="description">' . esc_html__( '暂无数据。', 'qingya' ) . '</p>';
		return;
	}
	$max_pv = 1;
	$max_uv = 1;
	foreach ( $trend as $d ) {
		$max_pv = max( $max_pv, $d['pageviews'] );
		$max_uv = max( $max_uv, $d['visitors'] );
	}
	$n = count( $trend );
	?>
	<div style="display:flex;align-items:flex-end;gap:2px;height:160px;border-bottom:1px solid #dcdcde;">
		<?php foreach ( $trend as $d ) : ?>
			<div style="flex:1;display:flex;align-items:flex-end;gap:1px;height:100%;" title="<?php echo esc_attr( $d['date'] . ' PV ' . $d['pageviews'] . ' / UV ' . $d['visitors'] ); ?>">
				<div style="flex:1;background:#3b82f6;border-radius:1px 1px 0 0;height:<?php echo esc_attr( max( 1, round( $d['pageviews'] / $max_pv * 100 ) ) ); ?>%;"></div>
				<div style="flex:1;background:#f59e0b;border-radius:1px 1px 0 0;height:<?php echo esc_attr( max( 1, round( $d['visitors'] / $max_uv * 100 ) ) ); ?>%;"></div>
			</div>
		<?php endforeach; ?>
	</div>
	<div style="display:flex;gap:2px;margin-top:4px;">
		<?php foreach ( $trend as $d ) : ?>
			<div style="flex:1;text-align:center;font-size:10px;color:#8c8f94;white-space:nowrap;overflow:hidden;"><?php echo esc_html( ( 0 === (int) substr( $d['date'], 8, 2 ) % 5 || $n <= 7 ) ? substr( $d['date'], 5 ) : '' ); ?></div>
		<?php endforeach; ?>
	</div>
	<div style="margin-top:8px;font-size:12px;color:#646970;">
		<span style="display:inline-block;width:10px;height:10px;background:#3b82f6;margin-right:4px;"></span><?php esc_html_e( '浏览量', 'qingya' ); ?>
		<span style="display:inline-block;width:10px;height:10px;background:#f59e0b;margin-left:14px;margin-right:4px;"></span><?php esc_html_e( '访客', 'qingya' ); ?>
	</div>
	<?php
}

/**
 * 实时页。
 */
function qingya_stats_render_realtime() {
	$data = qingya_stats_online( 10 );
	?>
	<div style="display:flex;gap:16px;flex-wrap:wrap;margin:16px 0;">
		<div style="flex:1;min-width:180px;background:#fff;border:1px solid #dcdcde;border-radius:6px;padding:16px;">
			<div style="color:#646970;font-size:13px;"><?php esc_html_e( '最近 10 分钟在线访客', 'qingya' ); ?></div>
			<div style="font-size:30px;font-weight:600;color:#1d2327;"><?php echo (int) $data['online']; ?></div>
		</div>
	</div>
	<div style="background:#fff;border:1px solid #dcdcde;border-radius:6px;padding:16px;">
		<h2 style="margin-top:0;font-size:14px;"><?php esc_html_e( '最近浏览明细', 'qingya' ); ?></h2>
		<table class="widefat striped" style="margin-top:8px;">
			<thead><tr><th><?php esc_html_e( '时间', 'qingya' ); ?></th><th><?php esc_html_e( '页面', 'qingya' ); ?></th><th><?php esc_html_e( '设备', 'qingya' ); ?></th><th><?php esc_html_e( '国家', 'qingya' ); ?></th></tr></thead>
			<tbody>
			<?php if ( ! $data['rows'] ) : ?>
				<tr><td colspan="4"><?php esc_html_e( '暂无实时访问。', 'qingya' ); ?></td></tr>
			<?php endif; ?>
			<?php foreach ( $data['rows'] as $row ) : ?>
				<tr>
					<td><?php echo esc_html( mysql2date( 'H:i:s', $row['ts'] ) ); ?></td>
					<td><a href="<?php echo esc_url( $row['url'] ); ?>" target="_blank" rel="noopener"><?php echo esc_html( $row['title'] ? $row['title'] : $row['url'] ); ?></a></td>
					<td><?php echo esc_html( $row['device'] ); ?></td>
					<td><?php echo $row['country'] ? esc_html( qingya_stats_country_name( $row['country'] ) ) : '—'; ?></td>
				</tr>
			<?php endforeach; ?>
			</tbody>
		</table>
	</div>
	<?php
}

/**
 * 通用排行列表。
 *
 * @param string $title   标题（空=不显示）。
 * @param array  $rows    数据。
 * @param string $url_key URL 字段名。
 * @param bool   $compact 紧凑模式。
 */
function qingya_stats_render_list( $title, $rows, $url_key, $compact = false ) {
	if ( $title ) {
		echo '<h2 style="margin-top:0;font-size:14px;">' . esc_html( $title ) . '</h2>';
	}
	if ( ! $rows ) {
		echo '<p class="description">' . esc_html__( '暂无数据。', 'qingya' ) . '</p>';
		return;
	}
	$max = 1;
	foreach ( $rows as $row ) {
		$max = max( $max, (int) $row['views'] );
	}
	echo '<table class="widefat striped" style="margin-top:8px;">';
	echo '<thead><tr><th style="width:60%;">' . esc_html__( '项目', 'qingya' ) . '</th><th style="width:40%;">' . esc_html__( '浏览量', 'qingya' ) . '</th></tr></thead>';
	echo '<tbody>';
	foreach ( $rows as $row ) {
		$label = 'referrer' === $url_key && ! empty( $row['domain'] ) ? $row['domain'] : ( ! empty( $row['title'] ) ? $row['title'] : $row[ $url_key ] );
		if ( ! $label ) {
			$label = '—';
		}
		$link = 'url' === $url_key && ! empty( $row['url'] ) ? $row['url'] : ( ! empty( $row['referrer'] ) ? $row['referrer'] : '' );
		$pct  = round( (int) $row['views'] / $max * 100, 1 );
		echo '<tr>';
		echo '<td>';
		if ( $link ) {
			echo '<a href="' . esc_url( $link ) . '" target="_blank" rel="noopener">' . esc_html( $label ) . '</a>';
		} else {
			echo esc_html( $label );
		}
		echo '</td>';
		echo '<td>' . number_format_i18n( (int) $row['views'] ) . ' <span style="color:#8c8f94;font-size:11px;">(' . esc_html( $pct ) . '%)</span></td>';
		echo '</tr>';
	}
	echo '</tbody></table>';
}

/**
 * 设备页。
 *
 * @param string $from Y-m-d。
 * @param string $to   Y-m-d。
 */
function qingya_stats_render_devices( $from, $to ) {
	$devices  = qingya_stats_devices( $from, $to );
	$browsers = qingya_stats_browsers( $from, $to );
	$labels   = array(
		'desktop' => __( '桌面端', 'qingya' ),
		'tablet'  => __( '平板', 'qingya' ),
		'mobile'  => __( '移动端', 'qingya' ),
	);
	?>
	<div style="display:flex;gap:16px;flex-wrap:wrap;">
		<div style="flex:1;min-width:320px;background:#fff;border:1px solid #dcdcde;border-radius:6px;padding:16px;">
			<h2 style="margin-top:0;font-size:14px;"><?php esc_html_e( '设备类型', 'qingya' ); ?></h2>
			<?php qingya_stats_render_bar_rows( $devices, 'device', $labels ); ?>
		</div>
		<div style="flex:1;min-width:320px;background:#fff;border:1px solid #dcdcde;border-radius:6px;padding:16px;">
			<h2 style="margin-top:0;font-size:14px;"><?php esc_html_e( '浏览器', 'qingya' ); ?></h2>
			<?php qingya_stats_render_bar_rows( $browsers, 'browser' ); ?>
		</div>
	</div>
	<?php
}

/**
 * 比例条列表（名称 + 条 + 数值）。
 *
 * @param array  $rows   数据。
 * @param string $name_key 名称字段。
 * @param array  $labels 名称映射。
 */
function qingya_stats_render_bar_rows( $rows, $name_key, $labels = array() ) {
	if ( ! $rows ) {
		echo '<p class="description">' . esc_html__( '暂无数据。', 'qingya' ) . '</p>';
		return;
	}
	$max = 1;
	foreach ( $rows as $row ) {
		$max = max( $max, (int) $row['views'] );
	}
	foreach ( $rows as $row ) {
		$name = isset( $labels[ $row[ $name_key ] ] ) ? $labels[ $row[ $name_key ] ] : $row[ $name_key ];
		$pct  = round( (int) $row['views'] / $max * 100, 1 );
		echo '<div style="margin-bottom:10px;">';
		echo '<div style="display:flex;justify-content:space-between;font-size:13px;margin-bottom:3px;">';
		echo '<span>' . esc_html( $name ) . '</span><span>' . number_format_i18n( (int) $row['views'] ) . '</span>';
		echo '</div>';
		echo '<div style="background:#f0f0f1;border-radius:3px;height:14px;">';
		echo '<div style="background:#3b82f6;border-radius:3px;height:14px;width:' . esc_attr( $pct ) . '%;"></div>';
		echo '</div>';
		echo '</div>';
	}
}

/**
 * 地理页。
 *
 * @param string $from   Y-m-d。
 * @param string $to     Y-m-d。
 * @param bool   $geo_ok GeoIP 可用。
 */
function qingya_stats_render_geo( $from, $to, $geo_ok ) {
	if ( ! $geo_ok ) {
		echo '<div class="notice notice-info"><p>' . esc_html__( '未检测到 MaxMind GeoLite2-Country 数据库（需上传到 wp-content/uploads/GeoLite2-Country.mmdb）。可用后此处显示访客国家分布。', 'qingya' ) . '</p></div>';
		return;
	}
	$countries = qingya_stats_countries( $from, $to );
	$rows = array();
	foreach ( $countries as $c ) {
		$rows[] = array(
			'name'  => qingya_stats_country_name( $c['country'] ),
			'views' => $c['views'],
		);
	}
	?>
	<div style="background:#fff;border:1px solid #dcdcde;border-radius:6px;padding:16px;">
		<h2 style="margin-top:0;font-size:14px;"><?php esc_html_e( '访客国家分布', 'qingya' ); ?></h2>
		<?php qingya_stats_render_bar_rows( $rows, 'name' ); ?>
	</div>
	<?php
}

/**
 * UTM 活动页。
 *
 * @param string $from Y-m-d。
 * @param string $to   Y-m-d。
 */
function qingya_stats_render_utms( $from, $to ) {
	$utms = qingya_stats_utms( $from, $to );
	?>
	<div style="background:#fff;border:1px solid #dcdcde;border-radius:6px;padding:16px;">
		<h2 style="margin-top:0;font-size:14px;"><?php esc_html_e( 'UTM 活动（来源/媒介/活动 → 浏览量）', 'qingya' ); ?></h2>
		<table class="widefat striped" style="margin-top:8px;">
			<thead><tr><th><?php esc_html_e( '来源', 'qingya' ); ?></th><th><?php esc_html_e( '媒介', 'qingya' ); ?></th><th><?php esc_html_e( '活动', 'qingya' ); ?></th><th><?php esc_html_e( '浏览量', 'qingya' ); ?></th></tr></thead>
			<tbody>
			<?php if ( ! $utms ) : ?>
				<tr><td colspan="4"><?php esc_html_e( '暂无 UTM 数据（带 utm_source 参数访问即自动记录）。', 'qingya' ); ?></td></tr>
			<?php endif; ?>
			<?php foreach ( $utms as $u ) : ?>
				<tr>
					<td><?php echo esc_html( $u['utm_source'] ); ?></td>
					<td><?php echo esc_html( $u['utm_medium'] ); ?></td>
					<td><?php echo esc_html( $u['utm_campaign'] ); ?></td>
					<td><?php echo number_format_i18n( (int) $u['views'] ); ?></td>
				</tr>
			<?php endforeach; ?>
			</tbody>
		</table>
	</div>
	<?php
}

/**
 * 目标页。
 *
 * @param string $from Y-m-d。
 * @param string $to   Y-m-d。
 */
function qingya_stats_render_goals( $from, $to ) {
	$goals = qingya_stats_goals( $from, $to );
	?>
	<div style="background:#fff;border:1px solid #dcdcde;border-radius:6px;padding:16px;">
		<h2 style="margin-top:0;font-size:14px;"><?php esc_html_e( '目标转化（URL 包含词命中）', 'qingya' ); ?></h2>
		<?php if ( ! qingya_stats_parse_goals() ) : ?>
			<p class="description"><?php esc_html_e( '尚未配置目标。到「设置」页填写目标（每行：标签|URL包含词，如 联系页|/contact）。', 'qingya' ); ?></p>
		<?php elseif ( ! $goals ) : ?>
			<p class="description"><?php esc_html_e( '所选时间段内无转化。', 'qingya' ); ?></p>
		<?php else : ?>
			<table class="widefat striped" style="margin-top:8px;">
				<thead><tr><th><?php esc_html_e( '目标', 'qingya' ); ?></th><th><?php esc_html_e( 'URL 包含', 'qingya' ); ?></th><th><?php esc_html_e( '达成次数', 'qingya' ); ?></th></tr></thead>
				<tbody>
				<?php foreach ( $goals as $g ) : ?>
					<tr>
						<td><?php echo esc_html( $g['label'] ); ?></td>
						<td><code><?php echo esc_html( $g['fragment'] ); ?></code></td>
						<td><?php echo number_format_i18n( $g['views'] ); ?></td>
					</tr>
				<?php endforeach; ?>
				</tbody>
			</table>
		<?php endif; ?>
	</div>
	<?php
}

/**
 * 设置页。
 *
 * @param array $settings 设置。
 */
function qingya_stats_render_settings( $settings ) {
	$rows = qingya_stats_row_count();
	?>
	<form method="post" style="max-width:720px;">
		<?php wp_nonce_field( 'qingya_stats_save', 'qingya_stats_nonce' ); ?>
		<table class="form-table" role="presentation">
			<tr>
				<th scope="row"><?php esc_html_e( '启用统计', 'qingya' ); ?></th>
				<td>
					<label><input type="checkbox" name="qingya_stats_enabled" value="1" <?php checked( 'on', $settings['enabled'] ); ?> />
					<?php esc_html_e( '前台加载追踪脚本并记录访问', 'qingya' ); ?></label>
				</td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( '不统计的登录角色', 'qingya' ); ?></th>
				<td>
					<?php
					$all_roles = array(
						'administrator' => __( '管理员', 'qingya' ),
						'editor'        => __( '编辑', 'qingya' ),
						'author'        => __( '作者', 'qingya' ),
						'contributor'   => __( '投稿者', 'qingya' ),
						'subscriber'    => __( '订阅者', 'qingya' ),
					);
					foreach ( $all_roles as $key => $label ) :
						?>
						<label style="display:block;margin-bottom:4px;">
							<input type="checkbox" name="qingya_stats_exclude_roles[]" value="<?php echo esc_attr( $key ); ?>"
								<?php checked( in_array( $key, (array) $settings['exclude_roles'], true ) ); ?> />
							<?php echo esc_html( $label ); ?>
						</label>
					<?php endforeach; ?>
					<p class="description"><?php esc_html_e( '默认不统计管理员（防止自己访问污染数据）。', 'qingya' ); ?></p>
				</td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( '数据保留天数', 'qingya' ); ?></th>
				<td>
					<input type="number" name="qingya_stats_retention" min="7" max="3650" value="<?php echo esc_attr( $settings['retention_days'] ); ?>" class="small-text" />
					<p class="description"><?php esc_html_e( '超过保留期的明细自动清理（7~3650 天，默认 180）。', 'qingya' ); ?></p>
				</td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( '尊重 Do Not Track', 'qingya' ); ?></th>
				<td>
					<label><input type="checkbox" name="qingya_stats_dnt" value="1" <?php checked( 'on', $settings['respect_dnt'] ); ?> />
					<?php esc_html_e( '浏览器开启「请勿跟踪」时不记录', 'qingya' ); ?></label>
				</td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( '目标配置', 'qingya' ); ?></th>
				<td>
					<textarea name="qingya_stats_goals" rows="5" class="large-text" placeholder="联系页|/contact&#10;注册成功|/register?done=1"><?php echo esc_textarea( $settings['goals'] ); ?></textarea>
					<p class="description"><?php esc_html_e( '每行一个：标签|URL包含词（URL 里包含该词即算达成，用于转化统计）。', 'qingya' ); ?></p>
				</td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( '数据与隐私', 'qingya' ); ?></th>
				<td>
					<p class="description" style="margin:0 0 8px;">
						<?php esc_html_e( '当前库内共', 'qingya' ); ?> <strong><?php echo number_format_i18n( $rows ); ?></strong> <?php esc_html_e( '条浏览记录。所有数据仅存本机数据库：无 Cookie、无第三方服务、IP 只保留加盐哈希，原始 IP 不落库。', 'qingya' ); ?>
					</p>
					<button type="submit" name="qingya_stats_clear" value="1" class="button button-link-delete"
						onclick="return confirm('<?php echo esc_js( __( '确定清空全部统计数据？此操作不可撤销。', 'qingya' ) ); ?>');">
						<?php esc_html_e( '清空全部统计数据', 'qingya' ); ?>
					</button>
				</td>
			</tr>
		</table>
		<?php submit_button( __( '保存设置', 'qingya' ) ); ?>
	</form>
	<?php
}
