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
│   │   └── index.css          # 全局样式 (自定义 CSS 类写在这里)
│   ├── package.json
│   └── vite.config.ts
└── dist/                      # 编译后的文件 (由 npm run build 生成)
    ├── js/                    # 编译后的 JS
    ├── css/                   # 编译后的 CSS
    └── *.png, *.ico           # 静态资源
```

## WordPress 页面与 Shortcode 映射

| 页面 | Page ID | Shortcode | 说明 |
|------|---------|-----------|------|
| Dashboard (首页) | 62 | `[dealer_inventory]` | 库存列表页 |
| Cart | 7 | `[dealer_cart]` | 购物车页 |
| Login | 61 | `[dealer_login]` | 登录页 |
| Orders | - | 通过 WooCommerce endpoint hook | `/my-account/orders/` |

**重要：** 如果页面布局不对，先检查 WordPress 页面内容是否使用了正确的 shortcode！

```bash
# 检查页面内容
sshpass -p 'WAUP9pmdREkD' ssh master_dhkqwtswwh@139.180.160.180 "cd /home/1572916.cloudwaysapps.com/pgehamfrpd/public_html && wp post get 7 --field=post_content"

# 更新页面内容为正确的 shortcode
sshpass -p 'WAUP9pmdREkD' ssh master_dhkqwtswwh@139.180.160.180 "cd /home/1572916.cloudwaysapps.com/pgehamfrpd/public_html && wp post update 7 --post_content='[dealer_cart]'"
```

## 代码修改正确流程

### 方法 1: 修改 React 前端代码 (推荐)

```bash
# 1. 编辑本地源文件
#    - frontend/src/pages/*.tsx (页面)
#    - frontend/src/components/**/*.tsx (组件)
#    - frontend/src/index.css (样式)

# 2. 本地 build (重要！服务器没有 npm)
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

### 方法 2: 直接在服务器修改 (紧急调试)

```bash
# 连接服务器
sshpass -p 'WAUP9pmdREkD' ssh master_dhkqwtswwh@139.180.160.180

# 进入目录
cd /home/1572916.cloudwaysapps.com/pgehamfrpd/public_html

# 编辑 PHP 文件
nano wp-content/plugins/dealer-system/dealer-system.php

# 清除缓存
wp cache flush && wp breeze purge --cache=all

# ⚠️ 注意：服务器没有 npm，无法 build 前端代码！
```

**⚠️ 重要：服务器直接修改后必须同步到 git！**

```bash
# 在服务器修改完 PHP 后，立即同步到本地 git：
sshpass -p 'WAUP9pmdREkD' ssh master_dhkqwtswwh@139.180.160.180 "cat /home/1572916.cloudwaysapps.com/pgehamfrpd/public_html/wp-content/plugins/dealer-system/dealer-system.php" > /Users/chenyalin/Documents/stock/wp-content/plugins/dealer-system/dealer-system.php

# 然后提交
cd /Users/chenyalin/Documents/stock
git add -A && git commit -m "Sync server PHP changes" && git push
```

否则下次 `git reset --hard` 会覆盖服务器上的改动！

## 一键部署命令

```bash
# 完整部署流程 (build + push + pull + clear cache)
cd /Users/chenyalin/Documents/stock/wp-content/plugins/dealer-system/frontend && npm run build && cd /Users/chenyalin/Documents/stock && git add -A && git commit -m "Update" && git push origin main && sshpass -p 'WAUP9pmdREkD' ssh master_dhkqwtswwh@139.180.160.180 "cd /home/1572916.cloudwaysapps.com/pgehamfrpd/public_html && git fetch origin && git reset --hard origin/main && wp cache flush && wp breeze purge --cache=all"
```

## 部署后验证命令

```bash
# 检查服务器上的 git 版本
sshpass -p 'WAUP9pmdREkD' ssh master_dhkqwtswwh@139.180.160.180 "cd /home/1572916.cloudwaysapps.com/pgehamfrpd/public_html && git log -1 --oneline"

# 检查 CSS 是否包含某个类
sshpass -p 'WAUP9pmdREkD' ssh master_dhkqwtswwh@139.180.160.180 "grep 'page-container' /home/1572916.cloudwaysapps.com/pgehamfrpd/public_html/wp-content/plugins/dealer-system/dist/css/style.css"

# 检查 JS 是否包含某个类
sshpass -p 'WAUP9pmdREkD' ssh master_dhkqwtswwh@139.180.160.180 "grep -o 'page-container' /home/1572916.cloudwaysapps.com/pgehamfrpd/public_html/wp-content/plugins/dealer-system/dist/js/cart.js"
```

