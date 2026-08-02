<?php
/**
 * 股市消息区（首页分区数据源）：
 * - 热门消息：东方财富 7x24 快讯 + 巨潮资讯公告
 * - 突发消息：新浪财经 7x24 快讯（按关键词标记突发）
 * - 博客文章：股票 / 行业分类
 *
 * 服务端抓取 + transient 缓存（默认 30 分钟，Customizer 可调），
 * 任一数据源失败自动降级隐藏，绝不影响页面渲染。
 *
 * @package Qingya
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * 缓存分钟数（Customizer 可配，最低 5 分钟）。
 *
 * @return int
 */
function qingya_stock_cache_minutes() {
	return max( 5, (int) get_theme_mod( 'qy_stock_cache_min', 30 ) );
}

/**
 * 通用抓取（带 transient 缓存）。失败返回 false。
 *
 * @param string $key  缓存键。
 * @param string $url  接口 URL。
 * @param array  $args wp_remote 参数。
 * @return string|false 响应体。
 */
function qingya_stock_fetch( $key, $url, $args = array() ) {
	$cache = get_transient( $key );
	if ( false !== $cache ) {
		return $cache;
	}

	$args = wp_parse_args( $args, array(
		'timeout'    => 12,
		'user-agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/126.0 Safari/537.36',
	) );

	$response = wp_remote_request( $url, $args );
	if ( is_wp_error( $response ) || 200 !== wp_remote_retrieve_response_code( $response ) ) {
		return false;
	}
	$body = wp_remote_retrieve_body( $response );
	if ( '' === $body ) {
		return false;
	}

	set_transient( $key, $body, qingya_stock_cache_minutes() * MINUTE_IN_SECONDS );
	return $body;
}

/**
 * 东方财富 7x24 快讯（热门消息）。
 *
 * @param int $count 数量。
 * @return array
 */
function qingya_stock_em_news( $count = 5 ) {
	$body = qingya_stock_fetch(
		'qy_stock_em',
		'https://newsapi.eastmoney.com/kuaixun/v1/getlist_102_ajaxResult_50_1_.html'
	);
	if ( ! $body ) {
		return array();
	}
	// 剥离 JSONP 包装：var ajaxResult={...};
	if ( 0 === strpos( $body, 'var ajaxResult=' ) ) {
		$body = substr( $body, strlen( 'var ajaxResult=' ) );
	}
	$body = trim( $body, " \t\n\r\0\x0B;" );
	$data = json_decode( $body, true );
	if ( empty( $data['LivesList'] ) || ! is_array( $data['LivesList'] ) ) {
		return array();
	}

	$out = array();
	foreach ( array_slice( $data['LivesList'], 0, $count ) as $item ) {
		$title  = isset( $item['title'] ) ? trim( wp_strip_all_tags( $item['title'] ) ) : '';
		$digest = isset( $item['digest'] ) ? trim( wp_strip_all_tags( $item['digest'] ) ) : '';
		$url    = isset( $item['url_w'] ) ? esc_url_raw( $item['url_w'] ) : '';
		if ( ! $title && ! $digest ) {
			continue;
		}
		$out[] = array(
			'title'  => $title ? $title : mb_substr( $digest, 0, 40 ),
			'digest' => $digest,
			'url'    => $url,
			'tag'    => '快讯',
		);
	}
	return $out;
}

/**
 * 巨潮资讯公告（热门消息：上市公司公告）。
 *
 * @param int $count 数量。
 * @return array
 */
function qingya_stock_cninfo( $count = 5 ) {
	$date = current_time( 'Y-m-d' );
	$body = qingya_stock_fetch(
		'qy_stock_cninfo',
		'http://www.cninfo.com.cn/new/hisAnnouncement/query',
		array(
			'method'  => 'POST',
			'headers' => array(
				'Referer'      => 'http://www.cninfo.com.cn/',
				'Content-Type' => 'application/x-www-form-urlencoded',
			),
			'body'    => array(
				'pageNum'   => '1',
				'pageSize'  => (string) $count,
				'column'    => 'szse',
				'tabName'   => 'fulltext',
				'plate'     => '',
				'stock'     => '',
				'searchkey' => '',
				'secid'     => '',
				'category'  => '',
				'trade'     => '',
				'seDate'    => $date . '~' . $date,
				'sortName'  => '',
				'sortType'  => '',
				'isHLtitle' => 'true',
			),
		)
	);
	if ( ! $body ) {
		return array();
	}
	$data = json_decode( $body, true );
	if ( empty( $data['announcements'] ) || ! is_array( $data['announcements'] ) ) {
		return array();
	}

	$out = array();
	foreach ( array_slice( $data['announcements'], 0, $count ) as $item ) {
		$title = isset( $item['announcementTitle'] ) ? trim( wp_strip_all_tags( $item['announcementTitle'] ) ) : '';
		$code  = isset( $item['secCode'] ) ? $item['secCode'] : '';
		$name  = isset( $item['secName'] ) ? $item['secName'] : '';
		$aid   = isset( $item['announcementId'] ) ? $item['announcementId'] : '';
		if ( ! $title ) {
			continue;
		}
		$url = '';
		if ( $aid ) {
			$url = 'http://www.cninfo.com.cn/new/disclosure/detail?stockCode=' . rawurlencode( $code ) . '&announcementId=' . rawurlencode( $aid );
		}
		$out[] = array(
			'title' => $title,
			'name'  => $name ? $name . '（' . $code . '）' : $code,
			'url'   => esc_url_raw( $url ),
			'tag'   => '公告',
		);
	}
	return $out;
}

