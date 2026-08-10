<?php
/**
 * 侧边栏小工具扩展：
 * - 热门文章（按浏览量）
 * - 最新文章
 * - 随机文章
 * 全部基于 WP 原生 WP_Widget，零外部依赖。
 *
 * @package Qingya
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * 通用文章列表小工具基类。
 */
abstract class Qingya_Posts_Widget extends WP_Widget {

	/**
	 * 查询参数（子类实现）。
	 *
	 * @param array $instance 实例配置。
	 * @return array
	 */
	abstract protected function qingya_query_args( $instance );

	/**
	 * 默认标题（子类可覆写）。标题为空时渲染回退此值。
	 *
	 * @return string
	 */
	protected function qingya_default_title() {
		return '';
	}

	/**
	 * 渲染列表。
	 *
	 * @param array $args     侧边栏参数。
	 * @param array $instance 实例配置。
	 */
	public function widget( $args, $instance ) {
		// 标题为空时回退子类默认标题，避免「有列表无标题」。
		$title = ! empty( $instance['title'] ) ? $instance['title'] : $this->qingya_default_title();
		$count = isset( $instance['count'] ) ? absint( $instance['count'] ) : 5;

		$query_args = wp_parse_args( $this->qingya_query_args( $instance ), array(
			'posts_per_page'      => $count,
			'ignore_sticky_posts' => true,
			'no_found_rows'       => true,
		) );
		$query = new WP_Query( $query_args );

		if ( ! $query->have_posts() ) {
			return;
		}

		echo $args['before_widget']; // phpcs:ignore WordPress.Security.EscapeOutput
		if ( $title ) {
			echo $args['before_title'] . esc_html( $title ) . $args['after_title']; // phpcs:ignore WordPress.Security.EscapeOutput
		}
		echo '<ul class="qy-widget-posts">';
		while ( $query->have_posts() ) {
			$query->the_post();
			echo '<li><a href="' . esc_url( get_permalink() ) . '">' . esc_html( get_the_title() ) . '</a>';
			echo '<span class="qy-widget-post-meta">' . esc_html( get_the_date() );
			if ( 'qy_hot' === $this->id_base ) {
				echo ' · ' . esc_html( qingya_views_text() );
			}
			echo '</span></li>';
		}
		echo '</ul>';
		echo $args['after_widget']; // phpcs:ignore WordPress.Security.EscapeOutput

		wp_reset_postdata();
	}

	/**
	 * 表单。
	 *
	 * @param array $instance 实例配置。
	 */
	public function form( $instance ) {
		$title = isset( $instance['title'] ) ? $instance['title'] : '';
		$count = isset( $instance['count'] ) ? absint( $instance['count'] ) : 5;
		?>
		<p>
			<label for="<?php echo esc_attr( $this->get_field_id( 'title' ) ); ?>"><?php esc_html_e( '标题：', 'qingya' ); ?></label>
			<input class="widefat" id="<?php echo esc_attr( $this->get_field_id( 'title' ) ); ?>" name="<?php echo esc_attr( $this->get_field_name( 'title' ) ); ?>" type="text" value="<?php echo esc_attr( $title ); ?>">
		</p>
		<p>
			<label for="<?php echo esc_attr( $this->get_field_id( 'count' ) ); ?>"><?php esc_html_e( '显示数量：', 'qingya' ); ?></label>
			<input class="tiny-text" id="<?php echo esc_attr( $this->get_field_id( 'count' ) ); ?>" name="<?php echo esc_attr( $this->get_field_name( 'count' ) ); ?>" type="number" min="1" max="20" value="<?php echo esc_attr( $count ); ?>">
		</p>
		<?php
	}

	/**
	 * 更新。
	 *
	 * @param array $new_instance 新配置。
	 * @param array $old_instance 旧配置。
	 * @return array
	 */
	public function update( $new_instance, $old_instance ) {
		$instance          = array();
		$instance['title'] = isset( $new_instance['title'] ) ? sanitize_text_field( $new_instance['title'] ) : '';
		$instance['count'] = isset( $new_instance['count'] ) ? min( 20, absint( $new_instance['count'] ) ) : 5;
		return $instance;
	}
}

/**
 * 热门文章（按浏览量）。
 */
