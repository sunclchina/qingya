<?php
/**
 * 极简文章首页布局：
 * 分类 chips + 文章列表（可翻页）+ 财经快讯折叠条 + 侧边栏。
 *
 * @package Qingya
 */

?>
<div class="qy-container qy-home-layout qy-home-minimal">

	<main id="qy-main" class="qy-main">

		<?php
		qingya_home_simple_list();
		?>

		<?php if ( 'off' !== get_theme_mod( 'qy_home_stock_on', 'on' ) ) : ?>
			<details class="qy-home-mini-stock">
				<summary><?php esc_html_e( '📈 财经快讯（点击展开）', 'qingya' ); ?></summary>
				<?php qingya_stock_ticker( 5 ); ?>
			</details>
		<?php endif; ?>

	</main>

	<?php qingya_home_sidebar(); ?>

</div>
