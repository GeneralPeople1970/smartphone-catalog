# 智能手机目录框架

Laravel + Vue 单仓项目，包含公开前台、`/api` 接口和 Blade 管理后台。

## 环境要求

- PHP `>=8.4.1 <9.0`
- Composer `>=2.2`
- Node.js `^22.18.0 || >=24.11.0`
- npm
- SQLite、MySQL 或其他 Laravel 支持的数据库

## 快速开始

```bash
composer install
npm ci
npm --prefix frontend ci
cp .env.example .env
php artisan key:generate
php artisan migrate
php artisan storage:link
composer run dev
```

## 文档

- [开发手册](docs/DEVELOPMENT.md)
- [API 手册](docs/API.md)

## 运行边界

- 生产环境 Web 根目录必须指向 `public/`。
