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

