<?php
/**
 * 前端交互 AJAX 端点：
 * - 文章浏览量统计（Cookie 24h 去重）
 * - 点赞（IP + Cookie 去重，post meta 计数）
 * - 收藏（登录用户存 user meta，游客存 Cookie）
 *
 * 统一 nonce 校验 + 权限/频率限制。
 *
 * @package Qingya
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * 浏览量统计（页面渲染时调用，非 AJAX）：
 * 通过 Cookie 记录 24 小时内已计数的文章，避免刷新刷量。
 */
function qingya_track_views() {
	if ( ! is_singular( 'post' ) ) {
		return;
	}
	$post_id = get_the_ID();
	if ( ! $post_id ) {
		return;
	}

	// 已计数则跳过。
	$viewed = isset( $_COOKIE['qingya_viewed'] ) ? array_map( 'absint', explode( ',', sanitize_text_field( wp_unslash( $_COOKIE['qingya_viewed'] ) ) ) ) : array();
	if ( in_array( $post_id, $viewed, true ) ) {
		return;
	}

	// 浏览量 +1。
	$views = (int) get_post_meta( $post_id, '_qingya_views', true ) + 1;
	update_post_meta( $post_id, '_qingya_views', $views );

	// 写入 Cookie（24 小时）。
	$viewed[] = $post_id;
	$viewed   = array_slice( array_unique( $viewed ), -50 ); // 最多记录 50 篇。
	setcookie( 'qingya_viewed', implode( ',', $viewed ), time() + DAY_IN_SECONDS, COOKIEPATH, COOKIE_DOMAIN, is_ssl(), true );
}
add_action( 'wp_head', 'qingya_track_views', 5 );

/**
 * AJAX 响应辅助。
 *
 * @param bool   $ok   是否成功。
 * @param string $msg  消息。
 * @param array  $data 附加数据。
 */
function qingya_ajax_response( $ok, $msg = '', $data = array() ) {
	wp_send_json( array_merge( array(
		'ok'  => $ok,
		'msg' => $msg,
	), $data ) );
}

/**
 * 点赞处理。
 */
function qingya_ajax_like() {
	if ( ! check_ajax_referer( 'qingya_ajax', 'nonce', false ) ) {
		qingya_ajax_response( false, __( '请求校验失败，请刷新页面重试', 'qingya' ) );
	}

	$post_id = isset( $_POST['post_id'] ) ? absint( $_POST['post_id'] ) : 0;
	if ( ! $post_id || 'post' !== get_post_type( $post_id ) ) {
		qingya_ajax_response( false, __( '参数错误', 'qingya' ) );
	}

	// IP + 浏览器指纹去重。
	$key  = 'qy_like_' . md5( qingya_client_ip() . ( isset( $_COOKIE['qingya_uid'] ) ? sanitize_text_field( wp_unslash( $_COOKIE['qingya_uid'] ) ) : '' ) . '_' . $post_id );
	$done = get_transient( $key );
	if ( $done ) {
		qingya_ajax_response( false, __( '已经点过赞了', 'qingya' ), array( 'count' => (int) get_post_meta( $post_id, '_qingya_likes', true ) ) );
	}

	$likes = (int) get_post_meta( $post_id, '_qingya_likes', true ) + 1;
	update_post_meta( $post_id, '_qingya_likes', $likes );
	set_transient( $key, 1, HOUR_IN_SECONDS );

	qingya_ajax_response( true, __( '点赞成功', 'qingya' ), array( 'count' => $likes ) );
}
add_action( 'wp_ajax_qingya_like', 'qingya_ajax_like' );
add_action( 'wp_ajax_nopriv_qingya_like', 'qingya_ajax_like' );

/**
 * 收藏处理：登录用户存 user meta；游客存 Cookie 标记。
 */
function qingya_ajax_favorite() {
	if ( ! check_ajax_referer( 'qingya_ajax', 'nonce', false ) ) {
		qingya_ajax_response( false, __( '请求校验失败，请刷新页面重试', 'qingya' ) );
	}

	$post_id = isset( $_POST['post_id'] ) ? absint( $_POST['post_id'] ) : 0;
	if ( ! $post_id ) {
		qingya_ajax_response( false, __( '参数错误', 'qingya' ) );
	}

	$is_faved = false;
	if ( is_user_logged_in() ) {
		$user_id  = get_current_user_id();
		$favorites = get_user_meta( $user_id, '_qingya_favorites', true );
		$favorites = is_array( $favorites ) ? $favorites : array();
		if ( in_array( $post_id, $favorites, true ) ) {
			// 取消收藏。
			$favorites = array_values( array_diff( $favorites, array( $post_id ) ) );
			update_user_meta( $user_id, '_qingya_favorites', $favorites );
		} else {
			$favorites[] = $post_id;
			update_user_meta( $user_id, '_qingya_favorites', $favorites );
			$is_faved = true;
		}
		qingya_ajax_response( true, $is_faved ? __( '收藏成功', 'qingya' ) : __( '已取消收藏', 'qingya' ), array( 'faved' => $is_faved ) );
	}

	// 游客：Cookie 标记。
	$faved_list = isset( $_COOKIE['qingya_favs'] ) ? array_map( 'absint', explode( ',', sanitize_text_field( wp_unslash( $_COOKIE['qingya_favs'] ) ) ) ) : array();
	if ( in_array( $post_id, $faved_list, true ) ) {
		$faved_list = array_values( array_diff( $faved_list, array( $post_id ) ) );
		$is_faved   = false;
	} else {
		$faved_list[] = $post_id;
		$is_faved     = true;
	}
	setcookie( 'qingya_favs', implode( ',', array_slice( array_unique( $faved_list ), -100 ) ), time() + YEAR_IN_SECONDS, COOKIEPATH, COOKIE_DOMAIN, is_ssl(), true );

	qingya_ajax_response( true, $is_faved ? __( '收藏成功', 'qingya' ) : __( '已取消收藏', 'qingya' ), array( 'faved' => $is_faved ) );
}
add_action( 'wp_ajax_qingya_favorite', 'qingya_ajax_favorite' );
add_action( 'wp_ajax_nopriv_qingya_favorite', 'qingya_ajax_favorite' );

/**
 * 阅读数（点赞/收藏按钮上的数字展示辅助）。
 *
 * @param int $post_id 文章 ID。
 * @return int
 */
function qingya_get_likes( $post_id = 0 ) {
	$post_id = $post_id ? $post_id : get_the_ID();
	return (int) get_post_meta( $post_id, '_qingya_likes', true );
}
