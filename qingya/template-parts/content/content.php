<?php
/**
 * 内容模板片段：文章列表卡片。
 * 样式由 Customizer 的「列表样式」控制（card / list）。
 *
 * @package Qingya
 */

?>
<article id="post-<?php the_ID(); ?>" <?php post_class( 'qy-card' ); ?>>

	<?php if ( has_post_thumbnail() ) : ?>
		<a class="qy-card-thumb" href="<?php the_permalink(); ?>" aria-hidden="true" tabindex="-1">
			<?php qingya_post_thumbnail( 'qingya-card' ); ?>
		</a>
	<?php endif; ?>

	<div class="qy-card-body">
		<h2 class="qy-card-title">
			<a href="<?php the_permalink(); ?>" rel="bookmark"><?php the_title(); ?></a>
			<?php if ( is_sticky() ) : ?>
				<span class="qy-sticky-badge"><?php esc_html_e( '置顶', 'qingya' ); ?></span>
			<?php endif; ?>
		</h2>

		<div class="qy-card-meta">
			<span class="qy-meta-date"><?php echo esc_html( get_the_date() ); ?></span>
			<?php
			$qingya_cats = get_the_category();
			if ( $qingya_cats ) :
				?>
				<span class="qy-meta-cats">
					<?php foreach ( array_slice( $qingya_cats, 0, 2 ) as $qingya_cat ) : ?>
						<a href="<?php echo esc_url( get_category_link( $qingya_cat ) ); ?>"><?php echo esc_html( $qingya_cat->name ); ?></a>
					<?php endforeach; ?>
				</span>
			<?php endif; ?>
			<span class="qy-meta-views"><?php echo esc_html( qingya_views_text() ); ?></span>
			<?php if ( ! post_password_required() && ( comments_open() || get_comments_number() ) ) : ?>
				<span class="qy-meta-comments">
					<a href="<?php comments_link(); ?>"><?php comments_number( '0', '1', '%' ); ?> <?php esc_html_e( '评论', 'qingya' ); ?></a>
				</span>
			<?php endif; ?>
		</div>

		<div class="qy-card-excerpt">
			<?php the_excerpt(); ?>
		</div>

		<a class="qy-card-more" href="<?php the_permalink(); ?>"><?php esc_html_e( '阅读全文 →', 'qingya' ); ?></a>
	</div>

</article>
