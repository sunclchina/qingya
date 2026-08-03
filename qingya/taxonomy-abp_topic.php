<?php
/**
 * 热门话题归档页（abp_topic）。
 * 参照星河AI工具箱「制造话题、引导互动」形态：
 * 话题标题 + 话题简介（AI 生成）+ 话题统计 + 相关文章卡片列表 + 相关话题导航。
 *
 * @package Qingya
 */

get_header();

$qingya_layout = qingya_layout_class();
$qy_topic      = get_queried_object();
$qy_topic_desc = '';
if ( $qy_topic && ! is_wp_error( $qy_topic ) ) {
	$qy_topic_desc = get_term_meta( $qy_topic->term_id, '_abp_desc', true );
	if ( '' === $qy_topic_desc ) {
		$qy_topic_desc = term_description( $qy_topic->term_id );
	}
}
?>

<div class="qy-container <?php echo esc_attr( $qingya_layout ); ?>">
	<main id="qy-main" class="qy-main">

		<?php qingya_breadcrumb(); ?>

		<header class="qy-topic-header">
			<div class="qy-topic-label"><?php esc_html_e( '热门话题', 'qingya' ); ?></div>
			<h1 class="qy-archive-title">#<?php single_term_title(); ?></h1>
			<?php if ( $qy_topic_desc ) : ?>
				<div class="qy-topic-desc"><?php echo esc_html( $qy_topic_desc ); ?></div>
			<?php endif; ?>
			<?php
			$qy_topic_count = $qy_topic ? $qy_topic->count : 0;
			?>
			<div class="qy-topic-meta">共 <?php echo esc_html( $qy_topic_count ); ?> 篇文章 · 持续更新中</div>
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
				<p><?php esc_html_e( '该话题下暂无内容，敬请期待。', 'qingya' ); ?></p>
			</div>
		<?php endif; ?>

		<?php
		// 相关话题导航（同站其他话题）。
		$qy_all_topics = get_terms(
			array(
				'taxonomy'   => 'abp_topic',
				'hide_empty' => false,
				'orderby'    => 'count',
				'order'      => 'DESC',
				'number'     => 8,
			)
		);
		if ( $qy_all_topics && ! is_wp_error( $qy_all_topics ) && count( $qy_all_topics ) > 1 ) :
			?>
			<div class="qy-topic-nav">
				<h3><?php esc_html_e( '其他热门话题', 'qingya' ); ?></h3>
				<div class="qy-topic-tags">
					<?php foreach ( $qy_all_topics as $qy_t ) : ?>
						<?php if ( $qy_t->term_id === (int) $qy_topic->term_id ) { continue; } ?>
						<a href="<?php echo esc_url( get_term_link( $qy_t ) ); ?>">#<?php echo esc_html( $qy_t->name ); ?><span><?php echo esc_html( $qy_t->count ); ?></span></a>
					<?php endforeach; ?>
				</div>
			</div>
		<?php endif; ?>

	</main>

	<?php get_sidebar(); ?>
</div>

<?php
get_footer();
