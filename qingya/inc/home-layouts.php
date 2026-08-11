<?php
/**
 * 首页分区渲染（门户/杂志/极简布局共用）：
 * - 侧边栏（PC 常驻、移动端折叠）
 * - 分类直达区
 * - 热门高赞区（点赞 + 评论）
 * - 开源项目区（推荐 / IT 分类）
 *
 * @package Qingya
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * 首页侧边栏（PC 可折叠，移动端折叠）。
 */
function qingya_home_sidebar() {
	?>
	<button class="qy-sidebar-toggle" type="button" aria-expanded="true" aria-controls="qy-home-sidebar">
		<span class="qy-sidebar-toggle-text"><?php esc_html_e( '◀ 收起侧边栏', 'qingya' ); ?></span>
	</button>
	<button class="qy-sidebar-fold" type="button" aria-expanded="false" aria-controls="qy-home-sidebar">
		<?php esc_html_e( '☰ 侧边栏', 'qingya' ); ?>
	</button>
	<aside id="qy-home-sidebar" class="qy-home-sidebar" role="complementary">
		<?php if ( is_active_sidebar( 'sidebar-1' ) ) : ?>
			<?php dynamic_sidebar( 'sidebar-1' ); ?>
		<?php else : ?>
			<section class="widget">
				<h3 class="widget-title"><?php esc_html_e( '侧边栏', 'qingya' ); ?></h3>
				<p><?php esc_html_e( '请在「外观 → 小工具」中添加内容。', 'qingya' ); ?></p>
			</section>
		<?php endif; ?>
	</aside>
	<?php
}

/**
 * 分类直达：分类小按钮（chips），点击进入分类归档页查看列表。
 * 不再内嵌各分类最新文章（翁老反馈：点击分类出列表）。
 */
function qingya_home_cats_quick() {
	$count = max( 3, (int) get_theme_mod( 'qy_home_cat_count', 6 ) );
	$cats  = get_categories( array(
		'hide_empty' => true,
		'orderby'    => 'count',
		'order'      => 'DESC',
		'number'     => $count,
	) );
	if ( empty( $cats ) || is_wp_error( $cats ) ) {
		return;
	}
	?>
	<section class="qy-home-cats qy-section">
		<h2 class="qy-block-title"><?php esc_html_e( '分类直达', 'qingya' ); ?></h2>
		<div class="qy-cat-chips">
			<?php foreach ( $cats as $cat ) : ?>
				<a class="qy-cat-chip" href="<?php echo esc_url( get_category_link( $cat ) ); ?>">
					<?php echo esc_html( $cat->name ); ?>
					<span class="qy-cat-chip-count"><?php echo esc_html( $cat->count ); ?></span>
				</a>
			<?php endforeach; ?>
		</div>
	</section>
	<?php
}

/**
 * 分类按钮组（chips，小字号）：供最新文章标题行使用。
 */
function qingya_home_cat_chips() {
	$count = max( 3, (int) get_theme_mod( 'qy_home_cat_count', 6 ) );
	$cats  = get_categories( array(
		'hide_empty' => true,
		'orderby'    => 'count',
		'order'      => 'DESC',
		'number'     => $count,
	) );
	if ( empty( $cats ) || is_wp_error( $cats ) ) {
		return;
	}
	?>
	<div class="qy-latest-cats">
		<?php foreach ( $cats as $cat ) : ?>
			<a class="qy-cat-chip" href="<?php echo esc_url( get_category_link( $cat ) ); ?>">
				<?php echo esc_html( $cat->name ); ?>
				<span class="qy-cat-chip-count"><?php echo esc_html( $cat->count ); ?></span>
			</a>
		<?php endforeach; ?>
	</div>
	<?php
}

/**
 * 最新文章列表（门户布局：分类直达前展示最新文章，默认 10 篇，标题行后跟分类按钮）。
 *
 * @param int $count 数量。
 */
