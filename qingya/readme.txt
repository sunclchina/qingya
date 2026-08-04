=== 青简（QingJian）===
Contributors: qingjian-team
Requires at least: 6.0
Tested up to: 7.0
Requires PHP: 7.4
Stable tag: 1.4.2
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html
Tags: blog, news, one-column, two-columns, right-sidebar, left-sidebar, custom-colors, custom-logo, custom-menu, featured-images, threaded-comments, translation-ready

中文博客与展示站点通用主题：原生 SEO、后台可视化配置、IP 黑名单、深色模式、响应式。

== Description ==

青简是一款面向中文博客与展示站点的轻量通用主题：

* 原生 SEO：TDK 自动生成 + 单页独立自定义、结构化数据、robots 兼容、图片 ALT 自动补充
* 后台可视化配置（Customizer）：LOGO、公告、版权、备案号、配色、字体、布局、轮播等，实时预览
* 页面模板：首页（轮播+图文+列表）、全宽页、无侧边栏页、404 页
* IP 黑名单系统：单 IP / 网段 / CIDR 拦截，白名单豁免，蜘蛛放行，访问日志
* 交互：浏览量统计、点赞、收藏、阅读进度条、深色/浅色模式
* 性能：按需加载、图片懒加载、CDN 域名配置、资源版本化
* AI 智能客服：悬浮对话、DeepSeek 中转、站内文章检索（RAG）、关键词回复、敏感词过滤、IP 限流封禁、夜间静默
* 安全：隐藏版本信息、扫描 UA 屏蔽、登录失败锁定自动拉黑（5 分钟 3 次）、境外 IP 拦截（MaxMind GeoLite2）、IP 黑名单
* 零第三方框架依赖，移动端自适应

== Installation ==

1. 将 `qingya` 文件夹上传至 `wp-content/themes/`，或在后台「外观 → 主题 → 安装主题 → 上传」。
2. 启用主题。
3. 前往「外观 → 自定义」进行可视化配置。
4. 可选：创建页面并选用「首页」模板，在「设置 → 阅读」中设为站点首页。


== AI 智能客服（可选功能） ==

主题内置轻量 AI 悬浮客服（Customizer → AI 智能客服 开启），无插件依赖：

1. 在 platform.deepseek.com 申请 API Key，填入「DeepSeek API Key」。
2. 自定义欢迎语、快捷问题、关键词自动回复、夜间静默时段与客服主色。
3. 安全防护自动生效：时间签名 + nonce + Referer 同源校验、单 IP 限流、高频封禁、爬虫 UA 拦截、敏感词过滤、每日调用量上限。
4. 对话记录仅存访客浏览器本地（localStorage），服务器不保存聊天内容。

== 境外 IP 拦截（可选） ==

主题内置两种境外拦截能力（均基于 MaxMind GeoLite2-Country 数据库）：

**A. AI 客服接口拦截**：「外观 → 自定义 → AI 智能客服 → 禁止境外 IP 调用 AI 客服」——仅拦客服接口。

**B. 全站境外 IP 拦截**：「外观 → 自定义 → 安全 → 拦截境外 IP 访问前台」——仅允许中国大陆 IP 访问前台，可放行港澳台、配置白名单、拦截后跳转 URL。

准备数据库：

1. 前往 https://www.maxmind.com/en/geolite2/signup 注册免费账号（需邮箱验证）；或参考 https://github.com/P3TERX/GeoLite.mmdb 的镜像下载。
2. 取 GeoLite2-Country.mmdb，上传到 wp-content/uploads/（或任意可读目录），主题自动检测；也可在 AI 客服设置中手动指定路径。
3. 数据库建议每月更新一次（IP 归属会变动）。

注意：登录管理员自动豁免，不会误锁后台；未找到数据库时自动放行（不误伤访客）；数据库仅供本站查 IP 归属，不会外传。

== Changelog ==

= 1.1.0 - 2026-08-01 =
* 修复 open_basedir 限制下 GeoIP 路径检测报 Warning 的问题（自动跳过受限路径）
* 登录保护升级：5 分钟 3 次失败锁定 + 自动加入 IP 黑名单（白名单豁免、管理员登录自动解除）
* 内置全站境外 IP 拦截（MaxMind GeoLite2，可开关/白名单/放行港澳台），替代易误伤的第三方国家封锁插件
* IP 黑名单增加管理员豁免：已登录管理员无条件放行，防止误锁后台
* 对话接口改为 REST API 优先（wp-json/qingya/v1），绕开 admin-ajax 被安全插件/防火墙拦截导致手机端无法对话的问题；admin-ajax 保留为自动兜底
* 修复 Customizer 复选框无法保存的 Bug（sanitize 兼容布尔值，AI 客服开关/轮播/深色模式等复选框恢复正常）
* AI 客服支持站内文章检索（RAG）：提问时自动检索博客文章，基于站内真实内容回答并附链接，禁止编造文章
* 客服按钮改为耳麦图标 + 「客服」胶囊样式
* AI 智能客服机器人（可开关）：悬浮对话、DeepSeek 中转、关键词回复、敏感词过滤、IP 限流封禁、境外 IP 拦截、夜间静默、对话本地存储

= 1.0.0 - 2026-07-31 =
* 初始版本
