# ZEEKR Dealer System 开发指南

## 服务器信息

- **SSH 连接**: `sshpass -p 'WAUP9pmdREkD' ssh master_dhkqwtswwh@139.180.160.180`
- **SSH 密码**: `WAUP9pmdREkD`
- **网站路径**: `/home/1572916.cloudwaysapps.com/pgehamfrpd/public_html`
- **插件路径**: `/home/1572916.cloudwaysapps.com/pgehamfrpd/public_html/wp-content/plugins/dealer-system`
- **网站 URL**: `https://wordpress-1572916-6134854.cloudwaysapps.com`

## 项目结构

```
dealer-system/
├── dealer-system.php          # 主 PHP 插件文件 (后端逻辑、短代码、样式)
├── frontend/
│   ├── src/
│   │   ├── pages/             # React 页面 (login.tsx, inventory.tsx, cart.tsx, orders.tsx)
│   │   ├── components/        # React 组件 (ui/, backgrounds/)
│   │   └── index.css          # 全局 Tailwind 样式
│   ├── package.json
│   └── vite.config.ts
└── dist/                      # 编译后的文件 (由 npm run build 生成)
    ├── js/                    # 编译后的 JS
    ├── css/                   # 编译后的 CSS
    └── *.png, *.ico           # 静态资源
```

## 代码修改正确流程

### 方法 1: 修改 React 前端代码 (推荐)

```bash
# 1. 编辑本地源文件
#    - frontend/src/pages/*.tsx (页面)
#    - frontend/src/components/**/*.tsx (组件)
#    - frontend/src/index.css (样式)

# 2. 本地 build (重要！)
cd /Users/chenyalin/Documents/stock/wp-content/plugins/dealer-system/frontend
npm run build

# 3. 提交并推送到 GitHub
cd /Users/chenyalin/Documents/stock
git add -A
git commit -m "描述改动"
git push origin main

# 4. 服务器拉取代码并清除缓存
sshpass -p 'WAUP9pmdREkD' ssh master_dhkqwtswwh@139.180.160.180 "cd /home/1572916.cloudwaysapps.com/pgehamfrpd/public_html && git fetch origin && git reset --hard origin/main && wp cache flush && wp breeze purge --cache=all"
```

### 方法 2: 修改 PHP 代码

```bash
# 1. 编辑本地 dealer-system.php

# 2. 提交并推送
git add -A && git commit -m "描述" && git push origin main

# 3. 服务器拉取并清除缓存
sshpass -p 'WAUP9pmdREkD' ssh master_dhkqwtswwh@139.180.160.180 "cd /home/1572916.cloudwaysapps.com/pgehamfrpd/public_html && git pull origin main && wp cache flush && wp breeze purge --cache=all"
```

### 方法 3: 直接在服务器修改 PHP (紧急情况)

```bash
# 连接服务器
sshpass -p 'WAUP9pmdREkD' ssh master_dhkqwtswwh@139.180.160.180

# 编辑文件
nano /home/1572916.cloudwaysapps.com/pgehamfrpd/public_html/wp-content/plugins/dealer-system/dealer-system.php

# 清除缓存
cd /home/1572916.cloudwaysapps.com/pgehamfrpd/public_html
wp cache flush && wp breeze purge --cache=all

# ⚠️ 注意：服务器没有 npm，无法 build 前端代码！
# 前端 React 代码必须在本地 build 后推送
```

## 一键部署命令

```bash
# 完整部署流程 (build + push + pull + clear cache)
cd /Users/chenyalin/Documents/stock/wp-content/plugins/dealer-system/frontend && npm run build && cd /Users/chenyalin/Documents/stock && git add -A && git commit -m "Update" && git push origin main && sshpass -p 'WAUP9pmdREkD' ssh master_dhkqwtswwh@139.180.160.180 "cd /home/1572916.cloudwaysapps.com/pgehamfrpd/public_html && git fetch origin && git reset --hard origin/main && wp cache flush && wp breeze purge --cache=all"
```

## 清除缓存命令

```bash
# 在服务器上执行
wp cache flush                    # WordPress 对象缓存
wp breeze purge --cache=all       # Breeze 本地缓存 + Varnish
wp breeze purge --cache=varnish   # 仅 Varnish
```

## 常见问题

### Q: 改了代码但网页没变化？
1. ✅ 确认已运行 `npm run build` (前端代码必须编译)
2. ✅ 确认已 `git push` 并在服务器 `git pull`
3. ✅ 清除服务器缓存 (`wp cache flush && wp breeze purge --cache=all`)
4. ✅ 浏览器强制刷新 (Ctrl+Shift+R 或 Cmd+Shift+R)

### Q: 为什么 JS/CSS 版本号用 time()？
PHP 已配置使用 `time()` 作为版本号，每次页面加载都会请求最新文件，避免浏览器缓存问题。

### Q: 服务器上能直接 build 前端吗？
不能！服务器没有安装 Node.js/npm。前端代码必须在本地 build 后推送。

### Q: Git 冲突怎么办？
```bash
# 在服务器上强制重置到远程版本
git fetch origin && git reset --hard origin/main
```

## 重要页面 URL

| 页面 | URL | 条件 |
|------|-----|------|
| 登录页 | `/login/` | 未登录用户 |
| 库存页 | `/inventory/` | 已登录用户 |
| 购物车 | `/cart/` | 已登录用户 |
| 订单页 | `/my-account/orders/` | 已登录用户 |

## 技术栈

- **后端**: WordPress + WooCommerce + PHP
- **前端**: React 18 + TypeScript + Vite + Tailwind CSS v4
- **UI 组件**: shadcn/ui (Button, Input, Table)
- **动画**: Framer Motion
- **构建**: Vite + esbuild