class Qingya_Widget_Hot extends Qingya_Posts_Widget {

	/**
	 * 构造。
	 */
	public function __construct() {
		parent::__construct( 'qy_hot', __( '青崖：热门文章', 'qingya' ), array(
			'description' => __( '按浏览量排序的热门文章', 'qingya' ),
		) );
	}

	/**
	 * 查询参数。
	 *
	 * @param array $instance 配置。
	 * @return array
	 */
	protected function qingya_query_args( $instance ) {
		return array(
			'post_type'      => 'post',
			'orderby'        => 'meta_value_num',
			'meta_key'       => '_qingya_views', // phpcs:ignore WordPress.DB.SlowDBQuery
			'order'          => 'DESC',
		);
	}

	/**
	 * 默认标题。
	 *
	 * @return string
	 */
	protected function qingya_default_title() {
		return __( '热门文章', 'qingya' );
	}
}

/**
 * 最新文章。
 */
class Qingya_Widget_Recent extends Qingya_Posts_Widget {

	/**
	 * 构造。
	 */
	public function __construct() {
		parent::__construct( 'qy_recent', __( '青崖：最新文章', 'qingya' ), array(
			'description' => __( '最新发布的文章', 'qingya' ),
		) );
	}

	/**
	 * 查询参数。
	 *
	 * @param array $instance 配置。
	 * @return array
	 */
	protected function qingya_query_args( $instance ) {
		return array(
			'post_type' => 'post',
			'orderby'   => 'date',
			'order'     => 'DESC',
		);
	}

	/**
	 * 默认标题。
	 *
	 * @return string
	 */
	protected function qingya_default_title() {
		return __( '近期文章', 'qingya' );
	}
}

/**
 * 随机文章。
 */
class Qingya_Widget_Random extends Qingya_Posts_Widget {

	/**
	 * 构造。
	 */
	public function __construct() {
		parent::__construct( 'qy_random', __( '青崖：随机文章', 'qingya' ), array(
			'description' => __( '随机推荐文章', 'qingya' ),
		) );
	}

	/**
	 * 查询参数。
	 *
	 * @param array $instance 配置。
	 * @return array
	 */
	protected function qingya_query_args( $instance ) {
		return array(
			'post_type' => 'post',
			'orderby'   => 'rand',
		);
	}

	/**
	 * 默认标题。
	 *
	 * @return string
	 */
	protected function qingya_default_title() {
		return __( '随机文章', 'qingya' );
	}
}

/**
 * 热门话题（abp_topic 分类法，按文章数排序）。
 * 参照星河AI工具箱「制造话题、引导互动」：侧边栏入口直达话题聚合页。
 */
class Qingya_Widget_Hot_Topics extends WP_Widget {

	/**
	 * 构造。
	 */
	public function __construct() {
		parent::__construct( 'qy_hot_topics', __( '青崖：热门话题', 'qingya' ), array(
			'description' => __( '热门话题：A-Blog 话题分类或星河AI工具箱 thread 话题（自动适配）', 'qingya' ),
		) );
	}