/**
 * 新浪财经 7x24 快讯（突发消息源）。
 *
 * @param int $count 数量。
 * @return array
 */
function qingya_stock_sina( $count = 5 ) {
	$url  = 'https://zhibo.sina.com.cn/api/zhibo/feed?page=1&page_size=' . ( $count + 5 ) . '&zhibo_id=152&tag_id=0&dire=f&dpc=1';
	$body = qingya_stock_fetch( 'qy_stock_sina', $url );
	if ( ! $body ) {
		return array();
	}
	$data = json_decode( $body, true );
	$list = isset( $data['result']['data']['feed']['list'] ) ? $data['result']['data']['feed']['list'] : array();
	if ( empty( $list ) || ! is_array( $list ) ) {
		return array();
	}

	$out = array();
	foreach ( array_slice( $list, 0, $count ) as $item ) {
		$text = isset( $item['rich_text'] ) ? trim( wp_strip_all_tags( $item['rich_text'] ) ) : '';
		if ( ! $text ) {
			continue;
		}
		$text = preg_replace( '/\s+/', ' ', $text );
		$time = isset( $item['create_time'] ) ? trim( $item['create_time'] ) : '';
		if ( $time && preg_match( '/\d{2}:\d{2}:\d{2}/', $time, $m ) ) {
			$time = $m[0];
		}
		$out[] = array(
			'text' => mb_substr( $text, 0, 140 ),
			'time' => $time,
			'tag'  => qingya_stock_is_burst( $text ) ? '突发' : '快讯',
		);
	}
	return $out;
}

/**
 * 是否 A 股突发/敏感事件关键词。
 *
 * @param string $text 快讯文本。
 * @return bool
 */
function qingya_stock_is_burst( $text ) {
	$keywords = array( '突发', '停牌', '复牌', '异动', '涨停', '跌停', '立案', '重组', '停牌核查', '龙虎榜', '临停', '处罚', '警示函', '问询函', '退市', 'st', '*st' );
	foreach ( $keywords as $kw ) {
		if ( false !== mb_stripos( $text, $kw ) ) {
			return true;
		}
	}
	return false;
}

/**
 * 博客股票/行业分类文章（股市消息区第三块）。
 *
 * @param int $count 数量。
 * @return array
 */
function qingya_home_stock_posts( $count = 5 ) {
	$cats = qingya_home_cat_ids( get_theme_mod( 'qy_home_stock_cats', '股票,行业' ) );
	$args = array(
		'post_type'           => 'post',
		'posts_per_page'      => max( 1, absint( $count ) ),
		'ignore_sticky_posts' => true,
		'no_found_rows'       => true,
	);
	if ( ! empty( $cats ) ) {
		$args['category__in'] = $cats;
	}
	$query = new WP_Query( $args );
	$out   = array();
	while ( $query->have_posts() ) {
		$query->the_post();
		$out[] = array(
			'title' => get_the_title(),
			'url'   => get_permalink(),
			'date'  => get_the_date( 'm-d' ),
		);
	}
	wp_reset_postdata();
	return $out;
}

/**
 * 分类名 → ID 数组（支持逗号/换行分隔）。
 *
 * @param string $names 分类名。
 * @return array
 */
function qingya_home_cat_ids( $names ) {
	if ( ! $names ) {
		return array();
	}
	$ids = array();
	foreach ( preg_split( '/[\r\n,，]+/', $names ) as $name ) {
		$name = trim( $name );
		if ( ! $name ) {
			continue;
		}
		$cat = get_category_by_slug( sanitize_title( $name ) );
		if ( ! $cat ) {
			$cat = get_category_by_slug( $name );
		}
		if ( ! $cat && function_exists( 'term_exists' ) ) {
			// 按名称查找。
			$term = term_exists( $name, 'category' );
			if ( $term && ! is_wp_error( $term ) ) {
				$cat = get_category( (int) $term['term_id'] );
			}
		}
		if ( $cat && ! is_wp_error( $cat ) ) {
			$ids[] = (int) $cat->term_id;
		}
	}
	return array_values( array_unique( $ids ) );
}

/**
 * 股市消息区整体渲染（门户布局使用）。
 */
