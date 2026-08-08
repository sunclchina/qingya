<?php
/**
 * 404 页面：友好提示 + 返回首页 + 搜索。
 *
 * @package Qingya
 */

get_header();
?>

<div class="qy-container col-1c">
	<main id="qy-main" class="qy-main">
		<section class="qy-error-404">
			<div class="qy-error-code">404</div>
			<h1 class="qy-error-title"><?php esc_html_e( '页面走丢了', 'qingya' ); ?></h1>
			<p class="qy-error-desc">
				<?php esc_html_e( '您访问的页面不存在，或已被移动。请检查网址，或使用搜索寻找内容。', 'qingya' ); ?>
			</p>
			<div class="qy-error-actions">
				<a class="qy-btn" href="<?php echo esc_url( home_url( '/' ) ); ?>"><?php esc_html_e( '返回首页', 'qingya' ); ?></a>
			</div>
			<div class="qy-error-search">
				<?php get_search_form(); ?>
			</div>
		</section>
	</main>
</div>

<?php
get_footer();
