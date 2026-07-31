<?php
/**
 * 独立页面内容片段。
 *
 * @package Qingya
 */

?>
<article id="post-<?php the_ID(); ?>" <?php post_class( 'qy-page' ); ?>>

	<?php if ( ! get_post_meta( get_the_ID(), '_qingya_hide_title', true ) ) : ?>
		<header class="qy-page-header">
			<h1 class="qy-page-title"><?php the_title(); ?></h1>
		</header>
	<?php endif; ?>

	<div class="qy-page-content entry-content">
		<?php
		the_content();

		wp_link_pages( array(
			'before' => '<div class="qy-post-pages">' . esc_html__( '分页：', 'qingya' ),
			'after'  => '</div>',
		) );

		if ( comments_open() || get_comments_number() ) {
			comments_template();
		}
		?>
	</div>

</article>
