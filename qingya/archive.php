<?php
/**
 * 归档模板（分类/标签/日期/作者）。
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
			<h1 class="qy-archive-title"><?php the_archive_title(); ?></h1>
			<?php the_archive_description( '<div class="qy-archive-desc">', '</div>' ); ?>
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
				<p><?php esc_html_e( '该分类下暂无内容。', 'qingya' ); ?></p>
			</div>
		<?php endif; ?>

	</main>

	<?php get_sidebar(); ?>
</div>

<?php
get_footer();
