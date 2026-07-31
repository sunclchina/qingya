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
	 * 渲染列表。
	 *
	 * @param array $args     侧边栏参数。
	 * @param array $instance 实例配置。
	 */
	public function widget( $args, $instance ) {
		$title = ! empty( $instance['title'] ) ? $instance['title'] : '';
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
}

/**
 * 注册小工具。
 */
function qingya_register_widgets() {
	register_widget( 'Qingya_Widget_Hot' );
	register_widget( 'Qingya_Widget_Recent' );
	register_widget( 'Qingya_Widget_Random' );
}
add_action( 'widgets_init', 'qingya_register_widgets' );