function qingya_home_latest_list( $count = 10 ) {
	$qy_paged = max( 1, get_query_var( 'paged' ) );
	if ( is_front_page() && 'page' === get_option( 'show_on_front' ) ) {
		$qy_paged = max( 1, get_query_var( 'page' ) );
	}
	$query = new WP_Query( array(
		'post_type'           => 'post',
		'posts_per_page'      => absint( $count ),
		'paged'               => $qy_paged,
		'ignore_sticky_posts' => false,
		'no_found_rows'       => false,
	) );
	if ( ! $query->have_posts() ) {
		return;
	}
	?>
	<section class="qy-home-latest-list qy-section">
		<div class="qy-latest-head">
			<h2 class="qy-block-title"><?php echo esc_html( get_theme_mod( 'qy_front_section_title', __( '最新文章', 'qingya' ) ) ); ?></h2>
			<?php qingya_home_cat_chips(); ?>
		</div>
		<div class="qy-simple-list">
			<?php
			while ( $query->have_posts() ) :
				$query->the_post();
				?>
				<article class="qy-simple-item">
					<a href="<?php the_permalink(); ?>">
						<span class="qy-simple-thumb"><?php qingya_post_thumbnail( 'thumbnail' ); ?></span>
						<span class="qy-simple-body">
							<span class="qy-simple-title"><?php the_title(); ?></span>
							<span class="qy-simple-excerpt"><?php echo esc_html( wp_trim_words( get_the_excerpt(), 30 ) ); ?></span>
							<span class="qy-simple-meta">
								<time datetime="<?php echo esc_attr( get_the_date( 'c' ) ); ?>"><?php echo esc_html( get_the_date() ); ?></time>
								<span><?php echo esc_html( qingya_views_text( get_the_ID() ) ); ?></span>
								<span>💬 <?php echo esc_html( number_format_i18n( get_comments_number() ) ); ?></span>
							</span>
						</span>
					</a>
				</article>
			<?php endwhile; ?>
		</div>
		<?php
		// 首页最新文章翻页。
		if ( $query->max_num_pages > 1 ) {
			$qy_pag_args = array(
				'total'              => $query->max_num_pages,
				'current'            => $qy_paged,
				'mid_size'           => 2,
				'prev_text'          => '&laquo; ' . __( '上一页', 'qingya' ),
				'next_text'          => __( '下一页', 'qingya' ) . ' &raquo;',
				'before_page_number' => '<span class="qy-page-num">',
				'after_page_number'  => '</span>',
			);
			if ( is_front_page() && 'page' === get_option( 'show_on_front' ) ) {
				$qy_pag_args['base']   = add_query_arg( 'page', '%#%', home_url( '/' ) );
				$qy_pag_args['format'] = '?page=%#%';
			}
			the_posts_pagination( $qy_pag_args );
		}
		?>
	</section>
	<?php
	wp_reset_postdata();
}

/**
 * 热门高赞区：按点赞数（_qingya_likes）排序，列表显示（含评论数）。
 */
function qingya_home_hot() {
	$count = max( 3, (int) get_theme_mod( 'qy_home_hot_count', 5 ) );
	$query = new WP_Query( array(
		'post_type'           => 'post',
		'posts_per_page'      => $count,
		'ignore_sticky_posts' => true,
		'no_found_rows'       => true,
		'meta_key'            => '_qingya_likes',
		'orderby'             => 'meta_value_num',
		'order'               => 'DESC',
	) );
	if ( ! $query->have_posts() ) {
		return;
	}
	?>
	<section class="qy-home-hot qy-section">
		<h2 class="qy-block-title"><?php esc_html_e( '热门高赞', 'qingya' ); ?></h2>
		<ol class="qy-hot-list">
			<?php
			while ( $query->have_posts() ) :
				$query->the_post();
				$likes = (int) get_post_meta( get_the_ID(), '_qingya_likes', true );
				$cnum  = (int) get_comments_number();
				?>
				<li class="qy-hot-item">
					<a class="qy-hot-item-title" href="<?php the_permalink(); ?>"><?php the_title(); ?></a>
					<span class="qy-hot-item-meta">
						<span class="qy-hot-like">👍 <?php echo esc_html( number_format_i18n( $likes ) ); ?></span>
						<span class="qy-hot-comment">💬 <?php echo esc_html( number_format_i18n( $cnum ) ); ?></span>
						<span class="qy-hot-views"><?php echo esc_html( qingya_views_text( get_the_ID() ) ); ?></span>
					</span>
				</li>
			<?php endwhile; ?>
		</ol>
	</section>
	<?php
	wp_reset_postdata();
}

