# Smartphone Catalog

**简体中文** · [English](README.en.md)

[![CI](https://github.com/GeneralPeople1970/smartphone-catalog/actions/workflows/ci.yml/badge.svg?branch=main)](https://github.com/GeneralPeople1970/smartphone-catalog/actions/workflows/ci.yml)
[![License: MIT](https://img.shields.io/badge/License-MIT-blue.svg)](LICENSE)

基于 Laravel 与 Vue 的开源智能手机参数站，包含公开前台、管理后台和只读 API，用于搭建和维护自己的机型目录。

[快速开始](#快速开始) · [部署](#部署) · [开发手册](docs/DEVELOPMENT.md) · [API 文档](docs/api.md)

## 功能

- **机型浏览**：品牌目录、关键词搜索、参数详情与按需分页，适配手机和桌面，主题跟随系统。
- **内容管理**：机型新增、编辑、上下架与 JSON 批量导入；导入包含字段校验，失败时整批回滚。
- **首页管理**：配置轮播图与热门推荐，调整顺序和上架状态。
- **账号权限**：用户、编辑、管理员、所有者四级角色，支持账号状态管理与邮箱验证。
- **公开 API**：提供品牌、机型、搜索与首页数据，支持字段裁剪、页码及游标分页。

## 技术栈

| 层级       | 技术                                            |
| ---------- | ----------------------------------------------- |
| 公开前台   | Vue 3 · Vue Router 5 · Bootstrap 5              |
| 管理后台   | Laravel 13 · Blade · Tailwind CSS 4 · Alpine.js |
| 数据库     | SQLite / MySQL                                  |
| 构建与测试 | Vite 8 · PHPUnit · Vitest · Playwright          |

## 快速开始

需要 **PHP 8.5、Composer 2**，并启用 Laravel 必需扩展、`fileinfo`、`gd` 和对应的 PDO 数据库驱动。以下使用默认 SQLite；仓库已包含构建产物，直接运行无需 Node.js。

```sh
git clone https://github.com/GeneralPeople1970/smartphone-catalog.git
cd smartphone-catalog
composer install
cp .env.example .env
php artisan key:generate
composer run setup
php artisan storage:link
php artisan serve
```

`composer run setup` 会创建 SQLite 文件并执行迁移。如需 MySQL，请在此步骤前配置 `.env`；同时将 `APP_URL` 设为实际访问地址，本地服务默认为 `http://127.0.0.1:8000`。

打开 [本地站点](http://127.0.0.1:8000)，先在 [注册页面](http://127.0.0.1:8000/register) 创建账号，再另开终端，将注册邮箱对应的账号设为所有者（替换以下示例邮箱）：

```sh
php artisan user:promote owner@example.com --role=owner
```

随后进入 [控制台](http://127.0.0.1:8000/dashboard) 管理内容。新安装的站点没有机型数据，可在后台手动添加或导入 JSON；格式与限制见 [导入说明](docs/DEVELOPMENT.md#手机写入与导入)。

## 部署

推荐使用 **Docker Engine 与 Docker Compose v2**，通过 [预构建镜像](https://hub.docker.com/r/generalpeople/smartphone-catalog) 部署。在全新克隆的项目目录中完成首次配置：

```sh
cp .env.docker.example .env
docker compose -f compose.deploy.yml run --rm --no-deps app php artisan key:generate --show
```

将输出写入 `.env` 的 `APP_KEY`，填写 `APP_URL`，并为 `DB_PASSWORD`、`DB_ROOT_PASSWORD` 设置不同的强密码。HTTPS 保持 `SESSION_SECURE_COOKIE=true`；通过 HTTP 访问时设为 `false`。然后启动：

```sh
docker compose -f compose.deploy.yml up -d --pull always --wait
```

默认端口为 `8080`。访问站点的 `/register` 页面注册账号后，在容器内设置所有者（替换示例邮箱）：

```sh
docker compose -f compose.deploy.yml exec app php artisan user:promote owner@example.com --role=owner
```

部署会自动迁移数据库并等待健康检查，数据库与上传文件保存在命名卷中。更新、备份、版本固定、反向代理与手动部署见 [部署指南](docs/DEPLOYMENT.md)。

## 开发

修改前端资源需要 **Node.js 24.x（≥ 24.11.0）与 npm 11**。在项目根目录安装两套依赖：

```sh
npm ci
npm --prefix frontend ci
```

| 命令                   | 用途                                       |
| ---------------------- | ------------------------------------------ |
| `composer test`        | 后端测试                                   |
| `npm run check`        | 开源边界检查、前端代码与格式检查、单元测试 |
| `npm run build`        | 构建前台与后台资源                         |
| `npm run test:browser` | 浏览器交互与布局回归测试                   |

首次运行浏览器测试前执行 `npx playwright install chromium`。修改前端资源后，需将重新构建的 `public/build/` 与 `public/frontend/` 一并提交，CI 会检查产物是否与源码一致。

本地热更新、目录结构、业务规则和完整测试说明见 [开发手册](docs/DEVELOPMENT.md)；接口路径、参数与响应格式见 [API 文档](docs/api.md)。

## 参与贡献

欢迎通过 [Issues](https://github.com/GeneralPeople1970/smartphone-catalog/issues) 报告问题或讨论改进，通过 Pull Request 提交修改。请说明变更目的，并运行与改动相关的检查。

## 许可证

代码采用 [MIT 许可证](LICENSE)。品牌名称与标志归各自所有者；第三方素材的使用与再分发权限需另行确认。本项目与相关手机厂商无隶属或背书关系。
