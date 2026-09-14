# 部署指南

**简体中文** · [English](DEPLOYMENT.en.md)

[项目介绍](../README.md) · [开发手册](DEVELOPMENT.md)

推荐使用预构建 Docker 镜像；宿主机无需安装 PHP、Composer 或 Node.js。默认部署包含 MySQL 8、PHP-FPM、Nginx 和一次性数据库迁移服务。以下命令在项目根目录执行，文件复制示例采用 Linux shell 语法。

## Docker 部署

### 1. 准备配置

安装 Docker Engine 和支持 `up --wait` 的 Docker Compose v2。在全新项目目录执行：

```sh
git clone https://github.com/GeneralPeople1970/smartphone-catalog.git
cd smartphone-catalog
cp .env.docker.example .env
docker compose -f compose.deploy.yml run --rm --no-deps app php artisan key:generate --show
```

最后一条命令只输出密钥，不启动数据库。将完整的 `base64:...` 写入 `.env` 的 `APP_KEY`，并填写以下配置：

| 配置                               | 取值                                             |
| ---------------------------------- | ------------------------------------------------ |
| `APP_ENV` / `APP_DEBUG`            | 保持 `production` / `false`                      |
| `APP_URL`                          | 实际访问地址，例如 `https://catalog.example.com` |
| `DB_PASSWORD` / `DB_ROOT_PASSWORD` | 两个不同的随机强密码，替换模板中的占位值         |
| `SESSION_SECURE_COOKIE`            | HTTPS 用 `true`；HTTP 用 `false`                 |
| `WEB_PORT`                         | 默认 `8080`，按宿主机端口安排调整                |

使用 HTTP 测试时可设 `APP_URL=http://127.0.0.1:8080`。`APP_KEY` 仅在首次安装时生成，后续更新和恢复沿用原值；妥善保存 `.env`，不要提交到 Git。

### 2. 启动并初始化账号

```sh
docker compose -f compose.deploy.yml up -d --pull always --wait
```

Compose 会等待 MySQL 就绪，执行迁移，再启动应用并等待健康检查。失败时命令返回非零状态；排障命令见下一节。

通过 `APP_URL` 访问站点，在 `/register` 注册账号，然后将该邮箱对应的账号设为所有者：

```sh
docker compose -f compose.deploy.yml exec app php artisan user:promote owner@example.com --role=owner
```

将示例邮箱替换为实际注册邮箱，确认后进入 `/dashboard`。命令只调整已有账号，`--force` 可跳过交互确认。新安装没有机型数据，可在后台添加或导入 JSON。注册邮箱验证默认关闭，首次初始化无需邮件服务。

### 3. 检查状态与日志

```sh
docker compose -f compose.deploy.yml ps --all
curl -fsS http://127.0.0.1:8080/up
docker compose -f compose.deploy.yml logs --tail=100
```

若调整了 `WEB_PORT`，同步修改检查地址。`/up` 检查应用是否能启动和响应，不包含独立的数据库或邮件探测；发布后还应确认机型页面、登录和上传正常。

模板默认把 Laravel 日志写入 app 容器的文件：

```sh
docker compose -f compose.deploy.yml exec app tail -n 100 storage/logs/laravel.log
```

可将 `.env` 中的 `LOG_CHANNEL` 改为 `stderr`，重新执行启动命令创建容器后，统一通过 `docker compose logs` 收集应用日志。默认文件日志没有持久卷，需按运维需要配置日志采集与保留。

## 配置与网络

### HTTPS 与反向代理

容器内 Nginx 只提供 HTTP。生产环境由外层反向代理配置证书、HTTP 到 HTTPS 跳转及 HSTS，并将流量转发到发布端口。保留原始 `Host`，正确传递 `X-Forwarded-For` 和 `X-Forwarded-Proto`。默认端口映射监听宿主机所有接口；按网络边界限制直接访问。

项目没有预设可信代理名单。若在代理后运行，在 [`bootstrap/app.php`](../bootstrap/app.php) 的 `withMiddleware` 回调中配置应用实际看到的受信代理地址，例如：

```php
$middleware->trustProxies(at: ['10.0.0.10']);
```

替换示例 IP，并使用下文的源码构建流程发布配置。不要直接信任任意客户端提交的转发头；`APP_URL` 本身不会让 Laravel 信任代理。手动部署且 Nginx 直接终止 TLS 时，可使用本文末尾的配置。

