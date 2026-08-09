/**
 * 青崖统计（Qingya Stats）追踪脚本
 * - 无 Cookie、无 localStorage，不采集个人信息
 * - 页面加载即上报一次浏览（URL/标题/来源/屏宽/UTM），尊重 Do Not Track
 * - 优先 fetch(keepalive)，旧浏览器回退 sendBeacon
 * - 配置经 wp_localize_script 注入 window.qingyaStats
 */
(function () {
	'use strict';

	var cfg = window.qingyaStats || {};
	if (!cfg.url) { return; }

	// 尊重 Do Not Track / Global Privacy Control。
	var dnt = (navigator.doNotTrack === '1' || navigator.doNotTrack === 'yes' || navigator.globalPrivacyControl === true) ? 1 : 0;

	// 解析 UTM 参数。
	function utm(name) {
		var m = new RegExp('[?&]' + name + '=([^&]+)').exec(location.search);
		return m ? decodeURIComponent(m[1].replace(/\+/g, ' ')).slice(0, 100) : '';
	}

	var payload = {
		url: location.href.slice(0, 500),
		title: document.title.slice(0, 255),
		referrer: document.referrer.slice(0, 500),
		width: screen.width || 0,
		dnt: dnt,
		utm_source: utm('utm_source'),
		utm_medium: utm('utm_medium'),
		utm_campaign: utm('utm_campaign')
	};

	var body = new URLSearchParams();
	for (var k in payload) {
		if (payload.hasOwnProperty(k)) { body.append(k, payload[k]); }
	}

	// 仅登录用户携带 nonce 头（WP 会对带 nonce 的请求做 cookie 会话校验，
	// 访客带头反而触发 rest_cookie_invalid_nonce；访客走同源 + 限流防护）。
	var headers = {};
	if (document.body && document.body.className.indexOf('logged-in') !== -1) {
		headers['X-WP-Nonce'] = cfg.nonce || '';
	}

	try {
		fetch(cfg.url, {
			method: 'POST',
			headers: headers,
			body: body,
			credentials: 'same-origin',
			keepalive: true
		});
	} catch (e) {
		if (navigator.sendBeacon) {
			try {
				navigator.sendBeacon(cfg.url, new Blob([body.toString()], { type: 'application/x-www-form-urlencoded' }));
			} catch (e2) { /* 静默失败，不影响页面 */ }
		}
	}
})();
