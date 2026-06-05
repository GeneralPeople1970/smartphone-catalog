# 前台说明

`frontend/` 是公开前台的 Vue 应用，使用 Vue 3、Vue Router history 模式、Bootstrap 5 和 Vite。

## 开发

在项目根目录安装依赖：

```bash
npm ci
npm --prefix frontend ci
```

启动前台开发服务器：

```bash
npm run dev:frontend
```

默认接口地址为同域 `/api`。如果前台 Vite 开发服务器和 Laravel 服务分开运行，可以在 `frontend/.env.local` 配置代理：

```dotenv
VITE_API_PROXY_TARGET=http://127.0.0.1:8000
```

## 构建

```bash
npm run build:frontend
```

构建结果输出到根项目的 `public/frontend/`。完整项目构建可在根目录执行：

```bash
npm run build
```

## 检查

```bash
npm --prefix frontend run lint
npm --prefix frontend run format:check
```

根目录也提供统一检查命令：

```bash
npm run check
```
