# 青简主题（QingJian / qingya）

> 青竹为简，记录时光。一个面向中文博客与展示站点的轻量 WordPress 主题。

## ✨ 简介

青简是一款完全自研的 WordPress 经典主题（无第三方框架依赖），适用于：

- 个人/自媒体博客：文章分类、标签、归档、评论、相关推荐
- 资讯内容站：图文列表、置顶推荐、专题聚合
- 轻量化产品展示站：产品分类、详情页、图文介绍、留言咨询
- 展示站：首页轮播、图文展示、关于我们、联系表单

**设计语言**：闲适清简、书卷气。暖米底色 + 竹青主色 + 深墨绿页脚，深浅色模式一键切换。

## 📦 特性

### 基础能力
- **后台可视化配置**（Customizer，实时预览）：LOGO、顶部公告、联系方式、底部版权、ICP 备案号、友情链接、6 项配色、字体、布局、LOGO 高度等
- **一键换肤**：内置 4 套配色方案（竹青书卷 / 水墨素简 / 青灰现代 / 暖咖复古），每套含配套深色模式
- **页面模板**：首页（轮播+图文+列表）、全宽页、无侧边栏页、404 页
- **全局模块**：自适应导航 + 移动端汉堡菜单、搜索、登录入口、面包屑、分页、返回顶部、图片懒加载

### 进阶功能
- 文章浏览量统计（Cookie 防刷）、点赞、收藏（AJAX + nonce 校验）
- 阅读进度条、滚动渐入动画、深色/浅色模式切换（记忆用户偏好）
- 侧边栏小工具扩展：热门文章（按浏览量）、最新文章、随机文章
- 文章/页面级 Meta：独立 SEO 标题/关键词/描述、独立布局、隐藏标题

### 🔒 IP 黑名单系统（内置）
- 后台可视化批量录入：单 IP、IP 段（`192.168.1.*`）、CIDR（`123.45.67.0/24`）
- 拦截策略：403 / 跳转提示页 / 跳转指定 URL
- 白名单豁免 + 搜索引擎蜘蛛自动放行（百度/谷歌等）
- 访问日志记录（自定义数据表），后台可查可一键清空
- 总开关 + "仅前台拦截"模式（不影响后台登录）

### 🔍 SEO 原生优化（无插件依赖）
- 自动生成标准化 TDK，支持单篇/单页独立自定义
- 面包屑 + 文章 JSON-LD 结构化数据
- 图片 ALT 自动补充、canonical 规范化、robots.txt 兼容
- 冗余代码清理（shortlink/RSD 等）

### ⚡ 性能
- 资源版本化（filemtime 自动刷新缓存）、按需加载、脚本 defer
- 图片懒加载、CDN 域名配置（主题静态资源一键走 CDN）
- 可选精简模式（移除 emoji/embed 脚本）、可选禁用 jQuery Migrate

### 🛡 安全
- 移除 WP 版本信息、常见扫描器 UA 屏蔽（sqlmap/wpscan 等）
- 输入长度/分页参数校验、登录失败次数限制（可开关）
- 目录访问保护（.htaccess + 空 index）

## 🚀 安装

1. 上传 `qingya-1.0.0.zip`：后台「外观 → 主题 → 安装主题 → 上传主题」，或解压至 `wp-content/themes/qingya/`
2. 启用主题
3. 前往「外观 → 自定义」进行可视化配置
4. 可选：创建页面选用「首页」模板，在「设置 → 阅读」中设为站点首页

### 环境要求
- WordPress ≥ 6.0（已测 6.8 / 7.0 兼容）
- PHP ≥ 7.4（兼容 8.x）

## 📁 目录结构

```
qingya/
├── style.css              主题头信息 + 设计变量
├── functions.php          模块加载器（唯一入口）
├── inc/                   功能模块（低耦合，按需加载）
│   ├── setup.php          主题初始化（支持/菜单/侧边栏/动态 CSS）
│   ├── customizer.php     后台可视化配置（8 大分区 + 4 套配色方案）
│   ├── seo.php            原生 SEO（TDK/结构化数据/robots/ALT）
│   ├── security.php       安全防护（版本隐藏/UA 屏蔽/输入过滤）
│   ├── ip-blacklist.php   IP 黑名单核心逻辑
│   ├── performance.php    性能优化（版本化/CDN/懒加载/按需加载）
│   ├── template-tags.php  模板辅助（面包屑/浏览量/分页/相关文章）
│   ├── widgets.php        侧边栏小工具扩展
│   ├── meta-boxes.php     文章/页面独立 SEO 与布局 Meta
│   └── ajax.php           点赞/收藏/浏览量 AJAX 端点
├── admin/ip-blacklist.php IP 黑名单后台管理页
├── page-templates/        首页/全宽/无侧边栏模板
├── template-parts/        页头/页脚/内容片段
└── assets/                css/js/img
```

## 🛠 开发

- 编码规范：WordPress Coding Standards
- 资源：原生 CSS/JS（零第三方依赖），系统字体
- 模块加载：`functions.php` 按清单 require，前台零冗余

```bash
# 语法检查
php -l functions.php && php -l inc/*.php

# 重新打包（Windows PowerShell 注意：请用 Python 打包保证正斜杠路径）
python -c "import zipfile,os; z=zipfile.ZipFile('qingya-1.0.0.zip','w',zipfile.ZIP_DEFLATED); [z.write(os.path.join(r,f), os.path.join('qingya',f).replace(chr(92),'/')) for r,d,fs in os.walk('qingya') for f in fs]; z.close()"
```

## 📜 版本记录

### v1.0.0（2026-07-31）
- 初始版本：全套模板、Customizer、SEO、IP 黑名单、性能、安全、深色模式、4 套配色方案

## 📄 许可证

GPL v2 or later