/**
 * 开源项目区：「推荐」+「IT」分类中，按「开源」关键词筛选的文章（翁老规则）：
 * 题目/正文/摘要含「开源」∪ 标签名含「开源」；分类不存在或无匹配时显示空，不退化全站最新。
 */
function qingya_home_projects() {
	$cats = qingya_home_cat_ids( get_theme_mod( 'qy_home_project_cats', '推荐,IT' ) );
	// 翁老规则：分类不存在/无匹配时显示空（不退化全站最新）。
	if ( empty( $cats ) ) {
		return;
	}

	// 筛选「开源」：① 题目/正文/摘要含「开源」；② 标签名含「开源」。两种命中任一即可。
	$ids = array();
	$base = array(
		'post_type'           => 'post',
		'posts_per_page'      => 10,
		'ignore_sticky_posts' => true,
		'no_found_rows'       => true,
		'fields'              => 'ids',
		'category__in'        => $cats,
	);
	$q1 = new WP_Query( $base + array( 's' => '开源' ) );
	if ( ! empty( $q1->posts ) ) {
		$ids = array_merge( $ids, $q1->posts );
	}
	global $wpdb;
	$tag_ids = $wpdb->get_col( "SELECT term_id FROM {$wpdb->terms} WHERE name LIKE '%开源%'" );
	if ( ! empty( $tag_ids ) ) {
		$q2 = new WP_Query( $base + array( 'tag__in' => array_map( 'intval', $tag_ids ) ) );
		if ( ! empty( $q2->posts ) ) {
			$ids = array_merge( $ids, $q2->posts );
		}
	}
	$ids = array_values( array_unique( array_map( 'intval', $ids ) ) );
	if ( empty( $ids ) ) {
		return; // 翁老规则：无匹配文章显示空。
	}

	$query = new WP_Query( array(
		'post_type'           => 'post',
		'posts_per_page'      => 6,
		'ignore_sticky_posts' => true,
		'no_found_rows'       => true,
		'post__in'            => $ids,
		'orderby'             => 'date',
		'order'               => 'DESC',
	) );
	if ( ! $query->have_posts() ) {
		return;
	}
	?>
	<section class="qy-home-projects qy-section">
		<h2 class="qy-block-title"><?php esc_html_e( '开源项目', 'qingya' ); ?></h2>
		<ul class="qy-project-list">
			<?php
			while ( $query->have_posts() ) :
				$query->the_post();
				?>
				<li class="qy-project-item">
					<a class="qy-project-item-title" href="<?php the_permalink(); ?>"><?php the_title(); ?></a>
					<span class="qy-project-item-meta">
						<?php the_category( ' / ' ); ?>
						<span class="qy-project-date"><?php echo esc_html( get_the_date() ); ?></span>
					</span>
				</li>
			<?php endwhile; ?>
		</ul>
	</section>
	<?php
	wp_reset_postdata();
}

/**
 * 最新文章网格（杂志布局用）。
 *
 * @param int $count 数量。
 */