	/**
	 * 渲染。
	 *
	 * @param array $args     侧边栏参数。
	 * @param array $instance 配置。
	 */
	public function widget( $args, $instance ) {
		// 标题为空时回退默认「热门话题」，避免手动添加/旧实例漏填标题导致「有列表无标题」。
		$title = ! empty( $instance['title'] ) ? $instance['title'] : __( '热门话题', 'qingya' );
		$count = isset( $instance['count'] ) ? absint( $instance['count'] ) : 6;

		// 数据源自适应：优先 A-Blog 的 abp_topic 分类法；不存在/为空时回退
		// 星河AI工具箱的 thread 自定义文章类型（线上话题实为 /thread/xxx）。
		$topics = array();
		$is_thread = false;
		if ( taxonomy_exists( 'abp_topic' ) ) {
			$terms = get_terms( array(
				'taxonomy'   => 'abp_topic',
				'hide_empty' => true,
				'orderby'    => 'count',
				'order'      => 'DESC',
				'number'     => $count,
			) );
			if ( ! empty( $terms ) && ! is_wp_error( $terms ) ) {
				$topics = $terms;
			}
		}
		if ( empty( $topics ) && post_type_exists( 'thread' ) ) {
			$is_thread = true;
			$q = new WP_Query( array(
				'post_type'           => 'thread',
				'posts_per_page'      => $count,
				'ignore_sticky_posts' => true,
				'no_found_rows'       => true,
				'orderby'             => 'comment_count',
				'order'               => 'DESC',
			) );
			if ( $q->have_posts() ) {
				$topics = $q->posts;
			}
			wp_reset_postdata();
		}
		if ( empty( $topics ) ) {
			return;
		}

		echo $args['before_widget']; // phpcs:ignore WordPress.Security.EscapeOutput
		if ( $title ) {
			echo $args['before_title'] . esc_html( $title ) . $args['after_title']; // phpcs:ignore WordPress.Security.EscapeOutput
		}
		echo '<ul class="qy-widget-posts">';
		foreach ( $topics as $t ) {
			$link = $is_thread ? get_permalink( $t->ID ) : get_term_link( $t );
			$name = $is_thread ? get_the_title( $t->ID ) : $t->name;
			echo '<li><a href="' . esc_url( $link ) . '">' . ( $is_thread ? '' : '#' ) . esc_html( $name ) . '</a></li>';
		}
		echo '</ul>';
		echo $args['after_widget']; // phpcs:ignore WordPress.Security.EscapeOutput
	}

	/**
	 * 表单。
	 *
	 * @param array $instance 配置。
	 */
	public function form( $instance ) {
		$title = isset( $instance['title'] ) ? $instance['title'] : __( '热门话题', 'qingya' );
		$count = isset( $instance['count'] ) ? absint( $instance['count'] ) : 6;
		?>
		<p>
			<label for="<?php echo esc_attr( $this->get_field_id( 'title' ) ); ?>"><?php esc_html_e( '标题：', 'qingya' ); ?></label>
			<input class="widefat" id="<?php echo esc_attr( $this->get_field_id( 'title' ) ); ?>" name="<?php echo esc_attr( $this->get_field_name( 'title' ) ); ?>" type="text" value="<?php echo esc_attr( $title ); ?>">
		</p>
		<p>
			<label for="<?php echo esc_attr( $this->get_field_id( 'count' ) ); ?>"><?php esc_html_e( '显示数量：', 'qingya' ); ?></label>
			<input class="tiny-text" id="<?php echo esc_attr( $this->get_field_id( 'count' ) ); ?>" name="<?php echo esc_attr( $this->get_field_name( 'count' ) ); ?>" type="number" min="1" max="20" value="<?php echo esc_attr( $count ); ?>">
		</p>
		<?php
	}

	/**
	 * 更新。
	 *
	 * @param array $new_instance 新配置。
	 * @param array $old_instance 旧配置。
	 * @return array
	 */
	public function update( $new_instance, $old_instance ) {
		$instance          = array();
		$instance['title'] = isset( $new_instance['title'] ) ? sanitize_text_field( $new_instance['title'] ) : '';
		$instance['count'] = isset( $new_instance['count'] ) ? min( 20, absint( $new_instance['count'] ) ) : 6;
		return $instance;
	}
}

/**
 * 近期评论（与近期文章/热门话题同结构：qy-widget-posts 统一列表样式）。
 */
class Qingya_Widget_Recent_Comments extends WP_Widget {

	/**
	 * 构造。
	 */
	public function __construct() {
		parent::__construct( 'qy_recent_comments', __( '青崖：近期评论', 'qingya' ), array(
			'description' => __( '最新评论（含作者与文章）', 'qingya' ),
		) );
	}

