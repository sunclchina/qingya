<?php
/**
 * 页脚。
 *
 * @package Qingya
 */

?>
	</div><!-- /#qy-content -->

	<footer id="qy-footer" class="qy-footer">
		<div class="qy-container">

			<?php if ( is_active_sidebar( 'footer-1' ) || is_active_sidebar( 'footer-2' ) || is_active_sidebar( 'footer-3' ) || is_customize_preview() ) : ?>
				<div class="qy-footer-widgets">
					<?php for ( $qingya_i = 1; $qingya_i <= 3; $qingya_i++ ) : ?>
						<?php if ( is_active_sidebar( 'footer-' . $qingya_i ) || is_customize_preview() ) : ?>
							<div class="qy-footer-col">
								<?php dynamic_sidebar( 'footer-' . $qingya_i ); ?>
								<?php if ( is_customize_preview() && ! is_active_sidebar( 'footer-' . $qingya_i ) ) : ?>
									<div class="qy-widget-placeholder"><?php esc_html_e( '页脚空位：在小工具中添加内容', 'qingya' ); ?></div>
								<?php endif; ?>
							</div>
						<?php endif; ?>
					<?php endfor; ?>
				</div>
			<?php endif; ?>

			<?php
			// 页脚友情链接。
			$qingya_links = get_theme_mod( 'qy_basic_links', '' );
			if ( $qingya_links ) :
				?>
				<div class="qy-footer-links">
					<span class="qy-footer-links-label"><?php esc_html_e( '友情链接：', 'qingya' ); ?></span>
					<?php
					foreach ( preg_split( '/[\r\n]+/', $qingya_links ) as $qingya_link ) {
						$qingya_link = trim( $qingya_link );
						if ( ! $qingya_link ) {
							continue;
						}
						$parts = explode( '|', $qingya_link );
						$name  = trim( $parts[0] );
						$url   = isset( $parts[1] ) ? trim( $parts[1] ) : '';
						if ( $name && $url ) {
							printf( '<a href="%s" target="_blank" rel="noopener nofollow">%s</a>', esc_url( $url ), esc_html( $name ) );
						}
					}
					?>
				</div>
			<?php endif; ?>

			<div class="qy-footer-bottom">
				<div class="qy-copyright">
					<?php
					$qingya_copyright = get_theme_mod( 'qy_basic_copyright', '' );
					if ( $qingya_copyright ) {
						echo wp_kses_post( $qingya_copyright );
					} else {
						printf(
							/* translators: 1: 站点名 2: 年份。 */
							esc_html__( '© %1$s %2$s. 保留所有权利。', 'qingya' ),
							esc_html( get_bloginfo( 'name' ) ),
							esc_html( date_i18n( 'Y' ) )
						);
					}
					?>
				</div>
				<div class="qy-footer-meta">
					<?php
					// 备案号。
					$qingya_icp = get_theme_mod( 'qy_basic_icp', '' );
					if ( $qingya_icp ) :
						printf(
							'<a href="https://beian.miit.gov.cn/" target="_blank" rel="nofollow noopener">%s</a>',
							esc_html( $qingya_icp )
						);
					endif;
					?>
					<?php if ( get_theme_mod( 'qy_basic_email', '' ) ) : ?>
						<a href="mailto:<?php echo esc_attr( get_theme_mod( 'qy_basic_email' ) ); ?>"><?php echo esc_html( get_theme_mod( 'qy_basic_email' ) ); ?></a>
					<?php endif; ?>
				</div>
			</div>

		</div>
	</footer>

	<?php qingya_back_to_top(); ?>

	<?php qingya_ai_chatbot_render(); ?>

	<?php
	// 统计代码（Customizer 配置）。
	$qingya_tracking = get_theme_mod( 'qy_seo_tracking', '' );
	if ( $qingya_tracking ) {
		echo wp_kses_post( $qingya_tracking );
	}
	?>

</div><!-- /#qy-page -->

<?php wp_footer(); ?>
</body>
</html>