function qingya_stock_section() {
	if ( 'off' === get_theme_mod( 'qy_home_stock_on', 'on' ) ) {
		return;
	}

	$em    = qingya_stock_em_news( 5 );
	$cn    = qingya_stock_cninfo( 5 );
	$sina  = qingya_stock_sina( 5 );
	$posts = qingya_home_stock_posts( 5 );

	if ( empty( $em ) && empty( $cn ) && empty( $sina ) && empty( $posts ) ) {
		return;
	}
	?>
	<section class="qy-home-stock qy-section">
		<h2 class="qy-block-title"><?php esc_html_e( '股市消息', 'qingya' ); ?></h2>

		<div class="qy-stock-grid">
			<div class="qy-stock-col">
				<h3 class="qy-stock-col-title"><?php esc_html_e( '热门消息', 'qingya' ); ?></h3>
				<?php if ( ! empty( $em ) ) : ?>
					<ul class="qy-news-list">
						<?php foreach ( $em as $item ) : ?>
							<li class="qy-news-item">
								<a href="<?php echo esc_url( $item['url'] ); ?>" target="_blank" rel="noopener nofollow">
									<span class="qy-news-tag"><?php echo esc_html( $item['tag'] ); ?></span>
									<span class="qy-news-text"><?php echo esc_html( $item['title'] ); ?></span>
								</a>
							</li>
						<?php endforeach; ?>
					</ul>
				<?php else : ?>
					<p class="qy-stock-empty"><?php esc_html_e( '快讯源暂时不可用。', 'qingya' ); ?></p>
				<?php endif; ?>

				<h3 class="qy-stock-col-title"><?php esc_html_e( '上市公司公告', 'qingya' ); ?></h3>
				<?php if ( ! empty( $cn ) ) : ?>
					<ul class="qy-news-list">
						<?php foreach ( $cn as $item ) : ?>
							<li class="qy-news-item">
								<a href="<?php echo esc_url( $item['url'] ); ?>" target="_blank" rel="noopener nofollow">
									<span class="qy-news-tag qy-news-tag-cn"><?php echo esc_html( $item['tag'] ); ?></span>
									<span class="qy-news-text"><?php echo esc_html( $item['name'] ); ?>：<?php echo esc_html( $item['title'] ); ?></span>
								</a>
							</li>
						<?php endforeach; ?>
					</ul>
				<?php else : ?>
					<p class="qy-stock-empty"><?php esc_html_e( '今日暂无公告（周末/节假日休市）。', 'qingya' ); ?></p>
				<?php endif; ?>
			</div>

			<div class="qy-stock-col">
				<h3 class="qy-stock-col-title"><?php esc_html_e( '突发消息', 'qingya' ); ?></h3>
				<?php if ( ! empty( $sina ) ) : ?>
					<ul class="qy-news-list">
						<?php foreach ( $sina as $item ) : ?>
							<li class="qy-news-item">
								<span class="qy-news-tag<?php echo '突发' === $item['tag'] ? ' qy-news-tag-burst' : ''; ?>"><?php echo esc_html( $item['tag'] ); ?></span>
								<span class="qy-news-text"><?php echo esc_html( $item['text'] ); ?></span>
								<?php if ( $item['time'] ) : ?>
									<span class="qy-news-time"><?php echo esc_html( $item['time'] ); ?></span>
								<?php endif; ?>
							</li>
						<?php endforeach; ?>
					</ul>
				<?php else : ?>
					<p class="qy-stock-empty"><?php esc_html_e( '快讯源暂时不可用。', 'qingya' ); ?></p>
				<?php endif; ?>

				<h3 class="qy-stock-col-title"><?php esc_html_e( '本站观点', 'qingya' ); ?></h3>
				<?php if ( ! empty( $posts ) ) : ?>
					<ul class="qy-news-list">
						<?php foreach ( $posts as $item ) : ?>
							<li class="qy-news-item">
								<a href="<?php echo esc_url( $item['url'] ); ?>">
									<span class="qy-news-text"><?php echo esc_html( $item['title'] ); ?></span>
									<span class="qy-news-time"><?php echo esc_html( $item['date'] ); ?></span>
								</a>
							</li>
						<?php endforeach; ?>
					</ul>
				<?php else : ?>
					<p class="qy-stock-empty"><?php esc_html_e( '暂无股票/行业分类文章。', 'qingya' ); ?></p>
				<?php endif; ?>
			</div>
		</div>
	</section>
	<?php
}

/**
 * 股市快讯条（杂志/极简布局使用：仅东财快讯）。
 *
 * @param int $count 数量。
 */
function qingya_stock_ticker( $count = 5 ) {
	if ( 'off' === get_theme_mod( 'qy_home_stock_on', 'on' ) ) {
		return;
	}
	$em = qingya_stock_em_news( $count );
	if ( empty( $em ) ) {
		return;
	}
	?>
	<section class="qy-home-stock-ticker qy-section">
		<h2 class="qy-block-title"><?php esc_html_e( '财经快讯', 'qingya' ); ?></h2>
		<ul class="qy-news-list qy-news-list-inline">
			<?php foreach ( $em as $item ) : ?>
				<li class="qy-news-item">
					<a href="<?php echo esc_url( $item['url'] ); ?>" target="_blank" rel="noopener nofollow">
						<span class="qy-news-tag"><?php echo esc_html( $item['tag'] ); ?></span>
						<span class="qy-news-text"><?php echo esc_html( $item['title'] ); ?></span>
					</a>
				</li>
			<?php endforeach; ?>
		</ul>
	</section>
	<?php
}
