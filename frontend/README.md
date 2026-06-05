# Vue 公开前台

这是仓库内的 Vue 3 + Vue Router history + Vite 前台子项目。前台通过 Laravel API 获取运行时内容，生产构建由根目录脚本统一调用。

## 命令

```bash
npm ci
npm run dev
npm run build
npm run lint
npm run format:check
```

## 环境

- Node.js `^22.18.0 || >=24.11.0`
- npm 使用 Node.js 附带版本即可

独立运行前台 Vite 时，可在 `.env.local` 配置后端代理：

```dotenv
VITE_API_PROXY_TARGET=http://127.0.0.1:8000
```

可选的 `VITE_SITE_URL` 用于生成 canonical SEO 地址：

```dotenv
VITE_SITE_URL=https://example.com
```

未设置 `VITE_SITE_URL` 时不会输出 canonical 链接。项目不包含第三方统计脚本。

## 构建输出

生产构建输出到 Laravel 仓库的 `../public/frontend/`。该目录只保存生成产物，构建时会清理旧文件，不应手动编辑或保存上传文件。
