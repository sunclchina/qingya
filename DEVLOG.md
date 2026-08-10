# 青崖主题（Qingya）开发日志

- 项目目录：`E:\my-project\workspace\qingya`
- 目标环境：WordPress 6.8+ / 7.x，PHP 7.4+（兼容 8.x）
- 主题类型：经典主题（PHP 模板 + Customizer），无第三方框架依赖
- 文本域：`qingya`

## 核心原则（源自翁老需求书）
1. 严格遵循 WP 官方编码标准（WordPress Coding Standards）
2. 原生规范优先、模块化低耦合
3. 资源工程化管理、轻量化无冗余
4. 可视化可配置（Customizer）、兼容可扩展
5. 安全高性能（前端加载 / 数据库 / 缓存全兼容）

## 架构
```
qingya/
├── style.css              主题头 + 设计变量
├── functions.php          模块加载器（唯一入口）
├── inc/
│   ├── setup.php          主题初始化：支持、菜单、侧边栏、图片尺寸
│   ├── customizer.php     Customizer 配置（基础/配色/首页/布局/字体/性能/SEO/安全）
│   ├── seo.php            TDK、结构化数据、robots、图片 ALT
│   ├── security.php       版本隐藏、扫描 UA 屏蔽、基础防护
│   ├── ip-blacklist.php   IP 黑名单（拦截策略/白名单/日志）
│   ├── performance.php    按需加载、懒加载、CDN、资源版本化
│   ├── template-tags.php  模板辅助函数（面包屑/浏览量/缩略图…）
│   ├── widgets.php        侧边栏小工具扩展
│   ├── meta-boxes.php     文章/页面独立 TDK 与布局 Meta
│   └── ajax.php           点赞/收藏/浏览量 AJAX 端点
├── admin/ip-blacklist.php IP 黑名单管理页
├── page-templates/        front-page（首页）/full-width/no-sidebar
├── template-parts/        header/footer/content 片段
└── assets/                css/js/img
```

## 开发记录

### 2026-08-10（v1.9.5）
- 修复：后台更新下载失败「cURL error 28: Failed to connect to github.com port 443」（翁老反馈，生产站环境）
  - 根因：updater 检查更新走 api.github.com（可达），但下载 zip 用 release asset 的 github.com 直链——国内/生产服务器网络层屏蔽 github.com → 超时
  - 修复：package_url 改用 api.github.com/releases/assets/<id> 直链（匿名 + Accept: application/octet-stream 可达，已实测 457502 字节下载成功）；新增 http_request_args filter（api_asset_accept）自动补 Accept 头
  - 版本同步：style.css → 1.9.5，打包 dist\qingya-v1.9.5.zip

### 2026-08-10（v1.9.4）
- 修复：评论头像不显示（翁老反馈：A-Blog AI 评论应有头像配置，实际显示默认灰图）
  - 根因：A-Blog 评论头像走 pre_get_avatar_data（_abp_avatar meta → 本地 SVG），但主题 qingya_local_avatar 的 pre_get_avatar 直接输出默认头像 HTML，覆盖了 A-Blog 的 SVG
  - 修复：qingya_local_avatar 优先检查评论 _abp_avatar meta（A-Blog SVG），其次用户本地头像，最后主题默认头像
  - 附带：测试站现有 18 条评论已批量补 _abp_avatar meta（ABP_Avatar::ensure_avatar 生成 18 个 SVG，同昵称同头像）
  - 版本同步：style.css → 1.9.4，打包 dist\qingya-v1.9.4.zip

### 2026-08-10（v1.9.3）
- 修复：后台保存页面/文章报「此响应不是合法的 JSON 响应」（翁老反馈，display_errors 排查后确认）
  - 根因：home-layouts.php / ai-chatbot/index.php 等文件带 UTF-8 BOM（EF BB BF），BOM 字节输出到每个响应开头，REST JSON 被污染（响应头 EF BB BF 7B...）→ wp.apiFetch 解析失败；wp-config.php 也被 PowerShell 写入时误加 BOM
  - 修复：去除全部 PHP 文件 BOM（主题 home-layouts.php、ai-chatbot/index.php、lib/index.php、wp-config.php、A-Blog 主文件），REST 响应恢复纯 JSON（7B 开头）
  - 教训：PowerShell 5.1 的 [System.Text.Encoding]::UTF8 带 BOM！写 PHP 文件必须用 UTF8Encoding($false)；写文件后检查头 3 字节
  - 版本同步：style.css → 1.9.3，打包 dist\qingya-v1.9.3.zip

### 2026-08-10（v1.9.2）
- 修复：文章页顶部特色图横幅与正文首图重复显示（翁老反馈：每篇两张相同图片）
  - 根因：A-Blog/AI 配图流程同一张图既设为特色图又插入正文开头；主题在顶部额外显示特色图横幅（1200x560）→ 与正文原图重复
  - 修复：content-single.php 正文含 <img> 时隐藏顶部 qy-post-thumb 横幅（仅显示层调整，不动数据）
  - 验证：测试站 7241 正文区 1 图、qy-post-thumb div=0；生产站同数据同效果
  - 版本同步：style.css → 1.9.2，打包 dist\qingya-v1.9.2.zip

### 2026-08-10（v1.9.1）
- 新增：后台「外观 → 检查主题更新」页面（翁老问询：主题没有手动检查更新功能）
  - 之前 force_check() 方法存在但无 UI 入口，发版后只能手动上传 zip 或等 12h 缓存
  - 实现：updater.php 加 admin_menu（add_theme_page「检查主题更新」），访问页面时清 update_themes transient + force_check()（清 release 缓存并重新拉取 GitHub）→ 显示当前/最新版本与结果 → 一键返回主题页点更新
  - 版本同步：style.css → 1.9.1，打包 dist\qingya-v1.9.1.zip