### CSP

应用通过 [`SecurityHeaders`](../app/Http/Middleware/SecurityHeaders.php) 输出内容安全策略。`script-src` 保留 `'unsafe-inline'` 和 `'unsafe-eval'`，以兼容前台内联引导脚本与后台 Alpine.js。代理额外添加的 CSP 会与应用策略同时生效，直接套用更严格的策略可能阻止页面脚本。

移除这两项前，需为内联脚本和响应策略同步配置每请求 nonce，切换到 Alpine 的 CSP 构建并调整相关表达式，再验证前台页面、后台表单与导航。

### 上传、缓存与邮件

- **上传**：轮播图上限为 20 MiB。容器配置为 Nginx `client_max_body_size 22m`，PHP `upload_max_filesize=24M`、`post_max_size=24M`、`memory_limit=256M`；外层代理应允许相应请求大小。修改限制时同时检查代理、PHP 和应用校验。
- **文件存储**：上传写入 `public` 磁盘的 `storage/app/public/`。该路径需持久化并可写；仅修改 `FILESYSTEM_DISK` 不会把轮播图迁移到对象存储。`/storage/` 应只提供静态文件，禁止执行脚本。
- **共享状态**：默认 `CACHE_STORE=database`、`SESSION_DRIVER=database`。迁移会创建相关表；所有应用进程应使用相同的共享缓存和锁表。更换缓存后端时必须支持共享原子锁，不能使用进程内 `array` 缓存。
- **邮件**：默认 `MAIL_MAILER=log` 不会实际投递。开启后台“站点设置”中的注册邮箱验证前，配置并验证真实 SMTP，包括发件地址。验证码同步发送，不依赖队列 worker；修改邮件配置后重新创建容器以刷新配置。
- **搜索**：默认 `CATALOG_SEARCH_DRIVER=like`。MySQL 上完成 ngram 全文索引迁移后，可切换为 `fulltext`；这不是其他数据库的通用全文搜索配置。

## 更新、备份与回滚

每次发布前备份数据库、上传文件和 `.env`，记录当前镜像版本。数据库使用 MySQL 备份工具或一致性快照；备份应包含同一时间点的相关数据，并在独立环境验证恢复。

| 数据       | Docker 持久化位置                              |
| ---------- | ---------------------------------------------- |
| MySQL 数据 | `db-data` 命名卷                               |
| 上传文件   | `uploads` 命名卷；挂载到 `storage/app/public/` |
| 配置与密钥 | 宿主机 `.env`，单独安全备份                    |

保持相同的 Compose 项目名称，避免切换目录后连接到新建卷。容器重建会保留命名卷；`docker compose down -v` 会删除卷及其中数据。已有 MySQL 卷不会随 `.env` 修改自动更改数据库账号密码，密码轮换需要同步更新库内账号和应用配置。

默认的 `runtime` 和 `web` 标签会随发布更新。生产环境可将 `.env` 中的两个镜像固定到同一已发布版本；下列版本号仅为示例：

```dotenv
DOCKER_APP_IMAGE=generalpeople/smartphone-catalog:runtime-v1.0.0
DOCKER_WEB_IMAGE=generalpeople/smartphone-catalog:web-v1.0.0
```

确认新版本的配置与迁移要求后，执行：

```sh
docker compose -f compose.deploy.yml up -d --pull always --wait
```

修改 `.env` 后也使用此命令，单纯重启旧容器不会载入新的环境变量。应用启动时会重建 Laravel 配置、路由和视图缓存。

回滚时选择此前匹配的 `runtime` / `web` 镜像，并先确认旧代码兼容当前数据库。镜像回滚不会撤销迁移；涉及不兼容结构或数据变更时，需要恢复相应数据库备份。不要把 `migrate:rollback` 当作通用恢复步骤，也不要重新生成 `APP_KEY`。

镜像发布配置见 [GitHub Actions 工作流](../.github/workflows/publish-images.yml)。仓库维护者需要配置 `DOCKERHUB_USERNAME` 与 `DOCKERHUB_TOKEN`；部署公开镜像不需要这些发布凭据。

## 从源码构建镜像

需要修改代码或容器配置时，在全新克隆中使用 [`compose.yml`](../compose.yml)。环境变量与备份要求同上：

