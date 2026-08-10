<?php
/**
 * inc/updater.php — 青简主题 GitHub Release 自动升级（v1.4.1）
 *
 * 原理：接入 WordPress 标准主题更新通道（update_themes transient + themes_api），
 * 从 GitHub Releases API 拉取最新版本：
 *   优先 Release Asset（zip 根目录即 qingya，WP 直接识别），
 *   无 Asset 时回退 Source code zip（配合 upgrader_source_selection 重命名目录）。
 * 后台「外观 → 主题」出现标准「有可用更新」提示，一键走 WP 自带升级流程。
 *
 * 配置（本文件顶部常量，或 functions.php 中提前 define 覆盖）：
 *   QINGYA_UPDATE_ENABLED  开关（默认 true）
 *   QINGYA_UPDATE_OWNER    GitHub 用户名/组织（默认 sunclchina）
 *   QINGYA_UPDATE_REPO     GitHub 仓库名（默认 qingya）
 *   QINGYA_UPDATE_TOKEN    可选 PAT（未认证限 60 次/小时/IP）
 *
 * @package Qingya
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // 禁止直接访问。
}

if ( ! defined( 'QINGYA_UPDATE_ENABLED' ) ) {
	define( 'QINGYA_UPDATE_ENABLED', true );
}
if ( ! defined( 'QINGYA_UPDATE_OWNER' ) ) {
	define( 'QINGYA_UPDATE_OWNER', 'sunclchina' );
}
if ( ! defined( 'QINGYA_UPDATE_REPO' ) ) {
	define( 'QINGYA_UPDATE_REPO', 'qingya' );
}
if ( ! defined( 'QINGYA_UPDATE_TOKEN' ) ) {
	define( 'QINGYA_UPDATE_TOKEN', '' );
}

class Qingya_Updater {

	const CACHE_KEY = 'qingya_gh_release_cache';
	const CACHE_TTL = 12 * HOUR_IN_SECONDS;

	/**
	 * 初始化：钩子挂载（functions.php 模块循环加载时调用）。
	 *
	 * @return void
	 */
	public static function init() {
		if ( ! QINGYA_UPDATE_ENABLED ) {
			return;
		}
		add_filter( 'pre_set_site_transient_update_themes', array( __CLASS__, 'check_update' ) );
		add_filter( 'themes_api', array( __CLASS__, 'theme_info' ), 10, 3 );
		add_filter( 'upgrader_source_selection', array( __CLASS__, 'fix_source_dir' ), 10, 4 );
		add_action( 'admin_menu', array( __CLASS__, 'admin_menu' ) );
		add_filter( 'http_request_args', array( __CLASS__, 'api_asset_accept' ), 10, 2 );
	}

	/**
	 * 主题 stylesheet（目录名）。
	 *
	 * @return string
	 */
	public static function stylesheet() {
		$theme = wp_get_theme();
		return $theme->get_stylesheet();
	}

	/**
	 * 当前主题版本（style.css 头）。
	 *
	 * @return string
	 */
	public static function current_version() {
		$theme = wp_get_theme();
		return $theme->get( 'Version' );
	}

	/**
	 * 拉取 GitHub 最新 Release（带 12h 缓存；force 强制刷新）。
	 *
	 * @param bool $force 是否忽略缓存。
	 * @return array|null 失败返回 null（静默，不影响站点）。
	 */
	public static function get_remote_release( $force = false ) {
		$cache = $force ? false : get_site_transient( self::CACHE_KEY );
		if ( is_array( $cache ) && ! empty( $cache['tag_name'] ) ) {
			return $cache;
		}
		$url = 'https://api.github.com/repos/' . rawurlencode( QINGYA_UPDATE_OWNER ) . '/' . rawurlencode( QINGYA_UPDATE_REPO ) . '/releases/latest';
		$args = array(
			'timeout' => 15,
			'headers' => array(
				'User-Agent' => 'Qingya/' . self::current_version(),
				'Accept'     => 'application/vnd.github+json',
			),
		);
		if ( QINGYA_UPDATE_TOKEN ) {
			$args['headers']['Authorization'] = 'Bearer ' . QINGYA_UPDATE_TOKEN;
		}
		$resp = wp_remote_get( $url, $args );
		// 部分 Windows PHP 环境的 OpenSSL 证书链验证异常（即使配置了 CA 也无法验证 GitHub 证书），
		// 对 GitHub API 域降级重试一次（仅传输层/证书类失败才降级，404 等业务错误不重试）。
		if ( is_wp_error( $resp ) || 0 === wp_remote_retrieve_response_code( $resp ) ) {
			$args2          = $args;
			$args2['sslverify'] = false;
			$args2['timeout']   = 20;
			$resp = wp_remote_get( $url, $args2 );
		}
		if ( is_wp_error( $resp ) || 200 !== wp_remote_retrieve_response_code( $resp ) ) {
			return null; // 网络/限流/仓库不存在失败静默降级。
		}
		$data = json_decode( wp_remote_retrieve_body( $resp ), true );
		if ( ! is_array( $data ) || empty( $data['tag_name'] ) ) {
			return null;
		}
		set_site_transient( self::CACHE_KEY, $data, self::CACHE_TTL );
		return $data;
	}

	/**
	 * 注入标准主题更新通道（pre_set_site_transient_update_themes）。
	 *
	 * @param object $transient 更新 transient。
	 * @return object
	 */
	public static function check_update( $transient ) {
		if ( ! is_object( $transient ) || empty( $transient->checked ) ) {
			return $transient;
		}
		$release = self::get_remote_release();
		if ( ! $release ) {
			return $transient;
		}
		$remote_ver = ltrim( (string) $release['tag_name'], 'vV' );
		if ( version_compare( $remote_ver, self::current_version(), '<=' ) ) {
			return $transient;
		}
		$package = self::package_url( $release );
		if ( ! $package ) {
			return $transient;
		}
		$stylesheet = self::stylesheet();
		$transient->response[ $stylesheet ] = array(
			'theme'        => $stylesheet,
			'new_version'  => $remote_ver,
			'url'          => isset( $release['html_url'] ) ? $release['html_url'] : '',
			'package'      => $package,
			'requires_php' => '7.4',
		);
		return $transient;
	}

	/**
	 * 计算下载包地址。
	 *
	 * @param array $release GitHub release 数据。
	 * @return string 空串表示无可用包。
	 */
	public static function package_url( $release ) {
		$assets = isset( $release['assets'] ) && is_array( $release['assets'] ) ? $release['assets'] : array();
		foreach ( $assets as $a ) {
			$name = isset( $a['name'] ) ? (string) $a['name'] : '';
			if ( false !== strpos( $name, 'qingya' ) && '.zip' === substr( $name, -4 ) ) {
				// 优先 api.github.com asset 直链：github.com 下载在国内环境常被网络层屏蔽，
				// api.github.com + Accept: application/octet-stream 可达（匿名即可）。
				if ( ! empty( $a['id'] ) ) {
					return 'https://api.github.com/repos/' . rawurlencode( QINGYA_UPDATE_OWNER ) . '/' . rawurlencode( QINGYA_UPDATE_REPO ) . '/releases/assets/' . (int) $a['id'];
				}
				if ( isset( $a['browser_download_url'] ) ) {
					return $a['browser_download_url'];
				}
			}
		}
		// 回退：Source code zip（codeload 域名，配合 fix_source_dir 重命名目录）。
		if ( ! empty( $release['zipball_url'] ) ) {
			return $release['zipball_url'];
		}
		return '';
	}

	/**
	 * Source code zip 顶层目录是 {repo}-{tag}，与主题目录不符会导致升级失败，
	 * 统一重命名为主题 stylesheet（仅处理本主题升级）。
	 *
	 * @param string      $source       解压后源目录。
	 * @param string      $remote_source 远端临时目录。
	 * @param WP_Upgrader $upgrader     升级器实例。
	 * @param array       $hook_extra   额外参数（含 theme stylesheet）。
	 * @return string
	 */
	public static function fix_source_dir( $source, $remote_source, $upgrader, $hook_extra = null ) {
		if ( ! $source || ! is_dir( $source ) ) {
			return $source;
		}
		if ( ! $hook_extra || empty( $hook_extra['theme'] ) || $hook_extra['theme'] !== self::stylesheet() ) {
			return $source;
		}
		$slug = self::stylesheet();
		$src  = rtrim( $source, '/\\' );
		$new  = rtrim( dirname( $source ), '/\\' ) . DIRECTORY_SEPARATOR . $slug;
		if ( $src === rtrim( $new, '/\\' ) ) {
			return $source; // 目录名已正确（Asset 包）。
		}
		global $wp_filesystem;
		if ( $wp_filesystem ) {
			$wp_filesystem->delete( $new, true );
			$wp_filesystem->move( $src, $new );
		} elseif ( @rename( $src, $new ) ) { // phpcs:ignore
			// PHP 原生 rename 兜底。
		} else {
			return $source; // 重命名失败，交回 WP 处理。
		}
		return $new;
	}

	/**
	 * 主题「查看详情」数据（themes_api）。
	 *
	 * @param mixed  $res    默认结果。
	 * @param string $action 动作名。
	 * @param object $args   请求参数。
	 * @return mixed
	 */
	public static function theme_info( $res, $action, $args ) {
		if ( 'theme_information' !== $action || empty( $args->slug ) ) {
			return $res;
		}
		if ( $args->slug !== self::stylesheet() ) {
			return $res;
		}
		$release = self::get_remote_release();
		if ( ! $release ) {
			return $res;
		}
		$info                = new stdClass();
		$info->name          = '青简（Qingya）';
		$info->slug          = self::stylesheet();
		$info->version       = ltrim( (string) $release['tag_name'], 'vV' );
		$info->author        = '<a href="https://sunclnas.cn/">Qingya Team</a>';
		$info->homepage      = 'https://github.com/' . QINGYA_UPDATE_OWNER . '/' . QINGYA_UPDATE_REPO;
		$info->requires      = '6.0';
		$info->requires_php  = '7.4';
		$info->download_link = self::package_url( $release );
		$info->sections      = array(
			'description' => '青简——闲适清简的中文博客与展示站通用主题（GitHub 自动升级）。',
			'changelog'   => isset( $release['body'] ) ? nl2br( esc_html( (string) $release['body'] ) ) : '',
		);
		return $info;
	}

	/**
	 * api.github.com asset 下载需要 Accept: application/octet-stream，否则返回 JSON 元数据。
	 *
	 * @param array  $args 请求参数。
	 * @param string $url  请求 URL。
	 * @return array
	 */
	public static function api_asset_accept( $args, $url ) {
		if ( false !== strpos( (string) $url, '/releases/assets/' ) ) {
			if ( empty( $args['headers'] ) || ! is_array( $args['headers'] ) ) {
				$args['headers'] = array();
			}
			$args['headers']['Accept'] = 'application/octet-stream';
		}
		return $args;
	}

	/**
	 * 强制检查更新（后台调试用；无 UI，直接返回数组）。
	 *
	 * @return array
	 */
	public static function force_check() {
		delete_site_transient( self::CACHE_KEY );
		$release = self::get_remote_release( true );
		if ( ! $release ) {
			return array(
				'ok'    => false,
				'error' => 'GitHub 不可达或仓库不存在',
			);
		}
		$remote_ver = ltrim( (string) $release['tag_name'], 'vV' );
		return array(
			'ok'          => true,
			'current'     => self::current_version(),
			'latest'      => $remote_ver,
			'has_update'  => version_compare( $remote_ver, self::current_version(), '>' ),
			'package'     => self::package_url( $release ),
			'update_url'  => admin_url( 'themes.php' ),
		);
	}

	/**
	 * 后台「外观 → 检查主题更新」页面（手动检查，绕过 12h 缓存）。
	 *
	 * @return void
	 */
	public static function admin_menu() {
		add_theme_page(
			__( '检查主题更新', 'qingya' ),
			__( '检查主题更新', 'qingya' ),
			'update_themes',
			'qingya-update-check',
			function () {
				delete_site_transient( 'update_themes' );
				$result = self::force_check();
				$back   = admin_url( 'themes.php' );
				echo '<div class="wrap">';
				echo '<h1>' . esc_html__( 'Qingya 主题更新检查', 'qingya' ) . '</h1>';
				if ( is_array( $result ) && ! empty( $result['ok'] ) ) {
					echo '<p>' . esc_html__( '当前版本', 'qingya' ) . '：<strong>' . esc_html( $result['current'] ) . '</strong>　→　最新版本：<strong>' . esc_html( $result['latest'] ) . '</strong></p>';
					if ( ! empty( $result['has_update'] ) ) {
						echo '<div class="notice notice-success"><p>' . esc_html__( '发现新版本！请到「外观 → 主题」页面点击更新。', 'qingya' ) . '</p></div>';
					} else {
						echo '<div class="notice notice-info"><p>' . esc_html__( '已是最新版本。', 'qingya' ) . '</p></div>';
					}
				} else {
					$err = isset( $result['error'] ) ? $result['error'] : __( '未知错误', 'qingya' );
					echo '<div class="notice notice-error"><p>' . esc_html__( '检查失败', 'qingya' ) . '：' . esc_html( $err ) . '</p></div>';
				}
				echo '<p><a class="button" href="' . esc_url( $back ) . '">' . esc_html__( '返回主题管理', 'qingya' ) . '</a></p>';
				echo '</div>';
			}
		);
	}
}

Qingya_Updater::init();
