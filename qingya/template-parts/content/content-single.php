<?php
/**
 * 文章详情内容片段。
 *
 * @package Qingya
 */

?>
<article id="post-<?php the_ID(); ?>" <?php post_class( 'qy-post' ); ?>>

	<header class="qy-post-header">
		<?php
		$qingya_cats = get_the_category();
		if ( $qingya_cats ) :
			?>
			<div class="qy-post-cats">
				<?php foreach ( $qingya_cats as $qingya_cat ) : ?>
					<a href="<?php echo esc_url( get_category_link( $qingya_cat ) ); ?>"><?php echo esc_html( $qingya_cat->name ); ?></a>
				<?php endforeach; ?>
			</div>
		<?php endif; ?>

		<?php if ( ! get_post_meta( get_the_ID(), '_qingya_hide_title', true ) ) : ?>
			<h1 class="qy-post-title"><?php the_title(); ?></h1>
		<?php endif; ?>

		<div class="qy-post-meta">
			<span class="qy-meta-author"><?php the_author_posts_link(); ?></span>
			<span class="qy-meta-date"><?php echo esc_html( get_the_date() ); ?></span>
			<span class="qy-meta-reading"><?php echo esc_html( qingya_reading_time() ); ?></span>
			<span class="qy-meta-views"><?php echo esc_html( qingya_views_text() ); ?></span>
		</div>
	</header>

	<?php if ( has_post_thumbnail() ) : ?>
		<div class="qy-post-thumb">
			<?php the_post_thumbnail( 'qingya-wide', array( 'loading' => 'eager', 'fetchpriority' => 'high' ) ); ?>
		</div>
	<?php endif; ?>

	<div class="qy-post-content entry-content">
		<?php
		the_content();

		wp_link_pages( array(
			'before' => '<div class="qy-post-pages">' . esc_html__( '分页：', 'qingya' ),
			'after'  => '</div>',
		) );
		?>
	</div>

	<footer class="qy-post-footer">
		<?php
		$qingya_tags = get_the_tags();
		if ( $qingya_tags ) :
			?>
			<div class="qy-post-tags">
				<span class="qy-post-tags-label"><?php esc_html_e( '标签：', 'qingya' ); ?></span>
				<?php foreach ( $qingya_tags as $qingya_tag ) : ?>
					<a href="<?php echo esc_url( get_tag_link( $qingya_tag ) ); ?>">#<?php echo esc_html( $qingya_tag->name ); ?></a>
				<?php endforeach; ?>
			</div>
		<?php endif; ?>

		<div class="qy-post-actions">
			<button type="button" class="qy-action-btn qy-like-btn" data-post="<?php the_ID(); ?>">
				<span class="qy-action-icon">👍</span>
				<span class="qy-like-count"><?php echo esc_html( qingya_get_likes() ); ?></span>
				<span class="qy-action-label"><?php esc_html_e( '点赞', 'qingya' ); ?></span>
			</button>
			<button type="button" class="qy-action-btn qy-fav-btn" data-post="<?php the_ID(); ?>">
				<span class="qy-action-icon">⭐</span>
				<span class="qy-action-label"><?php esc_html_e( '收藏', 'qingya' ); ?></span>
			</button>
		</div>

		<?php qingya_share_buttons(); ?>
	</footer>

	<?php
	// 作者简介。
	$qingya_author_desc = get_the_author_meta( 'description' );
	if ( $qingya_author_desc ) :
		?>
		<div class="qy-author-bio">
			<div class="qy-author-avatar"><?php echo get_avatar( get_the_author_meta( 'user_email' ), 64 ); ?></div>
			<div class="qy-author-info">
				<strong><?php the_author(); ?></strong>
				<p><?php echo esc_html( $qingya_author_desc ); ?></p>
			</div>
		</div>
	<?php endif; ?>

	<?php qingya_post_nav(); ?>

	<?php
	if ( 'off' !== get_theme_mod( 'qy_layout_show_featured', 'on' ) ) {
		qingya_related_posts();
	}
	?>

</article>