	/**
	 * 渲染。
	 *
	 * @param array $args     侧边栏参数。
	 * @param array $instance 配置。
	 */
	public function widget( $args, $instance ) {
		$title = ! empty( $instance['title'] ) ? $instance['title'] : __( '近期评论', 'qingya' );
		$count = isset( $instance['count'] ) ? absint( $instance['count'] ) : 5;

		// 注意：不能加 type=>'comment' 过滤——A-Blog AI 评论的 comment_type 是
		// 'ai_comment'，过滤后全部排除 → widget 不渲染（翁老反馈「热门评论不显示」）。
		$comments = get_comments( array(
			'number'      => $count,
			'status'      => 'approve',
			'post_status' => 'publish',
		) );
		if ( empty( $comments ) ) {
			return;
		}

		echo $args['before_widget']; // phpcs:ignore WordPress.Security.EscapeOutput
		if ( $title ) {
			echo $args['before_title'] . esc_html( $title ) . $args['after_title']; // phpcs:ignore WordPress.Security.EscapeOutput
		}
		echo '<ul class="qy-widget-posts">';
		foreach ( $comments as $c ) {
			$text = mb_substr( trim( wp_strip_all_tags( $c->comment_content ) ), 0, 30 );
			echo '<li><a href="' . esc_url( get_comment_link( $c ) ) . '">' . esc_html( $text ) . '</a>';
			echo '<span class="qy-widget-post-meta">' . esc_html( $c->comment_author ) . ' · ' . esc_html( get_the_title( $c->comment_post_ID ) ) . '</span></li>';
		}
		echo '</ul>';
		echo $args['after_widget']; // phpcs:ignore WordPress.Security.EscapeOutput
	}

	/**
	 * 表单。
	 *
	 * @param array $instance 配置。
	 */
	public function form( $instance ) {
		$title = isset( $instance['title'] ) ? $instance['title'] : '';
		$count = isset( $instance['count'] ) ? absint( $instance['count'] ) : 5;
		?>
		<p>
			<label for="<?php echo esc_attr( $this->get_field_id( 'title' ) ); ?>"><?php esc_html_e( '标题：', 'qingya' ); ?></label>
			<input class="widefat" id="<?php echo esc_attr( $this->get_field_id( 'title' ) ); ?>" name="<?php echo esc_attr( $this->get_field_name( 'title' ) ); ?>" type="text" value="<?php echo esc_attr( $title ); ?>">
		</p>
		<p>
			<label for="<?php echo esc_attr( $this->get_field_id( 'count' ) ); ?>"><?php esc_html_e( '显示数量：', 'qingya' ); ?></label>
			<input class="tiny-text" id="<?php echo esc_attr( $this->get_field_id( 'count' ) ); ?>" name="<?php echo esc_attr( $this->get_field_name( 'count' ) ); ?>" type="number" min="1" max="20" value="<?php echo esc_attr( $count ); ?>">
		</p>
		<?php
	}

	/**
	 * 更新。
	 *
	 * @param array $new_instance 新配置。
	 * @param array $old_instance 旧配置。
	 * @return array
	 */
	public function update( $new_instance, $old_instance ) {
		$instance          = array();
		$title_raw = isset( $new_instance['title'] ) ? sanitize_text_field( $new_instance['title'] ) : '';
		$instance['title'] = '' !== $title_raw ? $title_raw : __( '近期评论', 'qingya' );
		$instance['count'] = isset( $new_instance['count'] ) ? min( 20, absint( $new_instance['count'] ) ) : 5;
		return $instance;
	}
}

/**
 * 搜索框（主题自定义，后台可设置标题，与其他小工具统一管理）。
 */
class Qingya_Widget_Search extends WP_Widget {

	/**
	 * 构造。
	 */
	public function __construct() {
		parent::__construct( 'qy_search', __( '青崖：搜索', 'qingya' ), array(
			'description' => __( '站内搜索框', 'qingya' ),
		) );
	}

	/**
	 * 渲染。
	 *
	 * @param array $args     侧边栏参数。
	 * @param array $instance 配置。
	 */
	public function widget( $args, $instance ) {
		$title = ! empty( $instance['title'] ) ? $instance['title'] : __( '搜索', 'qingya' );
		echo $args['before_widget']; // phpcs:ignore WordPress.Security.EscapeOutput
		if ( $title ) {
			echo $args['before_title'] . esc_html( $title ) . $args['after_title']; // phpcs:ignore WordPress.Security.EscapeOutput
		}
		echo '<div class="qy-widget-search">';
		get_search_form();
		echo '</div>';
		echo $args['after_widget']; // phpcs:ignore WordPress.Security.EscapeOutput
	}

