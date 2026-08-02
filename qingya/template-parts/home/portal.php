<?php
/**
 * 门户综合首页布局：
 * 轮播 + 分类直达 + 热门高赞 + 开源项目区 + 股市消息区 + 侧边栏。
 *
 * @package Qingya
 */

echo '<div class="qy-container">';
qingya_render_carousel();
echo '</div>';
?>

<div class="qy-container qy-home-layout qy-home-portal">

	<main id="qy-main" class="qy-main">

		<?php
		qingya_home_cats_quick();
		qingya_home_hot();
		qingya_home_projects();
		qingya_stock_section();
		?>

	</main>

	<?php qingya_home_sidebar(); ?>

</div>