### 2026-08-10（v1.9.0）
- 新增：首页「最新文章」区翻页（翁老反馈：最新文章没有翻页，看不到前面文章）
  - 根因：qingya_home_latest_list/grid 用独立 WP_Query（posts_per_page 10/8 + no_found_rows=true），查询无 paged 且无分页输出
  - 修复：① 查询加 `paged`（兼容静态首页 page 变量）② no_found_rows 改 false（分页需要总数）③ 列表后输出 the_posts_pagination（total/current 基于该查询，复用现有 .page-numbers 样式）
  - 覆盖 portal（列表）与 magazine（网格）两种布局；blog 模式分页 /page/2/，静态首页模式 ?page=2
  - 备注：home-layouts.php 行尾统一为 LF（原 CRLF，edit 工具无法匹配）
  - 版本同步：style.css → 1.9.0，打包 dist\qingya-v1.9.0.zip

### 2026-08-10（v1.8.9）
- 修复：首页「最新文章」长标题手机端溢出的**真正根因**（翁老 Console 实测定位：A disp=flex dir=column + align-items:center → body 宽=标题全文宽）
  - 根因：@media(max-width:640) 里 `.qy-simple-item a { flex-direction: column }`（移动端卡片变纵向），而 `.qy-home-latest-list .qy-simple-item a { align-items: center }` 在 column 方向变成水平居中 → body 无显式宽度时宽度=内容宽（标题全文），长标题把 body 撑到 365px 超卡片被切；v1.8.7/1.8.8 的 min-width:0/flex:1 1 0 在 column+center 下管不到宽度
  - 修复：`.qy-simple-body` 加 `width: 100%; max-width: 100%`（显式限宽，任何布局下 body ≤ 容器宽，标题在固定宽度内 ellipsis）
  - 教训：此前 probe 均在 741px（桌面 row 布局）视口验证，未覆盖 ≤640px 移动 column 布局，导致两次误判
  - 版本同步：style.css → 1.8.9，打包 dist\qingya-v1.8.9.zip

### 2026-08-10（v1.8.8）
- 修复：首页「最新文章」长标题（第 4、9 条）在手机端仍溢出（翁老设备模拟定位：仅最长两条溢出）
  - 根因：v1.8.7 的 min-width:0 只"允许"flex 子项收缩，但 `.qy-simple-body` 的 flex-basis 仍是 auto（= 标题全文宽），长标题把 body 撑到 max-content，空间不足时收缩不可靠 → 溢出
  - 修复：`.qy-simple-body` 追加 `flex: 1 1 0`（flex-basis 0 → body 恒占满 a 剩余空间，标题在固定宽度内 ellipsis 截断，任何视口安全）
  - 验证：本地复现页 probe（生产站 HTML + 新 CSS）——body 宽从 573（标题全文撑开）变为 579（剩余空间），title scrollWidth=clientWidth，无溢出
  - 版本同步：style.css → 1.8.8，打包 dist\qingya-v1.8.8.zip

### 2026-08-10（v1.8.7）
- 修复：首页文章标题/卡片在手机端被右缘裁切（翁老反馈：无痕模式首页标题超出不换行，单篇页正常）
  - 根因：首页 portal/magazine 布局「最新文章」区 `.qy-home-latest-list .qy-simple-title` 为 nowrap+ellipsis 的 span（非 h 标签，v1.8.6 的 h1-h6 全局断词覆盖不到）；flex `min-width:auto` 陷阱——`.qy-simple-body` 有 min-width:0 但子项没有，nowrap 标题的 min-content=全文宽度，真机 375px 下把卡片一路撑破到 ~494px，被 body overflow-x:hidden 硬切（页面不滚动但文字被截）
  - 修复（main.css）：① `.qy-home-latest-list .qy-simple-title` 加 min-width:0（ellipsis 真正生效）；② `.qy-simple-item` 加 min-width:0；③ 新增 `.qy-simple-body > * { min-width:0 }` + `.qy-simple-title` 补 overflow-wrap；④ `.qy-simple-meta` 加 flex-wrap:wrap
  - 验证：headless 渲染 + 像素分析——文章列表区（y=240-719）贴边像素修复前 225 行→修复后 0 行；视觉检测（getBoundingClientRect）除抽屉菜单（设计行为）外无任何元素超出视口；快讯 ticker/项目标题本就有 min-width:0+ellipsis，无需改动
  - 版本同步：style.css → 1.8.7，打包 dist\qingya-v1.8.7.zip

### 2026-08-10
- v1.8.6：手机端文章长标题超出页面边界修复（翁老反馈）
  - 根因：全部标题（h1~h6：qy-post-title / qy-card-title / qy-block-title 等）均无断词兜底；中文标题默认可换行，但标题内夹长英文/URL/连续数字串时，单个"单词"整体画出容器边界，手机上尤其明显
  - 修复：基础样式 h1-h6 全局追加 `overflow-wrap: break-word` + `word-wrap: break-word`（旧浏览器兼容），一处改动覆盖文章页、列表卡片、首页全部模块标题
  - 不影响已有 nowrap+ellipsis 的单行标题（热搜/项目/快讯，均带 min-width:0 + overflow:hidden，本就安全）
  - 版本同步：style.css → 1.8.6，打包 dist\qingya-v1.8.6.zip

