<?php
/**
 * Template Name: 全宽页面（无侧边栏）
 * Template Post Type: page
 *
 * 适用于展示型页面（大图、案例详情等）。
 *
 * @package Qingya
 */

get_header();
?>

<div class="qy-container col-full">
	<main id="qy-main" class="qy-main">

		<?php
		while ( have_posts() ) :
			the_post();
			get_template_part( 'template-parts/content/content-page' );
		endwhile;
		?>

	</main>
</div>

<?php
get_footer();
