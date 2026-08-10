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
 * 头像数据过滤：本地头像 > 主题默认头像；任何情况下不输出 Gravatar 图片（国内不可达）。
 *
 * 注意：挂 pre_get_avatar_data 而非 pre_get_avatar。
 * - pre_get_avatar 的 $avatar 参数初始为 null（HTML 尚未生成），在其中返回 HTML 会短路
 *   整个头像流程，导致星河AI工具箱/A-Blog 等插件挂在 pre_get_avatar_data 的头像被跳过；
 * - 且「尊重其他插件头像」的检查（preg_match src）在 $avatar=null 时永不命中，等于没修。
 * - 改为 pre_get_avatar_data：只设置 $args['url']，已存在非 Gravatar 的 url 一律尊重，
 *   未设置时才回退主题默认头像。各插件按优先级协作，互不覆盖。
 *
 * @param array $args        get_avatar_data 参数（url/size/alt 等）。
 * @param mixed $id_or_email 用户 ID / 邮箱 / 评论对象。
 * @return array
 */
function qingya_local_avatar( $args, $id_or_email ) {
	// 其他插件（星河AI工具箱/A-Blog 等）已提供非 Gravatar 头像 → 尊重插件机制，不覆盖。
	if ( ! empty( $args['url'] ) ) {
		$src = (string) $args['url'];
		if ( false === stripos( $src, 'gravatar.com' ) && false === stripos( $src, 'default-avatar' ) ) {
			return $args;
		}
	}

	$url = '';

	// 兼容 A-Blog AI 评论头像（_abp_avatar meta，本地确定性 SVG）。
	if ( is_object( $id_or_email ) && ! empty( $id_or_email->comment_ID ) ) {
		$abp = get_comment_meta( $id_or_email->comment_ID, '_abp_avatar', true );
		if ( $abp ) {
			$url = (string) $abp;
		}
	}

	$user = qingya_avatar_resolve_user( $id_or_email );
	if ( ! $url && $user ) {
		$local = get_user_meta( $user->ID, 'qingya_avatar', true );
		if ( $local ) {
			$url = $local;
		}
	}

	if ( ! $url ) {
		// 未设置本地头像或无法解析用户：一律回退主题默认头像。
		$url = qingya_default_avatar_url();
	}

	$args['url']          = $url;
	$args['found_avatar'] = true;
	return $args;
}
add_filter( 'pre_get_avatar_data', 'qingya_local_avatar', 999, 2 );

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

/**
 * 移除 WP 自带「资料图片」区块（后台 → 个人资料）。
 *
 * 主题已提供「头像设置」（本地头像），WP 默认的 Profile Picture 区块与之重复；
 * 且其说明中的 Gravatar 链接国内不可达（无效链接），故整个区块隐藏，只留主题「头像设置」。
 *
 * @param string $hook 当前后台页面。
 */
function qingya_avatar_hide_core_profile_picture( $hook ) {
	if ( 'profile.php' !== $hook && 'user-edit.php' !== $hook ) {
		return;
	}
	// WP 6.8+ 中「资料图片」是「关于你自己」表格内的 tr.user-profile-picture 行（含 get_avatar 输出）。
	wp_add_inline_style( 'wp-admin', '
		#profile-page tr.user-profile-picture { display: none; }
	' );
}
add_action( 'admin_enqueue_scripts', 'qingya_avatar_hide_core_profile_picture' );

/**
 * 兜底：清空 WP 默认「资料图片」说明文字（Gravatar 链接，国内不可达）。
 * 即使上方 CSS 未命中，也不会再输出「您可以在 Gravatar 修改您的资料图片。」。
 *
 * @param string $description 默认说明文字。
 * @return string
 */
function qingya_avatar_profile_picture_description( $description ) {
	return '';
}
add_filter( 'user_profile_picture_description', 'qingya_avatar_profile_picture_description' );
