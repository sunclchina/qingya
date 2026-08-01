/**
 * 青崖主题 · AI 智能客服前端
 * 原生 JS，零依赖。对话记录存 localStorage，服务器不落库。
 *
 * @package Qingya
 */
(function () {
	'use strict';

	var cfg = window.qingyaAi || {};
	if (!cfg.ajaxUrl) {
		return;
	}

	var STORE_KEY = 'qingya_ai_chat_v1';
	var MAX_HISTORY = 50;   // 本地最多保留条数。
	var MAX_CONTEXT = 8;    // 每次请求携带的上下文条数。

	var launcher, panel, messagesBox, input, sendBtn, quickBox, clearBtn, closeBtn;
	var isOpen = false;
	var busy = false;

	// 实时凭据（解决页面缓存/CDN 下页面内签名过期问题）。
	var creds = { nonce: cfg.nonce, t: cfg.t, sign: cfg.sign };
	var credsAt = 0;

	/* ---------- 会话凭据 ---------- */

	// REST 路由优先（绕开 admin-ajax 可能被安全插件/防火墙拦截），失败回退 admin-ajax。
	function postJSON(url, body) {
		return fetch(url, {
			method: 'POST',
			credentials: 'same-origin',
			headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' },
			body: body.toString()
		})
			.then(function (res) {
				return res.json();
			})
			.catch(function () {
				return null;
			});
	}

	function refreshCreds() {
		var body = new URLSearchParams();
		body.append('nonce', cfg.nonce);

		// 1) 优先 REST。
		if (cfg.restBase) {
			return postJSON(cfg.restBase + 'session', body).then(function (data) {
				if (data && data.ok) {
					creds = { nonce: data.nonce, t: data.t, sign: data.sign };
					credsAt = Date.now();
					return true;
				}
				// 2) REST 失败 → 回退 admin-ajax。
				var ajaxBody = new URLSearchParams(body);
				ajaxBody.append('action', 'qingya_ai_session');
				return postJSON(cfg.ajaxUrl, ajaxBody).then(function (d2) {
					if (d2 && d2.ok) {
						creds = { nonce: d2.nonce, t: d2.t, sign: d2.sign };
						credsAt = Date.now();
						return true;
					}
					return false;
				});
			})
				.catch(function () {
					return false;
				});
		}

		// 无 REST 配置（旧缓存）→ 直接 admin-ajax。
		var ajaxBody = new URLSearchParams(body);
		ajaxBody.append('action', 'qingya_ai_session');
		return postJSON(cfg.ajaxUrl, ajaxBody).then(function (data) {
			if (data && data.ok) {
				creds = { nonce: data.nonce, t: data.t, sign: data.sign };
				credsAt = Date.now();
				return true;
			}
			return false;
		});
	}

	function ensureCreds() {
		// 页面内签名新鲜（<5 分钟）且可用时直接用；否则实时拉取。
		if (creds.sign && Date.now() - credsAt < 5 * 60 * 1000) {
			return Promise.resolve(true);
		}
		return refreshCreds();
	}

	/* ---------- 工具 ---------- */

	function loadHistory() {
		try {
			var raw = localStorage.getItem(STORE_KEY);
			var arr = raw ? JSON.parse(raw) : [];
			return Array.isArray(arr) ? arr : [];
		} catch (e) {
			return [];
		}
	}

	function saveHistory(history) {
		try {
			localStorage.setItem(STORE_KEY, JSON.stringify(history.slice(-MAX_HISTORY)));
		} catch (e) {
			/* 隐私模式等场景静默失败 */
		}
	}

	function pushMsg(role, content) {
		var history = loadHistory();
		history.push({ role: role, content: String(content) });
		saveHistory(history);
	}

	/* ---------- 渲染 ---------- */

	function addMessage(role, text) {
		var row = document.createElement('div');
		row.className = 'qy-ai-msg qy-ai-msg-' + role;

		var bubble = document.createElement('div');
		bubble.className = 'qy-ai-bubble';
		bubble.textContent = text;

		row.appendChild(bubble);
		messagesBox.appendChild(row);
		messagesBox.scrollTop = messagesBox.scrollHeight;
		return bubble;
	}

	/**
	 * 打字机效果：逐字追加。
	 */
	function typeMessage(text, done) {
		var bubble = document.createElement('div');
		bubble.className = 'qy-ai-bubble qy-ai-bubble-typing';
		var row = document.createElement('div');
		row.className = 'qy-ai-msg qy-ai-msg-assistant';
		row.appendChild(bubble);
		messagesBox.appendChild(row);

		var i = 0;
		var step = Math.max(1, Math.round(text.length / 60)); // 60 步内打完。
		var timer = setInterval(function () {
			i += step;
			bubble.textContent = text.slice(0, i);
			messagesBox.scrollTop = messagesBox.scrollHeight;
			if (i >= text.length) {
				clearInterval(timer);
				bubble.classList.remove('qy-ai-bubble-typing');
				bubble.textContent = text;
				if (done) {
					done();
				}
			}
		}, 16);
	}

	function showLoading() {
		var row = document.createElement('div');
		row.className = 'qy-ai-msg qy-ai-msg-assistant qy-ai-loading';
		row.innerHTML = '<div class="qy-ai-bubble"><span class="qy-ai-dots"><i></i><i></i><i></i></span></div>';
		messagesBox.appendChild(row);
		messagesBox.scrollTop = messagesBox.scrollHeight;
		return row;
	}

	function renderQuick() {
		if (!cfg.quick || !cfg.quick.length) {
			return;
		}
		quickBox.hidden = false;
		quickBox.innerHTML = '';
		cfg.quick.forEach(function (q) {
			var btn = document.createElement('button');
			btn.type = 'button';
			btn.className = 'qy-ai-quick-item';
			btn.textContent = q;
			btn.addEventListener('click', function () {
				input.value = q;
				send();
			});
			quickBox.appendChild(btn);
		});
	}

	/* ---------- 对话逻辑 ---------- */

	function send() {
		if (busy) {
			return;
		}
		var text = input.value.trim();
		if (!text) {
			return;
		}

		busy = true;
		input.disabled = true;
		sendBtn.disabled = true;

		addMessage('user', text);
		pushMsg('user', text);
		input.value = '';

		var loading = showLoading();

		// 携带本地最近上下文（服务端校验并截断，不落库）。
		var history = loadHistory().slice(-MAX_CONTEXT);

		var reset = function () {
			busy = false;
			input.disabled = false;
			sendBtn.disabled = false;
			input.focus();
		};

		var trySend = function (retried, viaREST) {
			var body = new URLSearchParams();
			body.append('nonce', creds.nonce);
			body.append('t', creds.t);
			body.append('sign', creds.sign);
			body.append('message', text);
			body.append('history', JSON.stringify(history));

			// 默认 REST 优先；viaREST=false 时走 admin-ajax。
			var useREST = (viaREST !== false) && !!cfg.restBase;
			var url = useREST ? cfg.restBase + 'chat' : cfg.ajaxUrl;
			var reqBody = body;
			if (!useREST) {
				reqBody = new URLSearchParams(body);
				reqBody.append('action', cfg.action);
			}

			fetch(url, {
				method: 'POST',
				credentials: 'same-origin',
				headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' },
				body: reqBody.toString()
			})
				.then(function (res) {
					return res.json();
				})
				.then(function (data) {
					// REST 失败（403/网络等）→ 回退 admin-ajax 重试一次。
					if (useREST && !retried && (!data || !data.ok)) {
						return trySend(true, false);
					}
					// 签名失效：刷新凭据后重试一次（应对页面缓存/CDN）。
					if (!retried && data && !data.ok && data.msg && data.msg.indexOf('签名') !== -1) {
						return refreshCreds().then(function (ok) {
							if (ok) {
								return trySend(true, useREST);
							}
							loading.remove();
							typeMessage('会话已过期，请刷新页面后重试。');
						});
					}
					loading.remove();
					if (data && data.ok) {
						typeMessage(data.reply, function () {
							pushMsg('assistant', data.reply);
						});
					} else {
						var msg = (data && data.msg) || '出错了，请稍后再试。';
						if (data && data.reply) {
							// 部分场景（如夜间）以 reply 形式返回话术。
							typeMessage(data.reply, function () {
								pushMsg('assistant', data.reply);
							});
						} else {
							typeMessage(msg);
						}
					}
				})
				.catch(function () {
					// REST 网络异常 → 回退 admin-ajax 一次。
					if (useREST && !retried) {
						return trySend(true, false);
					}
					loading.remove();
					typeMessage('网络异常，请稍后再试。');
				})
				.then(reset);
		};

		// 凭据新鲜（<60 秒）直接用，否则实时刷新（应对页面缓存/CDN）。
		var fresh = creds.sign && Date.now() - credsAt < 60 * 1000;
		if (fresh) {
			trySend(false);
			return;
		}
		refreshCreds().then(function () {
			trySend(false);
		});
	}

	function clearChat() {
		localStorage.removeItem(STORE_KEY);
		messagesBox.innerHTML = '';
		if (cfg.welcome) {
			addMessage('assistant', cfg.welcome);
		}
		renderQuick();
	}

	/* ---------- 展开 / 收起 ---------- */

	function openPanel() {
		isOpen = true;
		panel.classList.add('qy-ai-open');
		panel.setAttribute('aria-hidden', 'false');
		launcher.setAttribute('aria-expanded', 'true');
		launcher.classList.add('qy-ai-launcher-active');
		input.focus();
	}

	function closePanel() {
		isOpen = false;
		panel.classList.remove('qy-ai-open');
		panel.setAttribute('aria-hidden', 'true');
		launcher.setAttribute('aria-expanded', 'false');
		launcher.classList.remove('qy-ai-launcher-active');
	}

	function togglePanel() {
		isOpen ? closePanel() : openPanel();
	}

	/* ---------- 初始化 ---------- */

	function init() {
		launcher = document.getElementById('qy-ai-launcher');
		panel = document.getElementById('qy-ai-panel');
		messagesBox = document.getElementById('qy-ai-messages');
		input = document.getElementById('qy-ai-input');
		sendBtn = document.getElementById('qy-ai-send');
		quickBox = document.getElementById('qy-ai-quick');
		clearBtn = document.getElementById('qy-ai-clear');
		closeBtn = document.getElementById('qy-ai-close');

		if (!launcher || !panel || !messagesBox || !input || !sendBtn) {
			return;
		}

		launcher.addEventListener('click', togglePanel);
		closeBtn.addEventListener('click', closePanel);
		sendBtn.addEventListener('click', send);
		clearBtn.addEventListener('click', clearChat);
		input.addEventListener('keydown', function (e) {
			if (e.key === 'Enter' && !e.shiftKey) {
				e.preventDefault();
				send();
			}
		});

		// 点击面板外区域收起。
		document.addEventListener('click', function (e) {
			if (isOpen && !panel.contains(e.target) && !launcher.contains(e.target)) {
				closePanel();
			}
		});

		renderQuick();

		// 欢迎语：本地无记录时推送；有记录则恢复最近对话。
		var history = loadHistory();
		if (history.length === 0) {
			if (cfg.welcome) {
				addMessage('assistant', cfg.welcome);
			}
		} else {
			history.forEach(function (item) {
				if (item.role === 'user' || item.role === 'assistant') {
					addMessage(item.role, item.content);
				}
			});
		}
	}

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', init);
	} else {
		init();
	}
})();
