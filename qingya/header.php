<?php
/**
 * 页头。
 *
 * @package Qingya
 */

?><!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
<meta charset="<?php bloginfo( 'charset' ); ?>">
<meta name="viewport" content="width=device-width, initial-scale=1">
<?php wp_head(); ?>
</head>

<body <?php body_class(); ?>>
<?php wp_body_open(); ?>

<a class="skip-link screen-reader-text" href="#qy-content"><?php esc_html_e( '跳到内容', 'qingya' ); ?></a>

<div id="qy-page" class="qy-site">

	<?php
	// 顶部公告栏。
	$qingya_notice = get_theme_mod( 'qy_basic_notice', '' );
	if ( $qingya_notice ) :
		?>
		<div class="qy-notice-bar">
			<div class="qy-container">
				<span class="qy-notice-text"><?php echo esc_html( $qingya_notice ); ?></span>
			</div>
		</div>
	<?php endif; ?>

	<header id="qy-header" class="qy-header">
		<div class="qy-container qy-header-inner">

			<div class="qy-brand">
				<?php if ( has_custom_logo() ) : ?>
					<?php the_custom_logo(); ?>
				<?php else : ?>
					<a class="qy-brand-name" href="<?php echo esc_url( home_url( '/' ) ); ?>" rel="home"><?php bloginfo( 'name' ); ?></a>
				<?php endif; ?>
				<?php
				$qingya_desc = get_bloginfo( 'description', 'display' );
				if ( $qingya_desc && ! has_custom_logo() ) :
					?>
					<span class="qy-brand-desc"><?php echo esc_html( $qingya_desc ); ?></span>
				<?php endif; ?>
			</div>

			<div class="qy-header-actions">
				<?php if ( has_nav_menu( 'primary' ) ) : ?>
					<nav id="qy-nav" class="qy-nav" aria-label="<?php esc_attr_e( '主导航', 'qingya' ); ?>">
						<?php
						wp_nav_menu( array(
							'theme_location' => 'primary',
							'container'      => false,
							'menu_class'     => 'qy-nav-menu',
							'depth'          => 3,
							'fallback_cb'    => false,
						) );
						?>
					</nav>
				<?php endif; ?>

				<div class="qy-header-tools">
					<button class="qy-search-toggle" aria-label="<?php esc_attr_e( '搜索', 'qingya' ); ?>" aria-expanded="false">
						<svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><circle cx="11" cy="11" r="7"/><path d="M21 21l-4.3-4.3"/></svg>
					</button>
					<?php if ( 'on' === get_theme_mod( 'qy_color_darkmode', 'on' ) ) : ?>
						<button id="qy-dark-toggle" class="qy-dark-toggle" aria-label="<?php esc_attr_e( '切换深浅色模式', 'qingya' ); ?>">
							<span class="qy-dark-icon">🌙</span>
						</button>
					<?php endif; ?>
					<?php if ( ! is_user_logged_in() ) : ?>
						<a class="qy-login-link" href="<?php echo esc_url( wp_login_url() ); ?>"><?php esc_html_e( '登录', 'qingya' ); ?></a>
					<?php else : ?>
						<a class="qy-login-link" href="<?php echo esc_url( admin_url() ); ?>"><?php esc_html_e( '后台', 'qingya' ); ?></a>
					<?php endif; ?>
					<button id="qy-menu-toggle" class="qy-menu-toggle" aria-label="<?php esc_attr_e( '打开菜单', 'qingya' ); ?>" aria-controls="qy-nav" aria-expanded="false">
						<span></span><span></span><span></span>
					</button>
				</div>
			</div>

		</div>

		<div class="qy-search-panel" hidden>
			<div class="qy-container">
				<?php get_search_form(); ?>
			</div>
		</div>
	</header>

	<div id="qy-content" class="qy-content">
