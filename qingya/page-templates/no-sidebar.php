<?php
/**
 * Template Name: 无侧边栏页面（居中内容）
 * Template Post Type: page
 *
 * 适用于关于我们、联系我们等阅读型单页。
 *
 * @package Qingya
 */

get_header();
?>

<div class="qy-container col-1c">
	<main id="qy-main" class="qy-main">

		<?php qingya_breadcrumb(); ?>

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
