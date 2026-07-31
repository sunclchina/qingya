<?php
/**
 * 搜索结果页。
 *
 * @package Qingya
 */

get_header();

$qingya_layout = qingya_layout_class();
?>

<div class="qy-container <?php echo esc_attr( $qingya_layout ); ?>">
	<main id="qy-main" class="qy-main">

		<?php qingya_breadcrumb(); ?>

		<header class="qy-archive-header">
			<h1 class="qy-archive-title">
				<?php
				/* translators: %s: 搜索关键词。 */
				printf( esc_html__( '搜索「%s」的结果', 'qingya' ), esc_html( get_search_query() ) );
				?>
			</h1>
			<p class="qy-archive-desc">
				<?php
				global $wp_query;
				/* translators: %d: 结果数量。 */
				printf( esc_html__( '共找到 %d 条相关内容', 'qingya' ), (int) $wp_query->found_posts );
				?>
			</p>
		</header>

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
				<p><?php esc_html_e( '没有找到相关内容，换个关键词试试？', 'qingya' ); ?></p>
				<?php get_search_form(); ?>
			</div>
		<?php endif; ?>

	</main>

	<?php get_sidebar(); ?>
</div>

<?php
get_footer();
