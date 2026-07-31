<?php
/**
 * 评论模板（WP 原生评论列表 + 表单）。
 *
 * @package Qingya
 */

if ( post_password_required() ) {
	return;
}
?>

<div id="comments" class="qy-comments">

	<?php if ( have_comments() ) : ?>
		<h3 class="qy-comments-title">
			<?php
			$qingya_comment_count = get_comments_number();
			/* translators: %d: 评论数。 */
			printf( esc_html__( '评论（%d）', 'qingya' ), (int) $qingya_comment_count );
			?>
		</h3>

		<ol class="qy-comment-list">
			<?php
			wp_list_comments( array(
				'style'       => 'ol',
				'short_ping'  => true,
				'avatar_size' => 48,
			) );
			?>
		</ol>

		<?php
		the_comments_pagination( array(
			'prev_text' => __( '上一页', 'qingya' ),
			'next_text' => __( '下一页', 'qingya' ),
		) );
		?>

		<?php if ( ! comments_open() ) : ?>
			<p class="qy-comments-closed"><?php esc_html_e( '评论已关闭。', 'qingya' ); ?></p>
		<?php endif; ?>

	<?php endif; ?>

	<?php
	comment_form( array(
		'title_reply'          => __( '发表评论', 'qingya' ),
		'title_reply_to'       => __( '回复 %s', 'qingya' ),
		'cancel_reply_link'    => __( '取消回复', 'qingya' ),
		'label_submit'         => __( '提交评论', 'qingya' ),
		'comment_notes_before' => '',
	) );
	?>

</div>
