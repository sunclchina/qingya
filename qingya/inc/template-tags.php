<?php
/**
 * 模板辅助函数：面包屑、浏览量、缩略图、分页、阅读时间等。
 *
 * @package Qingya
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * 面包屑导航（含 JSON-LD 结构化数据，SEO 模块输出）。
 * 首页 > 分类 > 文章
 */
function qingya_breadcrumb() {
	// 首页不显示。
	if ( is_front_page() ) {
		return;
	}

	$items   = array();
	$items[] = array(
		'name' => __( '首页', 'qingya' ),
		'url'  => home_url( '/' ),
	);

	if ( is_singular() ) {
		$post_type = get_post_type();
		if ( 'post' === $post_type ) {
			$cats = get_the_category();
			if ( ! empty( $cats ) ) {
				$cat = $cats[0];
				$items[] = array(
					'name' => $cat->name,
					'url'  => get_category_link( $cat ),
				);
			}
		} elseif ( 'page' === $post_type && 0 !== ( $parent = wp_get_post_parent_id( get_the_ID() ) ) ) {
			$items[] = array(
				'name' => get_the_title( $parent ),
				'url'  => get_permalink( $parent ),
			);
		}
		$items[] = array(
			'name' => get_the_title(),
			'url'  => '',
		);
	} elseif ( is_category() || is_tag() || is_tax() ) {
		$items[] = array(
			'name' => single_term_title( '', false ),
			'url'  => '',
		);
	} elseif ( is_author() ) {
		$items[] = array(
			'name' => get_the_author(),
			'url'  => '',
		);
	} elseif ( is_search() ) {
		$items[] = array(
			/* translators: %s: 搜索关键词。 */
			'name' => sprintf( __( '搜索：%s', 'qingya' ), get_search_query() ),
			'url'  => '',
		);
	} elseif ( is_archive() ) {
		$items[] = array(
			'name' => wp_strip_all_tags( get_the_archive_title() ),
			'url'  => '',
		);
	} elseif ( is_404() ) {
		$items[] = array(
			'name' => __( '页面未找到', 'qingya' ),
			'url'  => '',
		);
	}

	// 生成 HTML。
	$output = '<nav class="qy-breadcrumb" aria-label="' . esc_attr__( '面包屑导航', 'qingya' ) . '">';
	$output .= '<span class="qy-breadcrumb-home"><a href="' . esc_url( home_url( '/' ) ) . '">' . esc_html__( '首页', 'qingya' ) . '</a></span>';
	$count = count( $items );
	foreach ( $items as $i => $item ) {
		$sep = '<span class="qy-breadcrumb-sep">›</span>';
		if ( 0 === $i ) {
			continue; // 首页已输出。
		}
		$output .= $sep;
		if ( ! empty( $item['url'] ) && $i < $count - 1 ) {
			$output .= '<a href="' . esc_url( $item['url'] ) . '">' . esc_html( $item['name'] ) . '</a>';
		} else {
			$output .= '<span class="qy-breadcrumb-current" aria-current="page">' . esc_html( $item['name'] ) . '</span>';
		}
	}
	$output .= '</nav>';

	echo $output; // phpcs:ignore WordPress.Security.EscapeOutput -- 已逐项转义。
}

/**
 * 文章浏览量（读取）。
 *
 * @param int $post_id 文章 ID。
 * @return int
 */
function qingya_get_views( $post_id = 0 ) {
	$post_id = $post_id ? $post_id : get_the_ID();
	$views   = (int) get_post_meta( $post_id, '_qingya_views', true );
	return max( 0, $views );
}

/**
 * 浏览量显示（带单位缩写：1.2k）。
 *
 * @param int $post_id 文章 ID。
 * @return string
 */
function qingya_views_text( $post_id = 0 ) {
	$views = qingya_get_views( $post_id );
	if ( $views >= 1000 ) {
		$text = round( $views / 1000, 1 ) . 'k';
	} else {
		$text = (string) $views;
	}
	/* translators: %s: 浏览量数字。 */
	return sprintf( __( '%s 次阅读', 'qingya' ), $text );
}

/**
 * 文章头图/缩略图输出（无图时输出占位样式，不依赖外部占位服务）。
 *
 * @param string $size 图片尺寸。
 */
function qingya_post_thumbnail( $size = 'qingya-card' ) {
	if ( has_post_thumbnail() ) {
		the_post_thumbnail( $size, array( 'loading' => 'lazy' ) );
	} else {
		echo '<span class="qy-thumb-placeholder" aria-hidden="true">' . esc_html( get_the_title() ) . '</span>';
	}
}

