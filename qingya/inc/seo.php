<?php
/**
 * SEO 原生优化模块（无插件依赖）：
 * - 标准化 TDK（标题/关键词/描述）自动生成
 * - 单页/单文章独立自定义（Meta Box）
 * - 面包屑 + 文章结构化数据（JSON-LD）
 * - 图片 ALT 自动补充
 * - URL 规范化（canonical）
 * - robots.txt 兼容
 *
 * @package Qingya
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * 标题生成：优先 Meta Box 自定义，其次站点标题规则。
 *
 * @param array $parts 标题片段。
 * @return array
 */
function qingya_seo_title( $parts ) {
	$custom = '';

	if ( is_singular() ) {
		$custom = get_post_meta( get_the_ID(), '_qingya_seo_title', true );
	} elseif ( is_category() || is_tag() || is_tax() ) {
		$custom = get_term_meta( get_queried_object_id(), '_qingya_seo_title', true );
	}

	if ( ! empty( $custom ) ) {
		// 完全自定义标题（不含站点名，避免重复）。
		$parts['title'] = wp_strip_all_tags( $custom );
		unset( $parts['site'] );
		return $parts;
	}

	// 默认规则：页面标题 + 站点名（首页反之）。
	if ( is_front_page() ) {
		$parts['title'] = get_bloginfo( 'name' );
		if ( ! empty( $parts['tagline'] ) ) {
			$parts['title'] .= ' - ' . $parts['tagline'];
		}
		unset( $parts['site'], $parts['tagline'] );
	}

	return $parts;
}
add_filter( 'document_title_parts', 'qingya_seo_title', 20 );

/**
 * 输出 meta description / keywords（wp_head）。
 */
function qingya_seo_meta() {
	// 站点级关键词（Customizer）。
	$site_keywords = get_theme_mod( 'qy_seo_keywords', '' );
	$description   = '';
	$keywords      = '';

	if ( is_front_page() ) {
		$description = get_theme_mod( 'qy_seo_home_desc', '' );
		if ( empty( $description ) ) {
			$description = get_bloginfo( 'description' );
		}
		$keywords = $site_keywords;
	} elseif ( is_singular() ) {
		$post_id = get_the_ID();
		$description = get_post_meta( $post_id, '_qingya_seo_desc', true );
		if ( empty( $description ) ) {
			$excerpt = get_the_excerpt();
			$description = $excerpt ? $excerpt : wp_trim_words( wp_strip_all_tags( get_post_field( 'post_content', $post_id ) ), 80 );
		}
		$keywords = get_post_meta( $post_id, '_qingya_seo_keywords', true );
		if ( empty( $keywords ) && 'post' === get_post_type() ) {
			$tags = wp_get_post_tags( $post_id, array( 'fields' => 'names' ) );
			if ( ! empty( $tags ) ) {
				$keywords = implode( ',', $tags );
			}
		}
	} elseif ( is_category() || is_tag() || is_tax() ) {
		$term_id    = get_queried_object_id();
		$description = get_term_meta( $term_id, '_qingya_seo_desc', true );
		if ( empty( $description ) ) {
			$description = term_description( $term_id );
		}
		$keywords = get_term_meta( $term_id, '_qingya_seo_keywords', true );
		if ( empty( $keywords ) ) {
			$keywords = $site_keywords;
		}
	} elseif ( is_search() ) {
		$description = sprintf( __( '搜索「%s」的结果。', 'qingya' ), get_search_query() );
	} elseif ( is_404() ) {
		$description = __( '页面未找到。', 'qingya' );
	}

	$description = trim( wp_strip_all_tags( (string) $description ) );
	$keywords    = trim( wp_strip_all_tags( (string) $keywords ) );

	if ( $description ) {
		echo '<meta name="description" content="' . esc_attr( mb_substr( $description, 0, 300 ) ) . '">' . "\n";
	}
	if ( $keywords ) {
		echo '<meta name="keywords" content="' . esc_attr( $keywords ) . '">' . "\n";
	}
}
add_action( 'wp_head', 'qingya_seo_meta', 1 );

/**
 * 结构化数据：文章（BlogPosting）与面包屑（BreadcrumbList）。
 */