```sh
cp .env.docker.example .env
docker compose build
docker compose run --rm --no-deps app php artisan key:generate --show
```

填写 `APP_KEY`、访问地址、数据库密码与 Cookie 配置，再执行：

```sh
docker compose run --rm migrate
docker compose up -d --wait
```

在站点注册后初始化所有者：

```sh
docker compose exec app php artisan user:promote owner@example.com --role=owner
```

源码构建版的迁移服务位于 `tools` profile，必须显式执行；以后更新按“备份、取得新源码、构建、迁移、启动”的顺序进行。构建会生成 PHP 依赖和两套前端资源。代码与配置变化需要重新构建镜像，直接编辑运行中的容器不能作为发布方式。

## 手动部署

服务器需要 PHP 8.5、Composer 2、PHP-FPM、Nginx，以及 SQLite 或 MySQL。启用 Composer 依赖要求的扩展、对应 PDO 驱动、`fileinfo` 和支持 JPEG、PNG、WebP、GIF 的 `gd`。仓库已提交前端构建产物，运行时无需 Node.js；重新构建请按 [开发手册](DEVELOPMENT.md) 安装构建依赖。

### 安装与发布

```sh
git clone https://github.com/GeneralPeople1970/smartphone-catalog.git
cd smartphone-catalog
composer install --no-dev --optimize-autoloader
cp .env.example .env
php artisan key:generate
```

按上文填写生产配置。使用 MySQL 时先创建数据库和可执行迁移的账号，设置 `DB_CONNECTION=mysql` 及 `DB_*`；使用默认 SQLite 时先创建文件：

```sh
php -r "file_exists('database/database.sqlite') || touch('database/database.sqlite');"
```

Web 进程必须能写入 `storage/`、`bootstrap/cache/`，SQLite 部署还需能写入数据库文件及所在目录。持久化上传目录；发布切换时保留 `.env`、数据库和上传文件。

```sh
php artisan migrate --force
php artisan storage:link
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

将 Nginx 网站根目录指向项目的 `public/`，通过 PHP-FPM 提供服务。在 `/register` 注册后运行：

```sh
php artisan user:promote owner@example.com --role=owner
```

后续发布保留原密钥与数据，安装锁定的 Composer 依赖，执行迁移并刷新以上缓存，再按服务器配置重新加载 PHP-FPM。生产上传限制、共享缓存、邮件与备份要求同 Docker 部署。

### Nginx 示例

此示例由 Nginx 直接终止 TLS。替换域名、证书路径、项目路径和 PHP-FPM socket，验证 Nginx 配置后再重新加载。构建资源使用长期缓存；页面路由交给 Laravel，只有前控制器 `index.php` 可以执行。

```nginx
server {
    listen 443 ssl;
    server_name example.com;
    ssl_certificate /etc/letsencrypt/live/example.com/fullchain.pem;
    ssl_certificate_key /etc/letsencrypt/live/example.com/privkey.pem;

    root /var/www/smartphone-catalog/public;
    index index.php;
    client_max_body_size 22m;
    error_page 404 /404.html;

    add_header X-Content-Type-Options "nosniff" always;
    add_header Strict-Transport-Security "max-age=31536000" always;

    location ~ /\.(?!well-known).* {
        deny all;
    }

    location ~* ^/storage/.*\.(php[0-9]?|pht|phtml|phps|phar|pl|py|cgi|sh|shtml)(/|$) {
        deny all;
    }

    location ~* ^/(build|frontend)/assets/.*\.(css|js|mjs|woff2?)$ {
        try_files $uri =404;
        add_header X-Content-Type-Options "nosniff" always;
        add_header Strict-Transport-Security "max-age=31536000" always;
        add_header Cache-Control "public, max-age=31536000, immutable" always;
    }

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location = /index.php {
        include fastcgi_params;
        fastcgi_param SCRIPT_FILENAME $realpath_root/index.php;
        fastcgi_pass unix:/run/php/php8.5-fpm.sock;
        fastcgi_hide_header X-Powered-By;
    }

    location ~ \.php$ {
        return 404;
    }
}

server {
    listen 80;
    server_name example.com;
    return 301 https://$host$request_uri;
}
```

Docker 使用独立的 [`docker/nginx/default.conf`](../docker/nginx/default.conf)，其中上传目录从共享卷直接提供，无需套用本段的宿主机路径。
