<?php
/**
 * 主模板（文章流 / 回退模板）。
 *
 * @package Qingya
 */

get_header();

// 文章列表首页：顶部显示轮播（独占整行，不受双栏布局影响）。
if ( is_front_page() ) {
	echo '<div class="qy-container">';
	qingya_render_carousel();
	echo '</div>';
}

$qingya_layout = qingya_layout_class();
?>

<div class="qy-container <?php echo esc_attr( $qingya_layout ); ?>">
	<main id="qy-main" class="qy-main">

		<?php qingya_breadcrumb(); ?>

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
				<p><?php esc_html_e( '暂无内容。', 'qingya' ); ?></p>
				<?php get_search_form(); ?>
			</div>
		<?php endif; ?>

	</main>

	<?php get_sidebar(); ?>
</div>

<?php
get_footer();