function qingya_home_latest_grid( $count = 8 ) {
	$qy_paged = max( 1, get_query_var( 'paged' ) );
	if ( is_front_page() && 'page' === get_option( 'show_on_front' ) ) {
		$qy_paged = max( 1, get_query_var( 'page' ) );
	}
	$query = new WP_Query( array(
		'post_type'           => 'post',
		'posts_per_page'      => absint( $count ),
		'paged'               => $qy_paged,
		'ignore_sticky_posts' => false,
		'no_found_rows'       => false,
	) );
	if ( ! $query->have_posts() ) {
		return;
	}
	?>
	<section class="qy-home-latest qy-section">
		<h2 class="qy-block-title"><?php echo esc_html( get_theme_mod( 'qy_front_section_title', __( '最新文章', 'qingya' ) ) ); ?></h2>
		<div class="qy-latest-grid">
			<?php
			while ( $query->have_posts() ) :
				$query->the_post();
				?>
				<article class="qy-latest-card">
					<a href="<?php the_permalink(); ?>">
						<span class="qy-latest-thumb"><?php qingya_post_thumbnail( 'qingya-card' ); ?></span>
						<span class="qy-latest-title"><?php the_title(); ?></span>
						<span class="qy-latest-excerpt"><?php echo esc_html( wp_trim_words( get_the_excerpt(), 22 ) ); ?></span>
						<span class="qy-latest-meta">
							<time datetime="<?php echo esc_attr( get_the_date( 'c' ) ); ?>"><?php echo esc_html( get_the_date() ); ?></time>
							<span><?php echo esc_html( qingya_views_text( get_the_ID() ) ); ?></span>
						</span>
					</a>
				</article>
			<?php endwhile; ?>
		</div>
		<?php
		// 首页最新文章翻页。
		if ( $query->max_num_pages > 1 ) {
			$qy_pag_args = array(
				'total'              => $query->max_num_pages,
				'current'            => $qy_paged,
				'mid_size'           => 2,
				'prev_text'          => '&laquo; ' . __( '上一页', 'qingya' ),
				'next_text'          => __( '下一页', 'qingya' ) . ' &raquo;',
				'before_page_number' => '<span class="qy-page-num">',
				'after_page_number'  => '</span>',
			);
			if ( is_front_page() && 'page' === get_option( 'show_on_front' ) ) {
				$qy_pag_args['base']   = add_query_arg( 'page', '%#%', home_url( '/' ) );
				$qy_pag_args['format'] = '?page=%#%';
			}
			the_posts_pagination( $qy_pag_args );
		}
		?>
	</section>
	<?php
	wp_reset_postdata();
}

/**
 * 杂志布局：图片墙（瀑布流交错），标题叠加图上，无摘要，点击阅读。
 *
 * @param int $count 数量。
 */
function qingya_home_masonry( $count = 12 ) {
	$query = new WP_Query( array(
		'post_type'           => 'post',
		'posts_per_page'      => absint( $count ),
		'ignore_sticky_posts' => false,
		'no_found_rows'       => true,
	) );
	if ( ! $query->have_posts() ) {
		return;
	}
	?>
	<section class="qy-home-masonry qy-section">
		<h2 class="qy-block-title"><?php echo esc_html( get_theme_mod( 'qy_front_section_title', __( '最新文章', 'qingya' ) ) ); ?></h2>
		<div class="qy-masonry-grid">
			<?php
			$i = 0;
			while ( $query->have_posts() ) :
				$query->the_post();
				$i++;
				?>
				<article class="qy-masonry-item<?php echo 1 === $i ? ' qy-masonry-featured' : ''; ?>">
					<a href="<?php the_permalink(); ?>">
						<span class="qy-masonry-thumb"><?php qingya_post_thumbnail( 'post-thumbnail' ); ?></span>
						<span class="qy-masonry-overlay">
							<span class="qy-masonry-title"><?php the_title(); ?></span>
							<span class="qy-masonry-meta">
								<span><?php echo esc_html( get_the_date() ); ?></span>
								<span><?php echo esc_html( qingya_views_text( get_the_ID() ) ); ?></span>
							</span>
						</span>
					</a>
				</article>
			<?php endwhile; ?>
		</div>
	</section>
	<?php
	wp_reset_postdata();
}

/**
 * 头条区（杂志布局用：首篇大图 + 两篇小图）。
 */
