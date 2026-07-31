<?php
/**
 * 文章详情页。
 *
 * @package Qingya
 */

get_header();

$qingya_layout = qingya_layout_class();
?>

<div class="qy-container <?php echo esc_attr( $qingya_layout ); ?>">
	<main id="qy-main" class="qy-main">

		<?php qingya_breadcrumb(); ?>

		<?php
		while ( have_posts() ) :
			the_post();
			get_template_part( 'template-parts/content/content-single' );
			comments_template();
		endwhile;
		?>

	</main>

	<?php get_sidebar(); ?>
</div>

<?php
get_footer();
