/**
 * 青崖主题（Qingya）主脚本
 * 原生 JavaScript，无第三方依赖。模块化 IIFE，逐功能初始化。
 */
(function () {
	'use strict';

	/** 工具：DOM 就绪后执行。 */
	function ready(fn) {
		if (document.readyState !== 'loading') {
			fn();
		} else {
			document.addEventListener('DOMContentLoaded', fn);
		}
	}

	/** 深色模式切换（localStorage 记忆，首次跟随系统）。 */
	function initDarkMode() {
		var toggle = document.getElementById('qy-dark-toggle');
		var root = document.documentElement;
		if (!toggle) {
			return;
		}
		var KEY = 'qingya-theme';
		var stored = null;
		try {
			stored = localStorage.getItem(KEY);
		} catch (e) { /* 隐私模式忽略 */ }
		var systemDark = window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches;
		var apply = function (dark) {
			if (dark) {
				root.setAttribute('data-theme', 'dark');
				toggle.querySelector('.qy-dark-icon').textContent = '☀️';
			} else {
				root.removeAttribute('data-theme');
				toggle.querySelector('.qy-dark-icon').textContent = '🌙';
			}
		};
		apply(stored ? stored === 'dark' : systemDark);
		toggle.addEventListener('click', function () {
			var dark = root.getAttribute('data-theme') === 'dark';
			apply(!dark);
			try {
				localStorage.setItem(KEY, dark ? 'light' : 'dark');
			} catch (e) { /* 忽略 */ }
		});
	}

	/** 移动端汉堡菜单（抽屉 + 遮罩）。
	 * 使用事件委托绑定，避免 DOMContentLoaded 时序问题；
	 * 即使页面其他脚本报错也不影响菜单功能。 */
	function initMobileNav() {
		var toggle = document.getElementById('qy-menu-toggle');
		var nav = document.getElementById('qy-nav');
		if (!toggle || !nav) {
			return;
		}
		var overlay = document.createElement('div');
		overlay.className = 'qy-nav-overlay';
		document.body.appendChild(overlay);

		var open = function () {
			nav.classList.add('is-open');
			overlay.classList.add('is-visible');
			toggle.setAttribute('aria-expanded', 'true');
			document.body.style.overflow = 'hidden';
		};
		var close = function () {
			nav.classList.remove('is-open');
			overlay.classList.remove('is-visible');
			toggle.setAttribute('aria-expanded', 'false');
			document.body.style.overflow = '';
		};

		// 事件委托：绑定到 document，任何时机点击都生效。
		document.addEventListener('click', function (e) {
			if (e.target.closest && e.target.closest('#qy-menu-toggle')) {
				nav.classList.contains('is-open') ? close() : open();
			}
		});
		overlay.addEventListener('click', close);
		nav.addEventListener('click', function (e) {
			if (e.target.closest && e.target.closest('a')) {
				close();
			}
		});
		window.addEventListener('keydown', function (e) {
			if (e.key === 'Escape') {
				close();
			}
		});
	}

	/** 搜索面板展开/收起。 */
	function initSearchPanel() {
		var toggle = document.querySelector('.qy-search-toggle');
		var panel = document.querySelector('.qy-search-panel');
		if (!toggle || !panel) {
			return;
		}
		var input = panel.querySelector('input[type="search"]');
		toggle.addEventListener('click', function () {
			var hidden = panel.hasAttribute('hidden');
			panel.toggleAttribute('hidden');
			toggle.setAttribute('aria-expanded', hidden ? 'true' : 'false');
			if (hidden && input) {
				input.focus();
			}
		});
	}

	/** 阅读进度条（文章页）。 */
	function initProgressBar() {
		if (!document.querySelector('.qy-post')) {
			return;
		}
		var bar = document.createElement('div');
		bar.id = 'qy-progress';
		document.body.appendChild(bar);
		var ticking = false;
		var update = function () {
			var doc = document.documentElement;
			var scrollTop = window.pageYOffset || doc.scrollTop;
			var height = doc.scrollHeight - window.innerHeight;
			bar.style.width = (height > 0 ? (scrollTop / height) * 100 : 0) + '%';
			ticking = false;
		};
		window.addEventListener('scroll', function () {
			if (!ticking) {
				ticking = true;
				window.requestAnimationFrame(update);
			}
		}, { passive: true });
		update();
	}

	/** 返回顶部按钮。 */
	function initBackToTop() {
		var btn = document.getElementById('qy-back-to-top');
		if (!btn) {
			return;
		}
		var update = function () {
			btn.classList.toggle('is-visible', window.pageYOffset > 400);
		};
		window.addEventListener('scroll', update, { passive: true });
		btn.addEventListener('click', function () {
			window.scrollTo({ top: 0, behavior: 'smooth' });
		});
		update();
	}

	/** 滚动渐入动画（IntersectionObserver，仅非文章内容元素）。 */
	function initReveal() {
		var targets = document.querySelectorAll('.qy-card, .qy-featured-item, .qy-related-item, .qy-carousel');
		if (!targets.length || !('IntersectionObserver' in window)) {
			return;
		}
		var observer = new IntersectionObserver(function (entries) {
			entries.forEach(function (entry) {
				if (entry.isIntersecting) {
					entry.target.classList.add('qy-reveal', 'is-visible');
					observer.unobserve(entry.target);
				} else {
					entry.target.classList.add('qy-reveal');
				}
			});
		}, { threshold: 0.08 });
		targets.forEach(function (el) {
			observer.observe(el);
		});
	}

	/** 首页轮播（自动播放 + 指示点 + 按钮）。 */
	function initCarousel() {
		var carousel = document.querySelector('.qy-carousel');
		if (!carousel) {
			return;
		}
		var track = carousel.querySelector('.qy-carousel-track');
		var slides = carousel.querySelectorAll('.qy-carousel-slide');
		var prev = carousel.querySelector('.qy-carousel-prev');
		var next = carousel.querySelector('.qy-carousel-next');
		var dotsBox = carousel.querySelector('.qy-carousel-dots');
		var autoplayMs = parseInt(carousel.dataset.autoplay || '0', 10);
		var index = 0;
		var timer = null;

		// 指示点。
		slides.forEach(function (_, i) {
			var dot = document.createElement('button');
			dot.setAttribute('aria-label', '第 ' + (i + 1) + ' 张');
			dot.addEventListener('click', function () {
				go(i);
				restart();
			});
			dotsBox.appendChild(dot);
		});
		var dots = dotsBox.querySelectorAll('button');

		var go = function (i) {
			index = (i + slides.length) % slides.length;
			track.style.transform = 'translateX(-' + index * 100 + '%)';
			dots.forEach(function (d, di) {
				d.classList.toggle('is-active', di === index);
			});
		};
		var restart = function () {
			if (autoplayMs > 0) {
				clearInterval(timer);
				timer = setInterval(function () {
					go(index + 1);
				}, autoplayMs);
			}
		};

		if (prev) { prev.addEventListener('click', function () { go(index - 1); restart(); }); }
		if (next) { next.addEventListener('click', function () { go(index + 1); restart(); }); }

		// 触摸滑动。
		var startX = 0;
		carousel.addEventListener('touchstart', function (e) {
			startX = e.touches[0].clientX;
		}, { passive: true });
		carousel.addEventListener('touchend', function (e) {
			var dx = e.changedTouches[0].clientX - startX;
			if (Math.abs(dx) > 40) {
				go(dx < 0 ? index + 1 : index - 1);
				restart();
			}
		}, { passive: true });

		go(0);
		restart();
	}

	/** 点赞 / 收藏（AJAX + nonce）。 */
	function initPostActions() {
		var likeBtn = document.querySelector('.qy-like-btn');
		var favBtn = document.querySelector('.qy-fav-btn');
		if (!likeBtn && !favBtn) {
			return;
		}
		if (typeof qingyaData === 'undefined') {
			return;
		}
		var post = function (action, data, done) {
			var body = new FormData();
			body.append('action', action);
			body.append('nonce', qingyaData.nonce);
			body.append('post_id', data.postId);
			fetch(qingyaData.ajaxUrl, {
				method: 'POST',
				credentials: 'same-origin',
				body: body
			})
				.then(function (r) { return r.json(); })
				.then(function (res) { done(res); })
				.catch(function () { done({ ok: false }); });
		};

		if (likeBtn) {
			likeBtn.addEventListener('click', function () {
				post('qingya_like', { postId: likeBtn.dataset.post }, function (res) {
					if (res && res.ok) {
						likeBtn.classList.add('is-active');
						var count = likeBtn.querySelector('.qy-like-count');
						if (count && typeof res.count !== 'undefined') {
							count.textContent = res.count;
						}
					}
				});
			});
		}
		if (favBtn) {
			favBtn.addEventListener('click', function () {
				post('qingya_favorite', { postId: favBtn.dataset.post }, function (res) {
					if (res && res.ok) {
						favBtn.classList.toggle('is-active', !!res.faved);
						var label = favBtn.querySelector('.qy-action-label');
						if (label) {
							label.textContent = res.faved ? '已收藏' : '收藏';
						}
					}
				});
			});
		}
	}

	ready(function () {
		// 每个模块独立 try/catch：单个失败不影响其余功能。
		[initDarkMode, initMobileNav, initSearchPanel, initProgressBar,
			initBackToTop, initReveal, initCarousel, initPostActions].forEach(function (fn) {
			try {
				fn();
			} catch (e) {
				if (window.console && console.error) {
					console.error('[Qingya] init error:', fn.name, e);
				}
			}
		});
	});
})();