function qingya_home_headline() {
	$query = new WP_Query( array(
		'post_type'           => 'post',
		'posts_per_page'      => 3,
		'ignore_sticky_posts' => false,
		'no_found_rows'       => true,
	) );
	if ( ! $query->have_posts() ) {
		return;
	}
	$i = 0;
	?>
	<section class="qy-home-headline qy-section">
		<?php while ( $query->have_posts() ) : $query->the_post(); ?>
			<?php if ( 0 === $i ) : ?>
				<article class="qy-headline-main">
					<a href="<?php the_permalink(); ?>">
						<span class="qy-headline-thumb"><?php qingya_post_thumbnail( 'post-thumbnail' ); ?></span>
						<span class="qy-headline-body">
							<span class="qy-headline-title"><?php the_title(); ?></span>
							<span class="qy-headline-excerpt"><?php echo esc_html( wp_trim_words( get_the_excerpt(), 40 ) ); ?></span>
							<span class="qy-headline-meta">
								<time datetime="<?php echo esc_attr( get_the_date( 'c' ) ); ?>"><?php echo esc_html( get_the_date() ); ?></time>
								<span><?php echo esc_html( qingya_views_text( get_the_ID() ) ); ?></span>
								<span>💬 <?php echo esc_html( number_format_i18n( get_comments_number() ) ); ?></span>
							</span>
						</span>
					</a>
				</article>
			<?php else : ?>
				<article class="qy-headline-sub">
					<a href="<?php the_permalink(); ?>">
						<span class="qy-headline-thumb"><?php qingya_post_thumbnail( 'qingya-card' ); ?></span>
						<span class="qy-headline-body">
							<span class="qy-headline-title"><?php the_title(); ?></span>
							<span class="qy-headline-meta"><time datetime="<?php echo esc_attr( get_the_date( 'c' ) ); ?>"><?php echo esc_html( get_the_date() ); ?></time></span>
						</span>
					</a>
				</article>
			<?php endif; ?>
			<?php $i++; ?>
		<?php endwhile; ?>
	</section>
	<?php
	wp_reset_postdata();
}

/**
 * 极简布局：分类 chips + 文章列表。
 */
function qingya_home_simple_list() {
	$cats = get_categories( array(
		'hide_empty' => true,
		'orderby'    => 'count',
		'order'      => 'DESC',
		'number'     => 8,
	) );
	?>
	<section class="qy-home-simple qy-section">
		<?php if ( ! empty( $cats ) && ! is_wp_error( $cats ) ) : ?>
			<div class="qy-cat-chips">
				<?php foreach ( $cats as $cat ) : ?>
					<a class="qy-cat-chip" href="<?php echo esc_url( get_category_link( $cat ) ); ?>"><?php echo esc_html( $cat->name ); ?></a>
				<?php endforeach; ?>
			</div>
		<?php endif; ?>

		<?php if ( have_posts() ) : ?>
			<div class="qy-simple-list">
				<?php
				while ( have_posts() ) :
					the_post();
					?>
					<article class="qy-simple-item">
						<a href="<?php the_permalink(); ?>">
							<span class="qy-simple-thumb"><?php qingya_post_thumbnail( 'thumbnail' ); ?></span>
							<span class="qy-simple-body">
								<span class="qy-simple-title"><?php the_title(); ?></span>
								<span class="qy-simple-excerpt"><?php echo esc_html( wp_trim_words( get_the_excerpt(), 30 ) ); ?></span>
								<span class="qy-simple-meta">
									<time datetime="<?php echo esc_attr( get_the_date( 'c' ) ); ?>"><?php echo esc_html( get_the_date() ); ?></time>
									<span><?php echo esc_html( qingya_views_text( get_the_ID() ) ); ?></span>
									<span>💬 <?php echo esc_html( number_format_i18n( get_comments_number() ) ); ?></span>
								</span>
							</span>
						</a>
					</article>
				<?php endwhile; ?>
			</div>
			<?php qingya_pagination(); ?>
		<?php else : ?>
			<p class="qy-empty"><?php esc_html_e( '暂无内容，先去后台发布一篇文章吧。', 'qingya' ); ?></p>
		<?php endif; ?>
	</section>
	<?php
}