	/**
	 * 表单。
	 *
	 * @param array $instance 配置。
	 */
	public function form( $instance ) {
		$title = isset( $instance['title'] ) ? $instance['title'] : '';
		?>
		<p>
			<label for="<?php echo esc_attr( $this->get_field_id( 'title' ) ); ?>"><?php esc_html_e( '标题：', 'qingya' ); ?></label>
			<input class="widefat" id="<?php echo esc_attr( $this->get_field_id( 'title' ) ); ?>" name="<?php echo esc_attr( $this->get_field_name( 'title' ) ); ?>" type="text" value="<?php echo esc_attr( $title ); ?>">
		</p>
		<?php
	}

	/**
	 * 更新。
	 *
	 * @param array $new_instance 新配置。
	 * @param array $old_instance 旧配置。
	 * @return array
	 */
	public function update( $new_instance, $old_instance ) {
		$instance          = array();
		$title_raw = isset( $new_instance['title'] ) ? sanitize_text_field( $new_instance['title'] ) : '';
		$instance['title'] = '' !== $title_raw ? $title_raw : __( '搜索', 'qingya' );
		return $instance;
	}
}

/**
 * 青崖统计小工具（侧边栏展示网站访问数据）。
 * 数据来自「青崖统计」模块，5 分钟缓存避免每页查库。
 */
class Qingya_Widget_Stats extends WP_Widget {

	/**
	 * 构造。
	 */
	public function __construct() {
		parent::__construct( 'qy_stats', __( '青崖：网站统计', 'qingya' ), array(
			'description' => __( '在侧边栏展示浏览量/访客等访问数据（来自青崖统计）', 'qingya' ),
		) );
	}

	/**
	 * 获取统计数据（带 5 分钟缓存）。
	 *
	 * @return array
	 */
	private function qingya_stats_data() {
		$cache = get_transient( 'qy_stats_widget_data' );
		if ( is_array( $cache ) ) {
			return $cache;
		}

		$data = array(
			'total_pv'   => 0,
			'today_pv'   => 0,
			'today_uv'   => 0,
			'yday_pv'    => 0,
			'week_pv'    => 0,
			'week_uv'    => 0,
		);
		if ( function_exists( 'qingya_stats_totals' ) && function_exists( 'qingya_stats_row_count' ) ) {
			$today  = gmdate( 'Y-m-d' );
			$yday   = gmdate( 'Y-m-d', time() - DAY_IN_SECONDS );
			$w_from = gmdate( 'Y-m-d', time() - 6 * DAY_IN_SECONDS );
			$t      = qingya_stats_totals( $today, $today );
			$y      = qingya_stats_totals( $yday, $yday );
			$w      = qingya_stats_totals( $w_from, $today );
			$data   = array(
				'total_pv' => (int) qingya_stats_row_count(),
				'today_pv' => $t['pageviews'],
				'today_uv' => $t['visitors'],
				'yday_pv'  => $y['pageviews'],
				'week_pv'  => $w['pageviews'],
				'week_uv'  => $w['visitors'],
			);
		}
		set_transient( 'qy_stats_widget_data', $data, 5 * MINUTE_IN_SECONDS );
		return $data;
	}

	/**
	 * 渲染。
	 *
	 * @param array $args     侧边栏参数。
	 * @param array $instance 实例配置。
	 */
	public function widget( $args, $instance ) {
		$title  = ! empty( $instance['title'] ) ? $instance['title'] : __( '网站统计', 'qingya' );
		$items  = isset( $instance['items'] ) && is_array( $instance['items'] ) ? $instance['items'] : array( 'total_pv', 'today_pv', 'today_uv' );
		$data   = $this->qingya_stats_data();
		$labels = array(
			'total_pv' => __( '总浏览量', 'qingya' ),
			'today_pv' => __( '今日浏览', 'qingya' ),
			'today_uv' => __( '今日访客', 'qingya' ),
			'yday_pv'  => __( '昨日浏览', 'qingya' ),
			'week_pv'  => __( '近 7 天浏览', 'qingya' ),
			'week_uv'  => __( '近 7 天访客', 'qingya' ),
		);

		echo $args['before_widget']; // phpcs:ignore WordPress.Security.EscapeOutput
		if ( $title ) {
			echo $args['before_title'] . esc_html( $title ) . $args['after_title']; // phpcs:ignore WordPress.Security.EscapeOutput
		}
		echo '<ul class="qy-widget-stats">';
		foreach ( $items as $key ) {
			if ( ! isset( $labels[ $key ] ) ) {
				continue;
			}
			echo '<li><span class="qy-widget-stats-label">' . esc_html( $labels[ $key ] ) . '</span>';
			echo '<span class="qy-widget-stats-num">' . esc_html( number_format_i18n( $data[ $key ] ) ) . '</span></li>';
		}
		echo '</ul>';
		echo $args['after_widget']; // phpcs:ignore WordPress.Security.EscapeOutput
	}

