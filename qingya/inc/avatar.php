<?php
/**
 * 本地头像模块：解决 Gravatar 国内不可用问题。
 *
 * - 用户资料页（后台 → 个人资料）新增「头像设置」：媒体库选择/上传/移除
 * - 全站头像输出：优先用户本地头像，未设置时用主题默认头像，完全不依赖 Gravatar
 *
 * @package Qingya
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * 主题默认头像。
 */
function qingya_default_avatar_url() {
	return QINGYA_URI . '/assets/img/default-avatar.webp';
}

/**
 * 头像过滤：本地头像 > 主题默认头像；任何情况下不输出 Gravatar 图片（国内不可达）。
 *
 * @param string $avatar      默认头像 HTML。
 * @param mixed  $id_or_email 用户 ID / 邮箱 / 评论对象。
 * @param array  $args        get_avatar 参数（size/alt 等）。
 * @return string
 */
function qingya_local_avatar( $avatar, $id_or_email, $args ) {
	$size = ! empty( $args['size'] ) ? (int) $args['size'] : 96;
	$alt  = ! empty( $args['alt'] ) ? $args['alt'] : '';
	$url  = '';

	$user = qingya_avatar_resolve_user( $id_or_email );
	if ( $user ) {
		$local = get_user_meta( $user->ID, 'qingya_avatar', true );
		if ( $local ) {
			$url = $local;
		}
	}

	if ( ! $url ) {
		// 未设置本地头像或无法解析用户：一律回退主题默认头像。
		$url = qingya_default_avatar_url();
	}

	return '<img src="' . esc_url( $url ) . '" class="avatar avatar-' . $size . ' photo" width="' . $size . '" height="' . $size . '" alt="' . esc_attr( $alt ) . '" />';
}
add_filter( 'pre_get_avatar', 'qingya_local_avatar', 10, 3 );

/**
 * 解析 get_avatar 的 $id_or_email 为 WP_User。
 *
 * @param mixed $id_or_email 用户 ID / 邮箱 / 评论对象。
 * @return WP_User|false
 */
function qingya_avatar_resolve_user( $id_or_email ) {
	if ( is_numeric( $id_or_email ) ) {
		return get_user_by( 'id', (int) $id_or_email );
	}
	if ( is_string( $id_or_email ) && is_email( $id_or_email ) ) {
		return get_user_by( 'email', $id_or_email );
	}
	if ( is_object( $id_or_email ) ) {
		if ( ! empty( $id_or_email->user_id ) ) {
			return get_user_by( 'id', (int) $id_or_email->user_id );
		}
		if ( ! empty( $id_or_email->comment_author_email ) && is_email( $id_or_email->comment_author_email ) ) {
			return get_user_by( 'email', $id_or_email->comment_author_email );
		}
	}
	return false;
}

/**
 * 用户资料页：头像设置区。
 *
 * @param WP_User $user 当前编辑的用户。
 */
function qingya_avatar_profile_field( $user ) {
	$url = get_user_meta( $user->ID, 'qingya_avatar', true );
	$src = $url ? $url : qingya_default_avatar_url();
	?>
	<h2><?php esc_html_e( '头像设置', 'qingya' ); ?></h2>
	<table class="form-table">
		<tr>
			<th><label><?php esc_html_e( '个人头像', 'qingya' ); ?></label></th>
			<td>
				<div id="qingya-avatar-preview" style="margin-bottom:10px;">
					<img src="<?php echo esc_url( $src ); ?>" alt=""
						style="width:96px;height:96px;border-radius:50%;object-fit:cover;border:1px solid #dcdcdc;background:#f5f5f5;">
				</div>
				<input type="hidden" name="qingya_avatar" id="qingya-avatar-url" value="<?php echo esc_attr( $url ); ?>">
				<button type="button" class="button" id="qingya-avatar-upload"><?php esc_html_e( '选择图片', 'qingya' ); ?></button>
				<button type="button" class="button" id="qingya-avatar-remove"<?php echo $url ? '' : ' disabled'; ?>><?php esc_html_e( '移除头像', 'qingya' ); ?></button>
				<p class="description"><?php esc_html_e( '上传或从媒体库选择头像（建议正方形图片）。未设置时显示主题默认头像，不依赖 Gravatar。', 'qingya' ); ?></p>
			</td>
		</tr>
	</table>
	<?php
}
add_action( 'show_user_profile', 'qingya_avatar_profile_field' );
add_action( 'edit_user_profile', 'qingya_avatar_profile_field' );

/**
 * 保存头像。
 *
 * @param int $user_id 用户 ID。
 */
function qingya_avatar_save( $user_id ) {
	if ( ! current_user_can( 'edit_user', $user_id ) ) {
		return;
	}
	if ( isset( $_POST['qingya_avatar'] ) ) {
		$url = esc_url_raw( wp_unslash( $_POST['qingya_avatar'] ) );
		if ( $url ) {
			update_user_meta( $user_id, 'qingya_avatar', $url );
		} else {
			delete_user_meta( $user_id, 'qingya_avatar' );
		}
	}
}
add_action( 'personal_options_update', 'qingya_avatar_save' );
add_action( 'edit_user_profile_update', 'qingya_avatar_save' );

/**
 * 后台用户资料页：加载媒体库与头像脚本。
 *
 * @param string $hook 当前后台页面。
 */
function qingya_avatar_admin_assets( $hook ) {
	if ( 'profile.php' !== $hook && 'user-edit.php' !== $hook ) {
		return;
	}
	wp_enqueue_media();
	wp_enqueue_script( 'qingya-avatar', QINGYA_URI . '/assets/js/qingya-avatar.js', array( 'jquery' ), QINGYA_VERSION, true );
	wp_localize_script( 'qingya-avatar', 'qingyaAvatar', array(
		'default' => qingya_default_avatar_url(),
	) );
}
add_action( 'admin_enqueue_scripts', 'qingya_avatar_admin_assets' );
