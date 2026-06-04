# Vue 公开前台

这是智能手机参数站的 Vue 3 + Vue Router history + Vite 前台子项目。

```bash
npm ci
npm run dev
npm run build
```

生产构建输出到 Laravel 仓库的 `../public/frontend/`。该目录只保存生成产物，构建时会清理旧文件，不应手动编辑或保存上传文件。

默认 API 基础路径为同域 `/api`；本地独立开发时可参考 `.env.example` 配置 `VITE_API_PROXY_TARGET`。

可选的 `VITE_SITE_URL` 用于生成 canonical SEO 地址。项目不包含统计脚本，真实手机目录数据由 Laravel API 从私有数据库提供，不存放在前端源码或构建产物中。
