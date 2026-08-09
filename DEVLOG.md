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