/**
 * 分页（兼容 WP-PageNavi 插件：存在则优先使用）。
 * 静态首页分页使用 ?page= 参数（WP 规范），其余用 ?paged=。
 */
function qingya_pagination() {
	if ( function_exists( 'wp_pagenavi' ) ) {
		wp_pagenavi();
		return;
	}

	$args = array(
		'mid_size'           => 2,
		'prev_text'          => '&laquo; ' . __( '上一页', 'qingya' ),
		'next_text'          => __( '下一页', 'qingya' ) . ' &raquo;',
		'before_page_number' => '<span class="qy-page-num">',
		'after_page_number'  => '</span>',
	);

	// 静态首页（front-page 模板）：分页变量为 page。
	if ( is_front_page() && 'page' === get_option( 'show_on_front' ) ) {
		$args['base']   = add_query_arg( 'page', '%#%', home_url( '/' ) );
		$args['format'] = '?page=%#%';
	}

	the_posts_pagination( $args );
}

/**
 * 文章阅读时间（按 350 字/分钟估算）。
 *
 * @return string
 */
function qingya_reading_time() {
	$content = get_post_field( 'post_content', get_the_ID() );
	$words   = mb_strlen( wp_strip_all_tags( $content ), 'UTF-8' );
	$minutes = max( 1, (int) ceil( $words / 350 ) );
	/* translators: %d: 阅读分钟数。 */
	return sprintf( __( '%d 分钟阅读', 'qingya' ), $minutes );
}

/**
 * 首页轮播渲染（Customizer 配置）。
 * 供 front-page 模板与文章列表首页共用。
 */
function qingya_render_carousel() {
	if ( 'on' !== get_theme_mod( 'qy_front_carousel', 'on' ) ) {
		return;
	}

	// 内置默认图（Customizer 未配置时回退；图片自带文字，不叠加文字层）。
	$defaults = array(
		1 => array( QINGYA_URI . '/assets/img/carousel-1.webp', '', '', '' ),
		2 => array( QINGYA_URI . '/assets/img/carousel-2.webp', '', '', '' ),
		3 => array( QINGYA_URI . '/assets/img/carousel-3.webp', '', '', '' ),
		4 => array( QINGYA_URI . '/assets/img/carousel-4.webp', '', '', '' ),
	);

	$slides = array();
	for ( $i = 1; $i <= 4; $i++ ) {
		$image = get_theme_mod( 'qy_front_slide_' . $i . '_image', '' );
		if ( ! $image && isset( $defaults[ $i ] ) ) {
			$image = $defaults[ $i ][0];
		}
		if ( $image ) {
			$slides[] = array(
				'image' => $image,
				'title' => get_theme_mod( 'qy_front_slide_' . $i . '_title', isset( $defaults[ $i ] ) ? $defaults[ $i ][1] : '' ),
				'desc'  => get_theme_mod( 'qy_front_slide_' . $i . '_desc', isset( $defaults[ $i ] ) ? $defaults[ $i ][2] : '' ),
				'link'  => get_theme_mod( 'qy_front_slide_' . $i . '_link', isset( $defaults[ $i ] ) ? $defaults[ $i ][3] : '' ),
			);
		}
	}
	if ( empty( $slides ) ) {
		return;
	}
	?>
	<div class="qy-carousel" data-autoplay="5000">
		<div class="qy-carousel-track">
			<?php foreach ( $slides as $slide ) : ?>
				<div class="qy-carousel-slide">
					<?php if ( ! empty( $slide['link'] ) ) : ?>
						<a class="qy-carousel-link" href="<?php echo esc_url( $slide['link'] ); ?>">
							<img src="<?php echo esc_url( $slide['image'] ); ?>" alt="<?php echo esc_attr( $slide['title'] ); ?>" loading="eager">
						</a>
					<?php else : ?>
						<img src="<?php echo esc_url( $slide['image'] ); ?>" alt="<?php echo esc_attr( $slide['title'] ); ?>" loading="eager">
					<?php endif; ?>
					<?php if ( ! empty( $slide['title'] ) || ! empty( $slide['desc'] ) || ! empty( $slide['link'] ) ) : ?>
						<div class="qy-carousel-caption">
							<?php if ( ! empty( $slide['title'] ) ) : ?>
								<h3 class="qy-carousel-title"><?php echo esc_html( $slide['title'] ); ?></h3>
							<?php endif; ?>
							<?php if ( ! empty( $slide['desc'] ) ) : ?>
								<p class="qy-carousel-desc"><?php echo esc_html( $slide['desc'] ); ?></p>
							<?php endif; ?>
							<?php if ( ! empty( $slide['link'] ) ) : ?>
								<a class="qy-carousel-btn" href="<?php echo esc_url( $slide['link'] ); ?>"><?php esc_html_e( '阅读全文 →', 'qingya' ); ?></a>
							<?php endif; ?>
						</div>
					<?php endif; ?>
				</div>
			<?php endforeach; ?>
		</div>
		<button class="qy-carousel-prev" aria-label="<?php esc_attr_e( '上一张', 'qingya' ); ?>">‹</button>
		<button class="qy-carousel-next" aria-label="<?php esc_attr_e( '下一张', 'qingya' ); ?>">›</button>
		<div class="qy-carousel-dots" role="tablist"></div>
	</div>
	<?php
}

