<?php
/**
 * 搜索表单。
 *
 * @package Qingya
 */

?>
<form role="search" method="get" class="qy-searchform" action="<?php echo esc_url( home_url( '/' ) ); ?>">
	<label class="screen-reader-text" for="qy-search-input"><?php esc_html_e( '搜索', 'qingya' ); ?></label>
	<input type="search" id="qy-search-input" class="qy-search-input" name="s"
		value="<?php echo esc_attr( get_search_query() ); ?>"
		placeholder="<?php esc_attr_e( '输入关键词搜索…', 'qingya' ); ?>">
	<button type="submit" class="qy-search-submit"><?php esc_html_e( '搜索', 'qingya' ); ?></button>
</form>