	/**
	 * 表单。
	 *
	 * @param array $instance 实例配置。
	 */
	public function form( $instance ) {
		$title = isset( $instance['title'] ) ? $instance['title'] : '';
		$items = isset( $instance['items'] ) && is_array( $instance['items'] ) ? $instance['items'] : array( 'total_pv', 'today_pv', 'today_uv' );
		$all   = array(
			'total_pv' => __( '总浏览量', 'qingya' ),
			'today_pv' => __( '今日浏览', 'qingya' ),
			'today_uv' => __( '今日访客', 'qingya' ),
			'yday_pv'  => __( '昨日浏览', 'qingya' ),
			'week_pv'  => __( '近 7 天浏览', 'qingya' ),
			'week_uv'  => __( '近 7 天访客', 'qingya' ),
		);
		?>
		<p>
			<label for="<?php echo esc_attr( $this->get_field_id( 'title' ) ); ?>"><?php esc_html_e( '标题：', 'qingya' ); ?></label>
			<input class="widefat" id="<?php echo esc_attr( $this->get_field_id( 'title' ) ); ?>" name="<?php echo esc_attr( $this->get_field_name( 'title' ) ); ?>" type="text" value="<?php echo esc_attr( $title ); ?>">
		</p>
		<p><?php esc_html_e( '显示项：', 'qingya' ); ?></p>
		<?php foreach ( $all as $key => $label ) : ?>
			<p style="margin:2px 0;">
				<label>
					<input type="checkbox" name="<?php echo esc_attr( $this->get_field_name( 'items' ) ); ?>[]" value="<?php echo esc_attr( $key ); ?>" <?php checked( in_array( $key, $items, true ) ); ?> />
					<?php echo esc_html( $label ); ?>
				</label>
			</p>
		<?php endforeach; ?>
		<p class="description"><?php esc_html_e( '数据来自「青崖统计」，约 5 分钟刷新一次。', 'qingya' ); ?></p>
		<?php
	}

	/**
	 * 更新。
	 *
	 * @param array $new_instance 新配置。
	 * @param array $old_instance 旧配置。
	 * @return array
	 */
	public function update( $new_instance, $old_instance ) {
		$instance          = array();
		$title_raw = isset( $new_instance['title'] ) ? sanitize_text_field( $new_instance['title'] ) : '';
		$instance['title'] = '' !== $title_raw ? $title_raw : __( '网站统计', 'qingya' );
		$valid             = array( 'total_pv', 'today_pv', 'today_uv', 'yday_pv', 'week_pv', 'week_uv' );
		$instance['items'] = array();
		if ( ! empty( $new_instance['items'] ) && is_array( $new_instance['items'] ) ) {
			foreach ( $new_instance['items'] as $item ) {
				$item = sanitize_key( $item );
				if ( in_array( $item, $valid, true ) ) {
					$instance['items'][] = $item;
				}
			}
		}
		return $instance;
	}
}

/**
 * 注册小工具（注册顺序 = 后台可用列表顺序，与侧边栏使用顺序保持一致：搜索在前）。
 */
function qingya_register_widgets() {
	register_widget( 'Qingya_Widget_Search' );
	register_widget( 'Qingya_Widget_Recent' );
	register_widget( 'Qingya_Widget_Recent_Comments' );
	register_widget( 'Qingya_Widget_Hot_Topics' );
	register_widget( 'Qingya_Widget_Hot' );
	register_widget( 'Qingya_Widget_Random' );
	register_widget( 'Qingya_Widget_Stats' );
}
add_action( 'widgets_init', 'qingya_register_widgets' );