/**
 * 上一篇 / 下一篇导航。
 */
function qingya_post_nav() {
	$prev = get_previous_post();
	$next = get_next_post();
	if ( ! $prev && ! $next ) {
		return;
	}
	echo '<nav class="qy-post-nav" aria-label="' . esc_attr__( '文章导航', 'qingya' ) . '">';
	if ( $prev ) {
		echo '<div class="qy-post-nav-prev"><span class="qy-post-nav-label">' . esc_html__( '上一篇', 'qingya' ) . '</span><a href="' . esc_url( get_permalink( $prev ) ) . '" rel="prev">' . esc_html( get_the_title( $prev ) ) . '</a></div>';
	}
	if ( $next ) {
		echo '<div class="qy-post-nav-next"><span class="qy-post-nav-label">' . esc_html__( '下一篇', 'qingya' ) . '</span><a href="' . esc_url( get_permalink( $next ) ) . '" rel="next">' . esc_html( get_the_title( $next ) ) . '</a></div>';
	}
	echo '</nav>';
}

/**
 * 相关文章（同分类，随机，排除当前）。
 *
 * @param int $number 数量。
 */
function qingya_related_posts( $number = 4 ) {
	$post_id = get_the_ID();
	$cats    = wp_get_post_categories( $post_id );
	if ( empty( $cats ) ) {
		return;
	}

	$args = array(
		'post_type'           => 'post',
		'posts_per_page'      => absint( $number ),
		'post__not_in'        => array( $post_id ),
		'category__in'        => $cats,
		'orderby'             => 'rand',
		'ignore_sticky_posts' => true,
		'no_found_rows'       => true,
	);
	$query = new WP_Query( $args );

	if ( ! $query->have_posts() ) {
		wp_reset_postdata();
		return;
	}

	echo '<section class="qy-related"><h3 class="qy-block-title">' . esc_html__( '相关推荐', 'qingya' ) . '</h3><div class="qy-related-grid">';
	while ( $query->have_posts() ) {
		$query->the_post();
		echo '<article class="qy-related-item"><a href="' . esc_url( get_permalink() ) . '">';
		echo '<span class="qy-related-thumb">';
		qingya_post_thumbnail( 'qingya-card' );
		echo '</span>';
		echo '<span class="qy-related-title">' . esc_html( get_the_title() ) . '</span></a></article>';
	}
	echo '</div></section>';

	wp_reset_postdata();
}

/**
 * 社交分享按钮（简单链接，无第三方 SDK）。
 */
function qingya_share_buttons() {
	$url  = rawurlencode( get_permalink() );
	$text = rawurlencode( get_the_title() );

	echo '<div class="qy-share">';
	echo '<span class="qy-share-label">' . esc_html__( '分享：', 'qingya' ) . '</span>';
	printf(
		'<a class="qy-share-btn" href="https://service.weibo.com/share/share.php?url=%1$s&title=%2$s" target="_blank" rel="nofollow noopener">微博</a>',
		esc_attr( $url ),
		esc_attr( $text )
	);
	printf(
		'<a class="qy-share-btn" href="https://connect.qq.com/widget/shareqq/index.html?url=%1$s&title=%2$s" target="_blank" rel="nofollow noopener">QQ</a>',
		esc_attr( $url ),
		esc_attr( $text )
	);
	printf(
		'<a class="qy-share-btn" href="https://twitter.com/intent/tweet?url=%1$s&text=%2$s" target="_blank" rel="nofollow noopener">X</a>',
		esc_attr( $url ),
		esc_attr( $text )
	);
	echo '</div>';
}

/**
 * 返回顶部按钮（HTML 结构，JS 控制显隐）。
 */
function qingya_back_to_top() {
	echo '<button id="qy-back-to-top" class="qy-back-to-top" aria-label="' . esc_attr__( '返回顶部', 'qingya' ) . '">↑</button>';
}
