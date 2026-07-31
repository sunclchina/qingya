<?php
/**
 * 侧边栏（布局类为 col-2c* 时输出）。
 * 注：Customizer 预览时无条件输出容器（空侧边栏也可编辑）。
 *
 * @package Qingya
 */

$qingya_layout = qingya_layout_class();

// 正常前台：仅两栏布局输出侧边栏。
if ( ! in_array( $qingya_layout, array( 'col-2cr', 'col-2cl' ), true ) && ! is_customize_preview() ) {
	return;
}
?>

<aside id="qy-sidebar" class="qy-sidebar" role="complementary">
	<?php if ( is_active_sidebar( 'sidebar-1' ) ) : ?>
		<?php dynamic_sidebar( 'sidebar-1' ); ?>
	<?php else : ?>
		<section class="widget">
			<h3 class="widget-title"><?php esc_html_e( '侧边栏', 'qingya' ); ?></h3>
			<p><?php esc_html_e( '请在「外观 → 小工具」中添加内容。', 'qingya' ); ?></p>
		</section>
	<?php endif; ?>
</aside>
