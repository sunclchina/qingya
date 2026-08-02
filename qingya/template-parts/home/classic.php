<?php
/**
 * 经典首页布局（与原首页一致：轮播 + 推荐区 + 最新列表）。
 * 由 front-page.php 按布局设置加载；page-templates/front-page.php 保留为独立页面模板。
 *
 * @package Qingya
 */

// 轮播（独占整行，位于双栏容器之外）。
echo '<div class="qy-container">';
qingya_render_carousel();
echo '</div>';
?>

<div class="qy-container col-1c">

	<main id="qy-main" class="qy-main">

		<?php
		// 置顶/推荐区（最新 4 篇，含置顶优先）。
		if ( 'on' === get_theme_mod( 'qy_layout_show_featured', 'on' ) ) :
			$qingya_featured = new WP_Query( array(
				'posts_per_page'      => 4,
				'ignore_sticky_posts' => false,
				'no_found_rows'       => true,
				'post_type'           => 'post',
			) );
			if ( $qingya_featured->have_posts() ) :
				?>
				<section class="qy-featured">
					<div class="qy-featured-grid">
						<?php
						while ( $qingya_featured->have_posts() ) :
							$qingya_featured->the_post();
							?>
							<article class="qy-featured-item">
								<a href="<?php the_permalink(); ?>">
									<span class="qy-featured-thumb">
										<?php qingya_post_thumbnail( 'qingya-card' ); ?>
										<?php if ( is_sticky() ) : ?>
											<span class="qy-sticky-badge"><?php esc_html_e( '置顶', 'qingya' ); ?></span>
										<?php endif; ?>
									</span>
									<span class="qy-featured-title"><?php the_title(); ?></span>
								</a>
							</article>
						<?php endwhile; ?>
					</div>
				</section>
				<?php
				wp_reset_postdata();
			endif;
		endif;
		?>

		<section class="qy-front-list">
			<h2 class="qy-block-title"><?php echo esc_html( get_theme_mod( 'qy_front_section_title', __( '最新文章', 'qingya' ) ) ); ?></h2>

			<?php if ( have_posts() ) : ?>
				<div class="qy-post-list qy-list-<?php echo esc_attr( get_theme_mod( 'qy_layout_list_style', 'card' ) ); ?>">
					<?php
					while ( have_posts() ) :
						the_post();
						get_template_part( 'template-parts/content/content' );
					endwhile;
					?>
				</div>

				<?php qingya_pagination(); ?>

			<?php else : ?>
				<div class="qy-empty">
					<p><?php esc_html_e( '暂无内容，先去后台发布一篇文章吧。', 'qingya' ); ?></p>
				</div>
			<?php endif; ?>
		</section>

	</main>
</div>