### 2026-08-09
- v1.8.5：评论防护修复外语广告漏网（翁老反馈：俄语戒酒广告评论进来——kapelnicza-ot-zapoya-sankt-peterburg.ru）
  - 漏网根因：俄语西里尔字母不被 [a-zA-Z] 识别，但 URL 里的英文字母（kapelnicza...ru）让「无意义检测」的 has_letter=true 通过；推广词库全是中文词；俄语正文长 >50 字符绕过外链 b) 规则——三重绕过
  - 修复：① qingya_attack_content_meaningless 先 preg_replace 剥掉 URL 再统计字符（俄语文本去 URL 后无中文无英文 → 拦）；② 新增外语字符兜底规则：含西里尔 U+0400-04FF/阿拉伯 U+0600-06FF/希伯来 U+0590-05FF/泰文 U+0E00-0E7F/天城文 U+0900-097F → 直接 spam（中文站无正常此类评论）
  - 实测 9 用例全过：俄语原文/俄英混合+链接/阿拉伯语+链接/纯俄语 全拦；正常中英文评论（含单链接中文）全放行
  - 版本同步：style.css / functions.php / readme.txt → 1.8.5，打包 dist\qingya-v1.8.5.zip，本机部署

### 2026-08-09
- v1.8.4：修复「青崖：热门话题」小工具不显示（翁老反馈）
  - 排查：远程 REST 查分类法列表无 abp_topic（A-Blog 未装）；sitemap 发现 wp-sitemap-posts-thread-1.xml → 线上「话题」是星河AI工具箱的 thread 自定义文章类型（234 篇，/thread/xxx，页面 200 正常）——小工具只认 A-Blog 的 abp_topic 分类法，查空直接 return
  - 修复：widgets.php 热门话题小工具数据源自适应——taxonomy_exists('abp_topic') 时用分类法（按文章数），否则 post_type_exists('thread') 回退 thread（按评论数排序），渲染自动切换 #前缀与链接形式；description 同步更新
  - 实测：本机（有 A-Blog）分类法分支渲染 6 话题正常；模拟无 abp_topic + 注册 thread 插入 2 篇 → 回退分支渲染 thread 标题/链接/无# 全对
  - 版本同步：style.css / functions.php / readme.txt → 1.8.4，打包 dist\qingya-v1.8.4.zip，本机部署

### 2026-08-09
- v1.8.3：评论防护新增「推广意图检测」（翁老反馈：评论关着还有伪装广告评论进来）
  - 规则：评论含链接/疑似域名（www. 或 .com/.cn/.ru/.top/.xyz 等后缀）且同时含推广诱导词（推荐/可以看看/了解一下/详情/点击/访问/了解更多/官网/优惠/领取/加我等 30 词）→ spam；评论者资料（comment_author_url）填了链接且内容也带链接 → 机器人特征 spam
  - 实测 14 用例全过：8 伪装广告（推荐+链接/官网+链接/作者URL+内容链接）全拦；6 正常评论（含单链接无推广词的中文评论）全放行
  - 版本同步：style.css / functions.php / readme.txt → 1.8.3，打包 dist\qingya-v1.8.3.zip，本机部署

### 2026-08-09
- v1.8.2：评论防护新增「无意义评论检测」（翁老需求：没有表达任何意义的中外文字符一律拦截）
  - qingya_attack_content_meaningless() 启发式检测（零外部依赖）：去空白 <3 字；唯一字符 ≤2 且 <20 字（111111/哈哈哈哈）；无中文且无字母（纯符号/数字/表情）；编码乱码（U+FFFD/€/GBK→UTF8 乱码区 U+9D00-9FFF）；键盘乱敲（≥8 无元音辅音）；高频凑字（最常见字符占比 >60% 且 <30 字）
  - 集成 preprocess_comment（设置项 comment_meaningless 默认开，后台可关）；设置页新增复选框
  - 坑：初次集成把 $content 变量定义放在了检测之后 → undefined 变空串 → 全部评论误判 spam；已把变量定义提前
  - 实测 23 用例全过：13 无意义场景（单字/纯符号/数字/重复字/表情/乱敲/乱码/凑字/词库）全拦；8 有意义场景（中文观点/英文评论/带链接中文评论/哈哈有意思/666 观点）全放行；vk.ru 外链 2 例仍拦
  - 版本同步：style.css / functions.php / readme.txt → 1.8.2，打包 dist\qingya-v1.8.2.zip，本机部署

### 2026-08-09
- v1.8.1：新增「青崖：网站统计」侧边栏小工具（翁老需求：侧边栏能看到网站访问数据）
  - inc/widgets.php 新增 Qingya_Widget_Stats（WP_Widget）：标题可配；显示项可选总浏览量/今日浏览/今日访客/昨日浏览/近7天浏览/近7天访客（默认前三项）；数据来自「青崖统计」模块 API，5 分钟 transient 缓存不拖慢页面；数字 tabular-nums 右对齐（main.css .qy-widget-stats）
  - 评论外链检测升级（翁老反馈 vk.ru 型单链接评论漏网）：① 手写 <a href> 标签直接 spam；② 去掉 URL 后无实质文字（<10 字符）spam；③ 单链接且无中文字符且剩余 <50 字符（英文废话+外链机器人）spam；④ ≥2 链接 spam 保留。实测 8 用例全过（纯URL/<a标签>/英文废话/中文短句+外链/双链接全部拦，正常中英文评论不误杀）
  - 版本同步：style.css / functions.php / readme.txt → 1.8.1，打包 dist\qingya-v1.8.1.zip，本机部署

