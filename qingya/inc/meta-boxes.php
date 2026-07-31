<?php
/**
 * 文章/页面独立 Meta 配置：
 * - SEO 标题、关键词、描述（单篇独立）
 * - 独立布局（侧边栏位置覆盖全局）
 * - 文章页：隐藏标题 / 浏览量（只读展示）
 *
 * @package Qingya
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * 注册 Meta Box。
 */
function qingya_meta_boxes() {
	$screens = array( 'post', 'page' );
	foreach ( $screens as $screen ) {
		add_meta_box(
			'qingya_seo_box',
			__( '青崖 SEO 设置', 'qingya' ),
			'qingya_meta_box_seo_render',
			$screen,
			'normal',
			'high'
		);
		add_meta_box(
			'qingya_layout_box',
			__( '青崖布局设置', 'qingya' ),
			'qingya_meta_box_layout_render',
			$screen,
			'side',
			'default'
		);
	}
}
add_action( 'add_meta_boxes', 'qingya_meta_boxes' );

/**
 * SEO Meta Box 渲染。
 *
 * @param WP_Post $post 当前文章。
 */
function qingya_meta_box_seo_render( $post ) {
	wp_nonce_field( 'qingya_meta_save', 'qingya_meta_nonce' );

	$title    = get_post_meta( $post->ID, '_qingya_seo_title', true );
	$keywords = get_post_meta( $post->ID, '_qingya_seo_keywords', true );
	$desc     = get_post_meta( $post->ID, '_qingya_seo_desc', true );
	?>
	<p>
		<label for="qingya_seo_title"><strong><?php esc_html_e( 'SEO 标题', 'qingya' ); ?></strong></label><br>
		<input type="text" id="qingya_seo_title" name="qingya_seo_title" class="widefat" value="<?php echo esc_attr( $title ); ?>" placeholder="<?php esc_attr_e( '留空自动生成', 'qingya' ); ?>">
	</p>
	<p>
		<label for="qingya_seo_keywords"><strong><?php esc_html_e( '关键词（逗号分隔）', 'qingya' ); ?></strong></label><br>
		<input type="text" id="qingya_seo_keywords" name="qingya_seo_keywords" class="widefat" value="<?php echo esc_attr( $keywords ); ?>">
	</p>
	<p>
		<label for="qingya_seo_desc"><strong><?php esc_html_e( '描述', 'qingya' ); ?></strong></label><br>
		<textarea id="qingya_seo_desc" name="qingya_seo_desc" class="widefat" rows="3"><?php echo esc_textarea( $desc ); ?></textarea>
	</p>
	<?php
}

/**
 * 布局 Meta Box 渲染。
 *
 * @param WP_Post $post 当前文章。
 */
function qingya_meta_box_layout_render( $post ) {
	wp_nonce_field( 'qingya_meta_save', 'qingya_meta_nonce' );

	$layout = get_post_meta( $post->ID, '_qingya_layout', true );
	$hide_title = get_post_meta( $post->ID, '_qingya_hide_title', true );
	?>
	<p>
		<label for="qingya_layout"><strong><?php esc_html_e( '本页布局', 'qingya' ); ?></strong></label><br>
		<select id="qingya_layout" name="qingya_layout" class="widefat">
			<option value=""><?php esc_html_e( '跟随全局', 'qingya' ); ?></option>
			<option value="right" <?php selected( 'right', $layout ); ?>><?php esc_html_e( '右侧边栏', 'qingya' ); ?></option>
			<option value="left" <?php selected( 'left', $layout ); ?>><?php esc_html_e( '左侧边栏', 'qingya' ); ?></option>
			<option value="none" <?php selected( 'none', $layout ); ?>><?php esc_html_e( '无侧边栏', 'qingya' ); ?></option>
			<option value="full" <?php selected( 'full', $layout ); ?>><?php esc_html_e( '全宽', 'qingya' ); ?></option>
		</select>
	</p>
	<p>
		<label><input type="checkbox" name="qingya_hide_title" value="1" <?php checked( '1', $hide_title ); ?>> <?php esc_html_e( '隐藏页面标题', 'qingya' ); ?></label>
	</p>
	<?php
}

/**
 * 保存 Meta 数据。
 *
 * @param int $post_id 文章 ID。
 */
function qingya_meta_box_save( $post_id ) {
	if ( ! isset( $_POST['qingya_meta_nonce'] ) || ! wp_verify_nonce( sanitize_key( $_POST['qingya_meta_nonce'] ), 'qingya_meta_save' ) ) {
		return;
	}
	if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
		return;
	}
	if ( ! current_user_can( 'edit_post', $post_id ) ) {
		return;
	}

	$fields = array(
		'qingya_seo_title'    => 'sanitize_text_field',
		'qingya_seo_keywords' => 'sanitize_text_field',
		'qingya_seo_desc'     => 'sanitize_textarea_field',
		'qingya_layout'       => 'sanitize_key',
	);
	foreach ( $fields as $field => $sanitize ) {
		$value = isset( $_POST[ $field ] ) ? call_user_func( $sanitize, wp_unslash( $_POST[ $field ] ) ) : '';
		if ( '' === $value ) {
			delete_post_meta( $post_id, '_' . $field );
		} else {
			update_post_meta( $post_id, '_' . $field, $value );
		}
	}

	// 隐藏标题（checkbox）。
	if ( isset( $_POST['qingya_hide_title'] ) ) {
		update_post_meta( $post_id, '_qingya_hide_title', '1' );
	} else {
		delete_post_meta( $post_id, '_qingya_hide_title' );
	}
}
add_action( 'save_post', 'qingya_meta_box_save' );