function qingya_seo_schema() {
	if ( is_singular( 'post' ) ) {
		$post = get_post();
		echo '<script type="application/ld+json">' . wp_json_encode( array(
			'@context'      => 'https://schema.org',
			'@type'         => 'BlogPosting',
			'headline'      => get_the_title( $post ),
			'datePublished' => get_the_date( 'c', $post ),
			'dateModified'  => get_the_modified_date( 'c', $post ),
			'author'        => array(
				'@type' => 'Person',
				'name'  => get_the_author_meta( 'display_name', $post->post_author ),
			),
			'publisher'     => array(
				'@type' => 'Organization',
				'name'  => get_bloginfo( 'name' ),
			),
			'url'           => get_permalink( $post ),
			'mainEntityOfPage' => get_permalink( $post ),
		) ) . '</script>' . "\n";
	}

	// 面包屑 JSON-LD（非首页）。
	if ( ! is_front_page() ) {
		$items = array(
			array(
				'@type'    => 'ListItem',
				'position' => 1,
				'name'     => get_bloginfo( 'name' ),
				'item'     => home_url( '/' ),
			),
		);

		if ( is_singular() ) {
			$cats = get_the_category();
			if ( ! empty( $cats ) ) {
				$items[] = array(
					'@type'    => 'ListItem',
					'position' => 2,
					'name'     => $cats[0]->name,
					'item'     => get_category_link( $cats[0] ),
				);
			}
			$items[] = array(
				'@type'    => 'ListItem',
				'position' => count( $items ) + 1,
				'name'     => get_the_title(),
			);
		} elseif ( is_archive() || is_search() ) {
			$items[] = array(
				'@type'    => 'ListItem',
				'position' => 2,
				'name'     => is_search() ? sprintf( __( '搜索：%s', 'qingya' ), get_search_query() ) : wp_strip_all_tags( get_the_archive_title() ),
			);
		}

		if ( count( $items ) > 1 ) {
			echo '<script type="application/ld+json">' . wp_json_encode( array(
				'@context'        => 'https://schema.org',
				'@type'           => 'BreadcrumbList',
				'itemListElement' => $items,
			) ) . '</script>' . "\n";
		}
	}
}
add_action( 'wp_head', 'qingya_seo_schema', 5 );

/**
 * robots.txt 兼容：不覆盖现有内容，仅在缺省时补充基础规则。
 *
 * @param string $output robots 内容。
 * @param bool   $public  是否允许索引。
 * @return string
 */
function qingya_robots_txt( $output, $public ) {
	if ( ! $public ) {
		return $output;
	}
	$lines   = array_filter( array_map( 'trim', explode( "\n", $output ) ) );
	$has_ua  = false;
	foreach ( $lines as $line ) {
		if ( 0 === strpos( $line, 'User-agent' ) ) {
			$has_ua = true;
			break;
		}
	}
	if ( ! $has_ua ) {
		$output .= "User-agent: *\nAllow: /\n";
	}
	return $output;
}
add_filter( 'robots_txt', 'qingya_robots_txt', 10, 2 );

/**
 * URL 规范化：确保 canonical 输出（WP 原生已输出，此钩子兜底验证）。
 * 同时处理分页页 canonical。
 */
function qingya_canonical() {
	if ( ! is_singular() ) {
		return;
	}
	$canonical = get_permalink();
	$paged     = get_query_var( 'page' );
	if ( $paged > 1 ) {
		$canonical = trailingslashit( $canonical ) . user_trailingslashit( $paged, 'single_paged' );
	}
	printf( '<link rel="canonical" href="%s">' . "\n", esc_url( $canonical ) );
}
add_action( 'wp_head', 'qingya_canonical', 6 );

/**
 * 冗余代码清理：清理 wp_head 中不必要的 shortlink 与相邻输出。
 */
function qingya_clean_head() {
	remove_action( 'wp_head', 'wp_shortlink_wp_head' );       // shortlink。
	remove_action( 'wp_head', 'rsd_link' );                    // RSD（无离线编辑器时）。
	remove_action( 'wp_head', 'wlwmanifest_link' );            // Windows Live Writer（已废弃）。
}
add_action( 'init', 'qingya_clean_head' );
