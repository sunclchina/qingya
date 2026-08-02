<?php
/**
 * 杂志资讯首页布局：
 * 轮播 + 头条区（大图+两小图）+ 分类 chips + 最新文章网格 + 财经快讯条 + 侧边栏。
 *
 * @package Qingya
 */

echo '<div class="qy-container">';
qingya_render_carousel();
echo '</div>';
?>

<div class="qy-container qy-home-layout qy-home-magazine">

	<main id="qy-main" class="qy-main">

		<?php
		qingya_home_masonry( 12 );
		qingya_stock_ticker( 5 );
		?>

	</main>

	<?php qingya_home_sidebar(); ?>

</div>