### 2026-08-09
- v1.8.0：新增「异常访问防护」模块（翁老需求：腾讯云 sunclnas.cn 遭外国 IP 攻击——评论页翻页 CC 刷量 + xmlrpc 轰炸 + wp-login 爆破 + 垃圾评论机器人，CPU/内存 100%，要求主题能自动屏蔽/拉黑）
  - 攻击分析（翁老提供 access log）：几十个国内外代理 IP 伪造 Chrome/Edge UA 刷 /archives/*/comment-page-{300~683}（评论区被灌 4 万+ 垃圾评论成靶子）；129.222.147.93 每 10s POST xmlrpc.php；209.59.172.59 爆破 wp-login；89.124.103.152 单 IP 刷文+发评论；wp-cron 每 15s 触发异常
  - 评审微调（翁老）：评论审核策略改为——管理员账户例外直接通过；非管理员评论 10 分钟内前 N 条（默认 5）按 WP 默认规则发布，超出的转人工审核（不拒绝，进待审队列）；全站频率阈值默认 60→30 次/分；实测：未超频放行/第 6 条起待审/垃圾词 spam/管理员免审 全通过
  - 新增 inc/attack-guard.php + admin/attack-guard.php（菜单「异常防护」）：
    ① 全站频率限制：单 IP 每分钟超阈值（默认 60）→ 临时封禁（默认 30 分钟，可配），连续 3 次自动转永久黑名单（复用 qingya_login_auto_blacklist，白名单豁免），可选超限直接拉黑
    ② XMLRPC 掐断：xmlrpc_enabled filter + XMLRPC_REQUEST 403 双重
    ③ 评论防护：同 IP 10 分钟评论数限制（默认 5）+ 垃圾词过滤（复用 qingya_ai_sensitive_words + 内置广告词库 + 自定义词）+ ≥2 外链直接 spam + 新评论强制人工审核
    ④ 封禁日志复用 qingya_ip_log（reason 扩展支持 attack），管理页可解除/转永久/清空 + 今日封禁统计
  - 安全设计：管理员/白名单/蜘蛛三重豁免；transient 60s 窗口计数；403+nocache 响应
  - 实测（本机 WP 端到端）：xmlrpc POST→403；连打 70 次首页→60 放行后 10 次 403（阈值精确）；临时封禁入库可解除；垃圾词/双外链→spam、正常评论→待审；管理页渲染正常；清理后首页恢复 200
  - 版本同步：style.css / functions.php / readme.txt → 1.8.0，打包 dist\qingya-v1.8.0.zip，本机部署

### 2026-08-09
- v1.7.0：新增内置「青崖统计」本地隐私分析（翁老需求：学习 Burst Statistics 插件，主题增加类似功能）
  - 调研：Burst = 无 Cookie/GDPR 友好/数据存本机 WP 库/追踪脚本 <4KB/实时看板；Pro = 地理（MaxMind）、UTM、目标、自动化报告。历史 CVE 教训（认证绕过 9.8/SQLi/XSS/CSRF）→ 实现时安全优先
  - 架构：inc/analytics.php（建表 dbDelta + REST 追踪端点 + 报表查询 API + 设置）+ admin/analytics.php（后台看板）+ assets/js/qingya-stats.js（<2KB，fetch keepalive→sendBeacon 回退）
  - 隐私：无 Cookie、无 localStorage、无第三方服务；IP 仅存 HMAC-SHA256 加盐哈希（原始 IP 不落库）；尊重 Do Not Track；数据保留天数自动清理（概率性，免 cron）+ 一键清空
  - 安全（防刷防伪造）：REST 端点单 IP 限流（150次/30s transient）+ Origin/Referer 同源校验（跨站 403）+ 登录态 nonce 校验；SQL 全预编译
  - 报表：概览（PV/UV/人均 + 纯 CSS 双系列柱状趋势图 + 热门内容/来源 TOP10）、实时（10 分钟在线 + 最近明细）、热门内容、来源（按域名聚合）、设备/浏览器、国家分布（复用 qingya_ai_geo_country + GeoLite2，库缺失自动隐藏）、UTM 活动（utm_source/medium/campaign）、目标转化（URL 包含词命中）、设置（开关/排除角色/保留天数/DNT/目标配置/清空）
  - 坑 1：访客 JS 发 X-WP-Nonce 头会触发 WP 核心 rest_cookie_invalid_nonce（core 对带 nonce 的请求强制 cookie 会话校验）→ 改为仅登录用户（body.logged-in class 判断）带头，访客靠同源+限流
  - 实测（本机 WP 端到端）：首页 200 且 qingya-stats.js/配置注入；REST POST 入库（mobile/desktop、Edge 识别、百度来源域名聚合、UTM 捕获）；跨站 Origin 403 拒绝、同源放行；DNT 记录正确跳过；趋势/总量/在线/目标查询全通；后台 9 个标签页渲染无错（CLI 模拟管理员）；测试数据已清空
  - 版本同步：style.css / functions.php / readme.txt → 1.7.0，打包 dist\qingya-v1.7.0.zip，本机部署

### 2026-08-09
- v1.6.11：移除 WP 自带「资料图片」区块（翁老反馈：个人资料页还有“您可以在 Gravatar 修改您的资料图片。”，为无效链接；且资料图片与主题「头像设置」的个人头像重复）
  - 根因：文字与图片来自 WP 核心（wp-admin 个人资料页默认 Profile Picture 区块，zh_CN 文案 + gravatar.com 链接），不在主题源码内，主题无钩子可删，只能隐藏/清空
  - 修复（inc/avatar.php）：① admin_enqueue_scripts 在 profile.php / user-edit.php 注入内联样式，隐藏 WP 核心给「资料图片」行的专用类 tr.user-profile-picture（WP 6.8+ 中该行嵌在「关于你自己」表格内，直接按行隐藏，不影响个人简介等其它行）；② user_profile_picture_description 过滤器返回空串兜底，即使 CSS 未命中也不输出 Gravatar 说明文字
  - 影响面：仅后台个人资料/编辑用户页，主题「头像设置」区（个人头像）不受影响（其预览图无 avatar class）；前台头像输出逻辑零改动
  - 版本同步：style.css / functions.php / readme.txt → 1.6.11，打包 dist\qingya-v1.6.11.zip，本机部署

### 2026-08-08
- v1.6.10：修复日历小工具样式（翁老反馈：①日历右缘与侧边栏右缘重合、②当日有文章深色效果对比度过小）
  - 根因：日历专用样式选择器是 .qy-sidebar #wp-calendar（普通侧边栏），但首页侧边栏类名是 .qy-home-sidebar → 首页日历拿不到 width:100%/table-layout:fixed/min-width:0/td 链接色等规则
  - 修复：所有日历规则改为 .qy-sidebar 与 .qy-home-sidebar 双选择器；有文章日增强：color var(--qy-primary-dark) 深色 + font-weight 700 + primary 14% 圆角底块 + display block
  - 验证：Chrome CDP 计算样式实测——width:100%、table-layout:fixed、linkColor #24496e、linkBg primary/0.14、fontWeight 700、display block 全部生效；截图 diff 确认日历区域渲染变化
  - 版本同步：style.css / functions.php / readme.txt → 1.6.10，打包 dist\qingya-v1.6.10.zip（正斜杠脚本），本机部署

### 2026-08-08
- v1.6.9：内置本地头像功能（翁老需求：国内 Gravatar 不可用，参考 Easy Author Avatar Image 插件）
  - 功能：后台 → 个人资料页新增「头像设置」区（预览 + 媒体库选择/上传 + 移除，qingya-avatar.js 走 wp.media）；全站头像（作者简介区 64px / 评论区 48px）经 pre_get_avatar 过滤器输出：用户本地头像 > 主题默认头像，任何情况不输出 gravatar.com 图片
  - 新增：inc/avatar.php（模块，functions.php 注册）、assets/js/qingya-avatar.js、assets/img/default-avatar.webp（GD 绘制 256×256 水墨风：米白圆底 + 竹青圆环 + 淡墨人形 + 朱红小点）
  - 坑：pre_get_avatar 过滤器只传 3 参（$avatar, $id_or_email, $args 数组），初版声明 5 参致 ArgumentCountError 评论区致命错误——debug.log 定位后改为 3 参并从 $args 取 size/alt
  - 实测：文章页 200、5 个头像全部输出 default-avatar.webp、gravatar.com 引用 0
  - 版本同步：style.css / functions.php / readme.txt → 1.6.9，打包 dist\qingya-v1.6.9.zip，本机部署

### 2026-08-08
- v1.6.8：页头登录/后台链接改为图标按钮（翁老反馈：不要「登录」文字）
  - header.php：未登录=人形 SVG（href wp_login_url）、已登录=齿轮 SVG（href admin_url），aria-label 保留语义；main.css 的 .qy-login-link 改为与搜索/深色按钮一致的图标样式（无边框、padding 6px、hover 背景高亮）
  - 实测：页面无「登录」文字、图标渲染于链接内
  - 版本同步：style.css / functions.php / readme.txt → 1.6.8，打包 dist\qingya-v1.6.8.zip，本机部署

### 2026-08-08
- v1.6.7：内置默认轮播新增第 4 张（翁老需求：看中文诗词站 cp.sunclnas.cn，写介绍，做轮播图加入默认轮播）
  - 站点分析：cp.sunclnas.cn「中文诗词」= 历代诗词典藏站（Chrome 实渲染验证）：毛泽东诗词全集（正编42+副编21）、曹操诗集（26）、诗经（305）、楚辞（65）、唐诗三百首（366）、水墨唐诗（176）、全唐诗（11,600/四万八千余首）、御定全唐诗（900卷/79,200）、宋词（两万余首/4,600）、宋词三百首（280）、蒙学、纳兰性德（258）、幽梦影（219则）、论语（20篇）、花间集（498）、南唐二主词（45）、元曲（11,057）；数据源：中文诗歌数据库（chinese-poetry 开源项目）
  - 制作：GD 生成 carousel-4.webp（1600×600，与默认 1-3 同规格）：宣纸渐变底 + 淡墨远山/水波 + 朱红印章「诗词」+ 楷体主标题「中文诗词」/副标「传世经典·词韵流芳」/仿宋内容三行 + 数据来源行；像素级验证文字/印章渲染完整
  - 接入：template-tags.php 的 qingya_render_carousel() 默认图数组 3→4；实测本机首页轮播 4 张 slide 全部渲染
  - 版本同步：style.css / functions.php / readme.txt → 1.6.7，打包 dist\qingya-v1.6.7.zip，本机部署

### 2026-08-08
- v1.6.6：现代门户微调（翁老反馈）
  - 分类按钮缩小字号（0.78em、padding 1px 10px）并移到「最新文章」标题行右侧同排（.qy-latest-head flex），不再独立成区（删除 portal.php 的 qingya_home_cats_quick() 调用，新增 qingya_home_cat_chips() 供标题行复用）
  - 最新文章 15 → 10 篇
  - 实测：qy-latest-head/qy-latest-cats 渲染、simple-item 恰好 10 条、独立分类区 0 残留
  - 版本同步：style.css / functions.php / readme.txt → 1.6.6，打包 dist\qingya-v1.6.6.zip，本机部署

### 2026-08-08
- v1.6.5：现代门户「最新文章」列表紧凑化（翁老反馈）
  - 改动（main.css .qy-home-latest-list 覆盖样式）：缩略图 150px→64px 方图、标题单行省略（nowrap + ellipsis）、隐藏摘要、行距收紧（gap 8px、padding 8px 12px），每条一行短小紧凑
  - 实测：首页 200 + 区块正常，CSS 已部署
  - 版本同步：style.css / functions.php / readme.txt → 1.6.5，打包 dist\qingya-v1.6.5.zip，本机部署

### 2026-08-08
- v1.6.4：现代门户方案板块调整（翁老反馈）
  - ① 分类直达前新增「最新文章」列表（默认 15 篇，图文列表，复用 qy-simple-list 样式）——新增 qingya_home_latest_list()
  - ② 分类直达改为分类 chips（qy-cat-chip，带文章数徽章 qy-cat-chip-count），点击进入分类归档页查看列表；取消原 qy-cat-block 内嵌最新 3 篇（qingya_home_cats_quick() 重写）
  - 顺序：轮播 → 最新文章(15) → 分类直达(chips) → 热门高赞 → 开源项目 → 股市消息 → 侧边栏
  - 实测（本机 WP）：最新列表渲染（站点 13 篇全出）、chips 9 个、旧 cat-block 0 残留、顺序正确；CSS 补 .qy-home-latest-list .qy-simple-list margin-top
  - 版本同步：style.css / functions.php / readme.txt → 1.6.4，打包 dist\qingya-v1.6.4.zip，本机部署

### 2026-08-08
- v1.6.3：修复「突发消息」列表无法点击（翁老反馈）
  - 原因：新浪 7x24 接口（qingya_stock_sina）只取了 rich_text/create_time，未取 docurl 字段；渲染时突发消息项是纯 span，无链接
  - 改动：qingya_stock_sina() 增加 url 字段（item['docurl']，新浪详情页直链）；渲染改为 <a target=_blank rel=noopener nofollow> 包裹（与东财/公告一致，CSS 的 .qy-news-item a flex 布局本就备好）
  - 实测（本机 WP）：突发消息区 5 条均带 finance.sina.cn 详情链接，清除 transient 后验证；当前快讯无「突发」关键词故 burst 徽标未出现（属正常）
  - 版本同步：style.css / functions.php / readme.txt → 1.6.3，打包 dist\qingya-v1.6.3.zip，本机部署

### 2026-08-08
- v1.6.2：书卷经典方案补侧边栏（翁老反馈）
  - 原因：经典布局模板（template-parts/home/classic.php）原为单栏 col-1c，其他三套方案均为双栏
  - 改动：classic.php 改为 qy-home-layout 双栏结构（复用 qingya_home_sidebar()，含 PC 折叠/移动端展开）；main.css 新增 .qy-home-classic .qy-featured-grid 2 列规则（主栏变窄后 4 列太挤）
  - 实测（本机 WP + 截图采样）：书卷经典渲染 qy-home-layout + qy-home-sidebar + qy-sidebar-toggle + qy-list-list，主栏/侧边栏均有内容，991px 响应式退化自动继承；测后恢复 gray-portal
  - 版本同步：style.css / functions.php / readme.txt → 1.6.2，打包 dist\qingya-v1.6.2.zip，本机部署

### 2026-08-08
- v1.6.1：首页布局下拉选项名简化（翁老反馈：去掉「竹青书卷 + 经典布局 + 文字列表」式解释），仅保留方案名：书卷经典 / 素简文章 / 现代门户 / 复古杂志；版本同步 + 重新打包 dist\qingya-v1.6.1.zip + 本机部署

### 2026-08-08
- v1.6.0：合并「首页方案」与「首页布局」为单一控件（翁老反馈：两个下拉重复，综合成一个，简化名称为「首页布局」四套方案）
  - 改动：Customizer → 首页 → 首页布局 = 书卷经典 / 素简文章 / 现代门户 / 复古杂志，一套方案 = 配色 + 板块布局 + 列表样式；删除独立「配色方案」「文章列表样式」选择器（已并入方案），配色区仅留深色模式开关 + 说明；删除 1.5.0 的联动 JS（不再需要）
  - 实现：新增 qingya_home_scheme()（setup.php）统一拆解三件套；front-page.php 取 layout、classic.php 取 list_style、qingya_get_dynamic_css() 取 palette；旧版布局值自动迁移（after_setup_theme 一次性 + 读取时双保险）：classic→qingjian-classic / minimal→ink-minimal / portal→gray-portal / magazine→coffee-magazine
  - 验证：php -l 全过；独立冒烟测试四方案拆解 + 旧值迁移 + 动态 CSS 跟随（ink→#ecece8、coffee→#f0e2cb）全过；本机 WP 端到端实测四套方案渲染（布局类名 + 背景色变量逐套验证）；翁老原 portal 布局自动迁移为「现代门户」（灰蓝底 #e2e8ef）
  - 版本同步：style.css / functions.php / readme.txt → 1.6.0，打包 dist\qingya-v1.6.0.zip，本机 WP 已同步部署

### 2026-08-08
- v1.5.0：新增「首页方案」一键套用（翁老需求：几套不同的首页显示方案，含颜色 + 板块布局 + 列表/卡片显示方式）
  - 背景：配色（4 套+自定义）、首页布局（经典/门户/杂志/极简）、列表样式（卡片/文字）本就独立可配，缺「一键组合」
  - 设计：Customizer → 首页 → 新增 qy_home_theme 选择器（5 选项），选中预设由控制端 JS（assets/js/qingya-customizer.js）同步 qy_palette / qy_home_layout / qy_layout_list_style 三个设置，套用后自动回到「自定义」；前端渲染逻辑零改动，兼容旧设置，选完仍可逐项微调
  - 四套预设：书卷经典（竹青+经典+列表）/ 素简文章（水墨+极简+列表）/ 现代门户（青灰+门户+卡片）/ 复古杂志（暖咖+杂志+瀑布流卡片）
  - 验证：php -l / node --check 通过；本机 WP 端到端实测——素简文章组合渲染 qy-home-minimal + qy-simple-list + qy-cat-chips + --qy-bg:#ecece8；复古杂志组合渲染 qy-home-magazine + qy-masonry-grid + --qy-bg:#f0e2cb（stock ticker 因数据源未命中自动隐藏属正常）；测试后恢复原设置（portal 布局）
  - 版本同步：style.css / functions.php / readme.txt → 1.5.0，打包 dist\qingya-v1.5.0.zip，本机 WP 已同步部署

### 2026-08-08
- v1.4.4：拉开四套配色方案浅色区色差（翁老反馈：切方案后「只有页脚颜色变化」）
  - 排查：后端输出已正确（动态 CSS 13 变量全量输出，像素级验证 body 背景 #f2ede3→#f6f6f3 确实在变）；问题在四套方案浅色区色差极小（米黄/灰白/冷白/暖白肉眼难分），页头/正文/卡片全白，只有页脚（深色区）和主色变化明显 → 视觉上像“只有页脚变”
  - 调整：水墨素简背景 #f6f6f3→#ecece8（冷灰）、青灰现代 #eef1f4→#e2e8ef（蓝灰）、暖咖复古 #f5efe4→#f0e2cb（暖咖米），侧边栏/边框同步；竹青书卷保持暖米不动
  - 实测（本机 WP + Chrome headless 截图 + 像素采样）：三套方案 body 背景 = #f2ede3 / #ecece8 / #f0e2cb，全站切换一眼可辨；方案切换/恢复均回写数据库验证
  - 版本同步：style.css / functions.php / readme.txt → 1.4.4，重新打包 dist\qingya-v1.4.4.zip；本机 WP 已同步部署 setup.php

### 2026-08-08
- v1.4.3：修复配色方案「半生效」问题（翁老反馈：竹青书卷/水墨素简等预设切换后部分页面颜色不变）
  - **根因 1（动态 CSS 变量不完整）**：`qingya_get_dynamic_css()` 只输出 6 个颜色变量（primary/bg/content/sidebar/text/header），但 main.css 实际用到 13 个。`--qy-primary-dark`（悬停色）、`--qy-footer-bg`（页脚背景）、`--qy-border`、`--qy-text-light`、`--qy-card`、`--qy-accent` 永远停留在竹青书卷默认值 → 切换水墨素简/青灰现代/暖咖复古时页脚仍深绿、悬停仍竹青、边框仍米色，视觉上像「没生效」
  - **根因 2（硬编码绿色）**：热门话题归档页（.qy-topic-*）、文章摘要框（.qy-post-excerpt）、侧边栏热门话题（.qy-widget-topics）全部硬编码竹青绿 #2e7d5b 系，任何配色方案下都保持绿色
  - **修复**：① 4 套预设补全 13 项颜色变量（浅色 + 深色各一组），变量名与 main.css 一一对应；② 自定义模式补全派生色（primary-dark 自动主色调深、text-light/border 由文字色 color-mix 混合、card 跟随 content-bg），不再残留默认绿；③ 硬编码绿全部改为 var(--qy-primary) / color-mix 派生，热门话题页/摘要/侧边栏随配色整体换肤
  - 保留的硬编码色（语义色，不改）：热搜前三名序号徽章红/橙/黄、财经「国内」标签金色 #b8860b、深色模式 footer 悬停白字
  - 验证：php -l 通过；独立 PHP 冒烟测试 5 种方案（4 预设 + 自定义）均输出 13 变量 × 浅色/深色；main.css 括号配平 487/487，残留硬编码绿仅剩 var() 回退值
  - 版本同步：style.css / functions.php / readme.txt（Stable tag + Changelog）→ 1.4.3，重新打包 dist\qingya-v1.4.3.zip

### 2026-08-02（下午）
- v1.3.0：多套首页布局 + 股市消息区
  - **首页布局可选**（外观 → 自定义 → 首页 → 首页布局）：经典（原首页）/ 门户综合 / 杂志资讯 / 极简文章，随时可切回经典
  - 新增根 front-page.php 布局分派器 + template-parts/home/{classic,portal,magazine,minimal}.php
  - **门户布局**：轮播 + 分类直达（按文章数取前 N）+ 热门高赞（按 _qingya_likes 排序含评论数）+ 开源项目区（推荐/IT 分类可配）+ 股市消息区 + 侧边栏
  - **杂志布局**：轮播 + 头条（大图+两小图）+ 分类 chips + 最新网格 + 财经快讯条 + 侧边栏
  - **极简布局**：分类 chips + 文章列表（可翻页）+ 财经快讯折叠条 + 侧边栏
  - **股市消息模块 inc/stock-news.php**：东方财富 7x24 快讯（热门消息）+ 巨潮资讯公告（seDate 当日）+ 新浪 7x24 快讯（突发关键词标记）+ 本站股票/行业分类文章；transient 缓存 30 分钟可配；任一源失败自动隐藏
  - **轮播升级**：3→4 张，新增简介字段 + 「阅读全文」按钮（有链接时显示），卡片式渐变遮罩
  - **侧边栏**：PC 常驻（sticky），移动端折叠（按钮展开，JS toggle）
  - 全部样式追加 main.css（--qy-* 变量，深色模式自动适配），JS 追加 initHomeSidebar
  - 坑：get_template_part('template-parts/home','portal') 不会找目录形式文件，需 get_template_part('template-parts/home/portal')
  - 实测（本机 WP）：四布局全部渲染通过；东财/新浪快讯真实数据、巨潮周日空公告（休市）均正常
  - **布局修复（CDP 实测验证）**：① `.qy-content .qy-container` 的 flex 特异性(0,2,0)覆盖了 `.qy-home-layout` 的 grid(0,1,0) → 改为 `.qy-content .qy-home-layout` 提升特异性；② 股市区 nowrap 文本撑破 grid 列 → 所有首页网格 `1fr` 改 `minmax(0, 1fr)`；③ 加 body overflow-x:hidden 兜底。修复后桌面/移动均 scrollWidth=viewport、无重叠、无溢出元素
  - **翁老视觉验收调整**：分类直达改「小按钮(chips)+每类最新3篇列表」；热门高赞改列表（序号徽章+标题+赞/评/阅读）；开源项目改列表（标题+分类+日期）；侧边栏 PC 可折叠（◀收起/▶展开，折叠后主栏全宽）+ 移动端按钮展开；grid-template-areas 显式布局修复折叠按钮占行导致的侧边栏错位；移动端 991px 退化 block 流最稳

### 2026-08-02（上午）
- v1.2.0：白名单互通 + 境外拦截日志
  - **白名单互通**：新增统一白名单判断 `qingya_ip_whitelisted()`（黑名单白名单 ∪ 境外拦截白名单），黑名单拦截、境外拦截、登录自动拉黑三处全部改用统一判断，任一白名单命中即放行，不再互不相认
  - **境外拦截写日志**：geo 拦截命中时写入 `qingya_ip_logs`（新增 reason 字段区分来源：blacklist=黑名单 / geo=境外拦截），受黑名单「访问日志」开关控制；后台日志页新增「来源」列
  - 已实测（本机 WP）：GeoIP 查询（8.8.8.8=US / 114.114.114.114=CN）、双向白名单互通、境外 IP 访问 wp-login.php → 403 + 日志落库（reason=geo）全部通过

### 2026-07-31
- 创建项目骨架与目录结构
- 完成全部模块开发（29 个 PHP 文件 + CSS + JS）：
  - 核心模板：header/footer/index/single/page/archive/search/404/sidebar/comments/searchform
  - 页面模板：首页（轮播+图文+列表）/ 全宽 / 无侧边栏
  - 模块：setup / template-tags / performance / seo / security / ip-blacklist / customizer / meta-boxes / widgets / ajax
  - 管理页：admin/ip-blacklist.php（IP 黑名单后台）
  - 前端：main.css（设计变量/深色模式/响应式）+ main.js（原生交互，零依赖）
- 生成 screenshot.png（GD 绘制 1200×900）
- 全部 29 个 PHP 文件通过 php -l 语法检查
- 实装验证（本机 WP 6.8.2）：临时切换主题验证后还原，37 个核心函数 / 小工具 / 侧边栏 / 菜单 / 图片尺寸 / IP 匹配逻辑 / 蜘蛛识别全部通过
- 站点已激活 qingya 主题，首页渲染实测正常（卡片/浏览量/评论/中文界面均正常）
- 主题正式命名：**青简（QingJian）**（青竹为简，记录时光）；内部文本域保持 qingya（代码稳定，改名无影响）
- 站点登录：用户名 sunclchina，密码已重置并校验通过
- 修复：Customizer 中小工具（侧边栏/页脚 123）无法编辑——根因是空侧边栏在前台无容器输出，Customizer 编辑器找不到目标。修复：footer.php 在预览模式强制输出三列容器 + 空位占位，sidebar.php 预览模式无条件输出主侧边栏；已用真实预览 iframe 验证（页脚 3 列 + 3 占位 + 主侧边栏均在）
- 重大修复：后台编辑器白屏（privateApis undefined）——根因是 security.php 的“隐藏版本”功能移除所有资源 ver 参数，破坏缓存致脚本混用。已移除该功能，验证零 JS 错误
- 打包与版本管理：重新打包 qingya-1.0.0.zip（35 文件 84KB）；编写 readme.md；推送 Gitea（localhost:3000/sunclchina/qingya，master @ 962b3fa）
- GitHub 推送：仓库 github.com/sunclchina/qingya（公开）已通过 Contents API 上传全部 40 个文件。背景：本机 hosts 曾屏蔽 GitHub 域名（已解除，备份 hosts.bak-20260731）；github.com 主站被网络层屏蔽（IP 全 000/400/403），仅 api.github.com 可达，故改用 API 上传；发现并移出敏感文件 my-project.git-credentials（含 Gitea 凭据，移存 E:\my-project\my-project.git-credentials.bak）
- 安全提醒：GitHub PAT（ghp_P03t2...）已在对话中出现，建议翁老推送后在 GitHub 作废该 token
- 待办：翁老视觉验收；Customizer 各设置项实际效果微调；可选补充 .pot 翻译模板

### 2026-08-02（傍晚）
- 修复：PC 全屏也显示三横（汉堡）按钮——根因是主题原有 .qy-header-tools button { display:inline-flex } 特异性(0,1,1)覆盖了 .qy-menu-toggle { display:none }(0,1,0)，按钮在所有宽度下都被强制显示。修复：menu-toggle 规则提升为 .qy-header-tools .qy-menu-toggle。CDP 实测 1400px 隐藏 / 800px 显示
- 修复：翁老反馈手机轮播图片显示不全（16:9 cover 裁掉 8:3 图的左右文字）→ 移动端轮播 aspect-ratio auto + img height auto 完整显示
- 交付 Gwolle Guestbook v5.0.2 完整中文翻译（719 条，官方包仅 10 条）