## 常见问题排查

### Q: 改了代码但网页没变化？

按顺序检查：

1. **本地是否 build 了？**
   ```bash
   cd /Users/chenyalin/Documents/stock/wp-content/plugins/dealer-system/frontend && npm run build
   ```

2. **服务器是否拉取了最新代码？**
   ```bash
   sshpass -p 'WAUP9pmdREkD' ssh master_dhkqwtswwh@139.180.160.180 "cd /home/1572916.cloudwaysapps.com/pgehamfrpd/public_html && git log -1 --oneline"
   ```

3. **服务器缓存是否清除了？**
   ```bash
   sshpass -p 'WAUP9pmdREkD' ssh master_dhkqwtswwh@139.180.160.180 "cd /home/1572916.cloudwaysapps.com/pgehamfrpd/public_html && wp cache flush && wp breeze purge --cache=all"
   ```

4. **WordPress 页面是否使用了正确的 shortcode？**
   ```bash
   # 检查 cart 页面 (ID=7)
   sshpass -p 'WAUP9pmdREkD' ssh master_dhkqwtswwh@139.180.160.180 "cd /home/1572916.cloudwaysapps.com/pgehamfrpd/public_html && wp post get 7 --field=post_content"
   # 应该输出: [dealer_cart]
   # 如果是 WooCommerce block，需要更新为 shortcode
   ```

5. **CSS 类是否生成了？**
   ```bash
   sshpass -p 'WAUP9pmdREkD' ssh master_dhkqwtswwh@139.180.160.180 "grep '你的类名' /home/1572916.cloudwaysapps.com/pgehamfrpd/public_html/wp-content/plugins/dealer-system/dist/css/style.css"
   ```

### Q: Tailwind 任意值 (如 `pt-[120px]`) 不生效？

Tailwind v4 的任意值可能不会自动生成 CSS。**解决方案：在 `index.css` 中定义自定义类：**

```css
/* frontend/src/index.css */
.page-container {
  min-height: 100vh;
  background-color: white;
  padding-top: 120px;
  padding-bottom: 80px;
}

.page-content {
  width: 100%;
  max-width: 80vw;
  margin: 0 auto;
  padding-left: 16px;
  padding-right: 16px;
  box-sizing: border-box;
}
```

然后在 React 中使用：
```tsx
<div className="page-container">
  <div className="page-content">
    ...
  </div>
</div>
```

### Q: 样式被 WordPress 覆盖？

**方法 1: 使用 `!` 前缀**

```tsx
// ❌ 可能被覆盖
<Button className="bg-black text-white" />

// ✅ 使用 ! 前缀
<Button className="!bg-black !text-white !border !border-white/20" />
```

**方法 2: 使用 inline style (当 `!` 前缀无效时)**

当 Tailwind 的 `!` 前缀仍然被 WordPress 覆盖时，使用 inline `style={{}}` 属性来确保样式生效：

```tsx
// ❌ Tailwind class 可能被覆盖
<div className="pt-5 pb-5 border-b border-gray-100">

// ✅ 使用 inline style 确保生效
<div style={{ paddingTop: '20px', paddingBottom: '20px', borderBottom: '1px solid #f3f4f6' }}>

// ✅ 也可以混合使用
<div
  style={{ marginTop: '32px', display: 'flex', flexDirection: 'column', gap: '12px' }}
  className="other-classes"
>
```

**优先级顺序：** inline style > `!important` > 普通 CSS

**⚠️ 注意：** 这种情况常见于 spacing 类 (padding, margin, gap) 和 border 类，因为 WordPress 主题通常会覆盖这些样式。

## 技术栈

- **后端**: WordPress + WooCommerce + PHP
- **前端**: React 18 + TypeScript + Vite + Tailwind CSS v4
- **UI 组件**: shadcn/ui (Button, Input, Table)
- **动画**: Framer Motion
- **构建**: Vite + esbuild

## 重要页面 URL

| 页面 | URL | 条件 |
|------|-----|------|
| 登录页 | `/login/` | 未登录用户 |
| 库存页 (首页) | `/` | 已登录用户 |
| 购物车 | `/cart/` | 已登录用户 |
| 订单页 | `/my-account/orders/` | 已登录用户 |
