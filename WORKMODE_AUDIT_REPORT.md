# Smartphone Catalog 部署、测试与安全审计报告

审计日期：2026-07-16
审计基线：`825bddb7774ff55874ccb005cd8f3d53e67de079`
本地工作分支：`workmode/deployment-audit`
最终结论：**Conditionally Ready**

## 1. 执行摘要

本次审计实际克隆并运行了项目，而不是仅做静态评审。Laravel 以 `APP_ENV=production`、`APP_DEBUG=false` 在隔离 SQLite 数据库上启动，`/up`、首页、构建资源、公开 API、认证与后台 CRUD 完成 87 项 HTTP 巡检并全部通过。SQLite 与真实 MySQL 8.0.46 分别执行完整 PHPUnit 套件，最终均为 **158 tests / 650 assertions / 0 failures**；前端 ESLint、Prettier、11 项 Node 测试、后台与 SPA 生产构建均通过。

确认 6 个项目缺陷：Critical 0、High 1、Medium 4、Low 1；6 个均已最小化修复并补回归测试。修复覆盖 MySQL FULLTEXT 测试隔离、公共 API URL 净化、注册/API 限流键冲突、Docker Nginx 上传图片 404、SPA 空白 404 页面、HTTPS 页面混合内容图片。

完整 Docker 部署未能执行：当前容器可以运行 Compose 2.40.3 的配置解析，但访问 Docker daemon socket 被沙箱拒绝。因此镜像构建、Compose 启动、容器重启、卷持久化、镜像内容/大小和实际容器用户检查均为 `Environment Blocked`。云浏览器也无法路由到进程本地的 loopback 地址。由于发布完成标准明确要求 Docker build/up、持久化和浏览器巡检，结论不能是 Ready，只能是 **Conditionally Ready**。

主测试矩阵最终执行计数为 **414 passed / 0 failed**（SQLite 158 + MySQL 158 + 前端 11 + HTTP 87），另有 6/6 Nginx 运行时探针符合预期。定向安全套件 83 项是上述 PHPUnit 套件的重复抽取，不计入 414 的唯一执行总数。

## 2. 审计范围

已阅读和分析：

- `README.md`、`docs/DEVELOPMENT.md`、`docs/api.md`
- `composer.json`、根 `package.json`、`frontend/package.json` 及三份 lock 文件
- `phpunit.xml`、`.env.example`、`.env.docker.example`
- `Dockerfile`、`compose.yml`、`.dockerignore`、`docker/entrypoint.sh`、`docker/release.sh`
- Docker Nginx、PHP-FPM、OPcache、上传限制配置
- `.github/workflows/ci.yml` 及 Dependabot/仓库边界配置
- `routes/`、所有 migrations、seeders、models、controllers、middleware、policies、tests
- Vue Router、视图、API client、主题和 URL/图片净化逻辑

实际执行覆盖：依赖安装、平台要求、语法、Pint、PHPUnit、SQLite/MySQL 迁移和回滚、配置/路由/视图缓存、npm audit、ESLint、Prettier、Node tests、双 Vite build、Gitleaks、生产模式 HTTP、API/认证/权限/CRUD、安全输入、限流、Nginx 上传与敏感路径探针、Compose 配置解析和 Docker daemon 探测。

未连接任何生产系统；数据库、用户、密码、端口和上传探针均为本次任务创建的隔离资源。未创建或覆盖仓库 `.env`，未触碰已有 Docker volume，未执行 push、PR、Release 或远程设置修改。

## 3. 仓库版本和测试环境

| 项目 | 实际值 | 结论 |
| --- | --- | --- |
| 仓库 | `GeneralPeople1970/smartphone-catalog` | 已克隆 |
| Commit | `825bddb7774ff55874ccb005cd8f3d53e67de079` | 审计基线 |
| 分支 | `workmode/deployment-audit` | 本地分支，无本地 commit |
| 初始工作区 | clean | 修改前已确认 |
| OS | Ubuntu 24.04.3 LTS，Linux 6.12.47，x86_64 | 满足工具运行 |
| PHP | 8.5.8 | 满足 `^8.5` |
| Composer | 2.10.2 | 满足 2.x |
| Laravel | 13.20.0 | 与 lock 一致 |
| Node.js | 24.14.0 | 满足 `^24.11.0` |
| npm | 11.9.0 | 满足要求 |
| SQLite | PHP 8.5 `pdo_sqlite` / `sqlite3` | 可用 |
| MySQL | 8.0.46，`utf8mb4_unicode_ci` | 真实服务验证 |
| Nginx 验证器 | 1.24.0 Ubuntu | 实际解析并运行 location 规则 |
| Docker Compose | 2.40.3 | 配置解析成功 |
| Docker daemon | socket access `operation not permitted` | Environment Blocked |
| 磁盘 | 63G 总量，54G 可用，11% 使用 | 充足 |
| 测试端口 | 18080、18081、13306 | 仅任务进程占用，结束后释放 |

基础环境最初没有 PHP、Composer、MySQL、Nginx 和 Docker CLI。PHP/Composer、MySQL 8、Nginx、Compose 与 Gitleaks 只解压到 `/tmp`；PHP 和 Composer 下载物做了官方校验值验证。Docker engine 无法在当前权限边界内替代。完整环境证据见 [`artifacts/environment.log`](artifacts/environment.log) 和 [`artifacts/docker-environment.log`](artifacts/docker-environment.log)。

首次 `npm ci` 因默认 `/root/.npm` 不可写而失败；把缓存定向到 `/tmp/workmode-npm-cache` 后原命令成功。这是执行环境权限，不是项目缺陷。原始证据保留在 `artifacts/npm-ci-*-initial-failure.log`。

## 4. 项目架构概览

| 层 | 组成与职责 |
| --- | --- |
| 公开前台 | Vue 3 SPA、vue-router 5、Bootstrap 5；首页、品牌、搜索、详情、主题；输出 `public/frontend/` |
| 后台 | Laravel 13 + Blade/Vite；认证、个人资料、手机 CRUD/导入、轮播/推荐、用户与角色；输出 `public/build/` |
| API | Laravel `/api/*` 公开只读目录接口；`fields` 裁剪/别名、分页、搜索、限流；`/api/me` 返回同域会话状态 |
| 权限 | `user < editor < admin < owner`，路由中间件 + Policy + `OwnerGuard` 服务端强制 |
| 数据 | `products`、轮播、推荐、用户、cache/session/jobs；SQLite 开发/测试，MySQL 生产；MySQL ngram FULLTEXT 可选 |
| 上传 | `storage/app/public/homepage/`，GD 重编码、随机文件名；公开路径 `/storage/*` |
| Docker | `web` Nginx → `app` PHP-FPM:9000 → `db` MySQL 8；一次性 `migrate`；`db-data` 与 `uploads` 持久卷 |
| 测试 | PHPUnit feature/unit、Node test、ESLint/Prettier、Pint、Composer/npm audits、Gitleaks、CI Docker smoke |

生产 Web 根目录在 Nginx 和文档中均正确指向 `public/`。`.dockerignore` 排除 `.env*`、依赖、测试数据库、开发文件和本地构建输出；runtime 重新复制生产 vendor 与构建产物。PHP-FPM stage 以 `www-data` 运行，`storage` 和 `bootstrap/cache` 在镜像内赋予写权限。上传和数据库分别配置持久卷。

## 5. 实际执行的命令

以下为核心命令；密码和 APP_KEY 均在进程内随机生成，未写入日志。MySQL 命令中的凭据参数在此省略。

```bash
git clone https://github.com/GeneralPeople1970/smartphone-catalog
git switch -c workmode/deployment-audit
git status --short --branch
git rev-parse HEAD

composer validate --strict
composer install --no-interaction --prefer-dist --no-progress
composer audit
composer check-platform-reqs
vendor/bin/pint --test
git ls-files -z '*.php' | xargs -0 -n1 php -l
composer test

npm ci
npm --prefix frontend ci
npm audit --audit-level=high
npm --prefix frontend audit --audit-level=high
npm run check
npm run build

php artisan route:list --json
php artisan migrate --force
php artisan migrate:status
php artisan migrate:rollback --force
php artisan migrate --force
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan optimize:clear

mysqld --no-defaults --datadir=<task-temp> --port=13306 --socket=
mysql ... CREATE DATABASE <task-temp>; CREATE USER <task-temp> ...
CATALOG_SEARCH_DRIVER=fulltext vendor/bin/phpunit
mysql ... SHOW INDEX FROM products ...
mysql ... EXPLAIN SELECT ... MATCH(search_text) AGAINST(...)

php artisan serve --host=127.0.0.1 --port=18080
node /tmp/workmode-http-smoke.mjs

nginx -t -c /tmp/workmode-nginx.conf
nginx -c /tmp/workmode-nginx.conf -g 'daemon off;'
curl http://127.0.0.1:18081/storage/...

docker compose config
docker compose build
docker compose run --rm migrate
docker compose up -d
docker compose ps
docker compose logs --no-color

gitleaks detect --source . --redact --verbose
git diff --check
```

关键证据：[`php-final-matrix.log`](artifacts/php-final-matrix.log)、[`mysql-tests-post-fix.log`](artifacts/mysql-tests-post-fix.log)、[`frontend-final-matrix.log`](artifacts/frontend-final-matrix.log)、[`http-smoke-tests.log`](artifacts/http-smoke-tests.log)、[`nginx-storage-regression.log`](artifacts/nginx-storage-regression.log)、[`docker-build.log`](artifacts/docker-build.log)、[`docker-compose.log`](artifacts/docker-compose.log)、[`secret-scan.log`](artifacts/secret-scan.log)。

## 6. CI 复现结果

| CI job | 本地复现 | 结果 | 证据/备注 |
| --- | --- | --- | --- |
| `test` / PHP | validate、install、audit、platform、Pint、PHPUnit | PASS | 158/158，650 assertions |
| `test` / Node | 两次 `npm ci`、两次 audit、check、build | PASS | ESLint 0 warning；Prettier pass；11/11 tests |
| `mysql-test` | MySQL 8 空库迁移 + fulltext PHPUnit | PASS after fix | 基线 153 中 2 项失败；最终 158/158 |
| `docker` / config | Compose 2.40.3 解析四服务/两卷/网络 | PASS | 缺仓库 `.env` 的原始 config 失败符合文档；隔离临时环境解析成功 |
| `docker` / build/up | build → migrate → up → `/up` | Environment Blocked | daemon socket `operation not permitted` |
| `secret-scan` | Gitleaks 全历史 | PASS | 44 commits，0 leaks |

未触发远程 GitHub Actions；本表是对 workflow 实际命令的本地复现。所有 Actions 在仓库中均固定完整 SHA，顶层权限为 `contents: read`。

## 7. SQLite 测试结果

- 在全新临时 SQLite 文件上成功执行全部 18 个 migration。
- `migrate:status` 全部为 Ran；完整 rollback 成功；随后完整重新 migrate 成功。
- `config:cache`、`route:cache`、`view:cache` 均成功；`artisan about` 显示 `Environment=production`、`Debug Mode=OFF`。
- 完整 PHPUnit：**158 tests、650 assertions、0 failures**，2.572 秒。
- `CATALOG_SEARCH_DRIVER=fulltext` 在 SQLite 按设计降级到 LIKE；中英文、别名和短关键词定向测试通过。
- 空数据库、空页面集合、未知 slug/id、越界/负数分页、无效 cursor 和非法参数已有 feature/HTTP 覆盖。

证据：[`sqlite-migrations-cache-final.log`](artifacts/sqlite-migrations-cache-final.log)、[`php-final-matrix.log`](artifacts/php-final-matrix.log)、[`sqlite-search-regression.log`](artifacts/sqlite-search-regression.log)。

## 8. MySQL 8 测试结果

实际启动 MySQL `8.0.46-0ubuntu0.24.04.3`，绑定任务端口 13306，使用随机临时账号和独立空库；未使用 SQLite 代替。

- 全部 migration 在空 MySQL 库成功。
- 完整 rollback 和再次 migrate 成功。
- `products_search_text_fulltext` 为可见 `FULLTEXT` 索引，列为 `search_text`。
- `EXPLAIN` 显示 `type=fulltext`、`key=products_search_text_fulltext`。
- 已提交数据的搜索实测：`骁龙=1`、`iphone=1`、单字 `骁=1`（LIKE fallback）、不存在词 `=0`。
- 最终 `CATALOG_SEARCH_DRIVER=fulltext vendor/bin/phpunit`：**158 tests、650 assertions、0 failures**，12.701 秒。
- MySQL server 日志扫描无 ERROR/fatal/crash/corrupt。

基线 MySQL 套件曾有 2/153 失败：InnoDB FULLTEXT 看不到测试事务内未提交行。生产查询在提交数据上正常，失败来自测试隔离策略。改用 migration-based isolation 后 SQLite 与 MySQL 同一搜索断言均通过。

证据：[`mysql-tests.log`](artifacts/mysql-tests.log)（修复前）、[`mysql-fulltext-diagnostic.log`](artifacts/mysql-fulltext-diagnostic.log)、[`mysql-tests-final.log`](artifacts/mysql-tests-final.log)（回滚/重建）、[`mysql-tests-post-fix.log`](artifacts/mysql-tests-post-fix.log)（最终全套）。

## 9. 前端检查和构建结果

| 检查 | 结果 |
| --- | --- |
| 根 `npm ci` | PASS |
| `npm --prefix frontend ci` | PASS |
| 根 npm audit | 0 vulnerabilities |
| frontend npm audit | 0 vulnerabilities |
| 开源边界检查 | PASS |
| ESLint `--max-warnings=0` | PASS，0 warning |
| Prettier | PASS |
| Node tests | 11/11 PASS |
| 后台 Vite build | PASS，`public/build/` 120K |
| Vue SPA build | PASS，`public/frontend/` 820K |

最终 SPA 产物包含按需加载的 NotFound、Category、PhoneDetail、BrandPhoneList chunks。构建目录中无 `.env`、source map、APP_KEY/数据库密码、私钥标记、`/workspace`、`/root` 或用户 home 绝对路径。重复构建后只保留当前 manifest 引用的 chunks。

证据：[`frontend-final-matrix.log`](artifacts/frontend-final-matrix.log)、[`build-artifact-scan.log`](artifacts/build-artifact-scan.log)、[`frontend-404-regression.log`](artifacts/frontend-404-regression.log)、[`mixed-content-regression.log`](artifacts/mixed-content-regression.log)。

## 10. Docker 构建和部署结果

Compose 2.40.3 能解析配置。启用 `tools` profile 后服务为 `db/app/migrate/web`，卷为 `db-data/uploads`；镜像声明为 MySQL 8、应用 runtime 和 web。因为仓库按安全要求没有 `.env`，直接 `compose config` 报缺失 `.env`；使用只在 `/tmp` 的环境覆盖进行结构解析后退出码为 0。

`docker compose build` 实际到达 daemon 探测，但失败：

```text
permission denied while trying to connect to the Docker daemon socket ...
socket: operation not permitted
```

因此以下项目不能宣称通过：

- runtime/web 镜像真实构建和镜像大小
- one-shot migrate 容器
- `db/app/web` 健康状态与 Compose 日志
- `/up` 经 Nginx → PHP-FPM → MySQL 的容器链路
- 容器 restart 自动恢复
- 数据库和上传卷在 restart/rebuild 后持久化
- 镜像内 `.env`、test DB、node_modules、开发文件的实际 `docker image save` 检查
- 运行容器 UID/GID 与实际 root 进程检查

完成的可执行替代验证：Dockerfile/Compose/`.dockerignore` 静态边界检查；Laravel production-mode 部署；真实 MySQL；真实 Nginx location 解析/请求；上传 alias 与脚本阻止探针。Nginx 修复后合法上传图片 200、PHP 403、隐藏文件 403。

证据：[`docker-compose.log`](artifacts/docker-compose.log)、[`docker-build.log`](artifacts/docker-build.log)、[`docker-environment.log`](artifacts/docker-environment.log)、[`nginx-storage-initial-failure.log`](artifacts/nginx-storage-initial-failure.log)、[`nginx-storage-regression.log`](artifacts/nginx-storage-regression.log)。

## 11. 健康检查和日志分析

Laravel 在随机 APP_KEY、临时 SQLite、`APP_ENV=production`、`APP_DEBUG=false` 下启动。结果：

- `GET /up` → 200
- `GET /` → 200，返回 SPA shell 与当前哈希资源
- 当前 JS/CSS 资源 → 200
- Vue 深层路径 `/XIAOMI` → 200 SPA fallback
- 公开 API → JSON，状态/结构符合契约
- 87 项 HTTP smoke → 87 pass / 0 fail
- 公共 API 第 88 次累计请求触发 429，限流有效
- 应用 server log 扫描无 fatal、uncaught、SQLSTATE、permission denied、Laravel exception、production.ERROR 或 stack trace

Nginx 探针结果：上传文本 200、上传 SVG 200、上传 PHP 403、`/.env` 403、`/.git/config` 403、`/storage/.env` 403。初始合法上传 SVG 为 404，修复后为 200。

容器日志无法取得，因为 Docker stack 未启动；不能以本地日志替代该项。

证据：[`http-smoke-tests.log`](artifacts/http-smoke-tests.log)、[`http-server-final.log`](artifacts/http-server-final.log)、[`nginx-storage-regression.log`](artifacts/nginx-storage-regression.log)。

## 12. API 测试矩阵

实际路由清单保存为 [`artifacts/routes.json`](artifacts/routes.json)。所有公开 GET API 均有正常、边界或非法路径覆盖；主要矩阵如下。

| 路由 | 正常请求 | 边界/空数据 | 非法/安全输入 | 最终结果 |
| --- | --- | --- | --- | --- |
| `/up` | 200 | 重复请求 | production debug off | PASS |
| `/api/me` | 匿名、各角色会话 | 登出后 | 不泄露密码/token | PASS |
| `/api/brands` | 默认/fields/别名/计数 | 空计数 | invalid fields、限流 | PASS |
| `/api/homepage-slides` | active + 排序 + aliases | 空/禁用排除 | legacy unsafe image/link 净化 | PASS |
| `/api/homepage-featured-phones` | published/active/fields | 空/禁用/草稿排除 | unsafe image 净化 | PASS |
| `/api/phones` | 列表、品牌、排序、fields/aliases | page/cursor、末页空、短词、长名称、Unicode | page=0、负 limit、数组 q、坏 cursor、SQLi/XSS/特殊字符、超长 q | PASS |
| `/api/search` | 中文、英文、别名 | 单字 fallback、无结果 | 空 q/超长 q → 422 | PASS |
| `/api/brands/{brand}/search` | 品牌内关键词 | 无结果 | 特殊输入/未知范围 | PASS |
| `/api/phones/{id}` | published 数字 ID | 不存在/未发布 | 非数字不命中 API route | PASS |
| `/api/phones/detail` | slug、品牌过滤 | 大小写/分隔兼容、未知 slug | 缺失/数组/超长参数 | PASS |

共同断言包括：正确 `application/json`、200/404/405/422/429、统一验证错误结构、字段类型、分页响应头和 cursor meta、未发布数据不泄露、无 password/remember_token/APP_KEY/DB_PASSWORD/内部路径/SQL/stack trace。错误 HTTP 方法返回 405 JSON。API 同源设计未启用不必要的开放 CORS。

## 13. 浏览器功能测试结果

浏览器自动化工具实际尝试打开 `http://127.0.0.1:18080/` 和 `http://localhost:18080/`，两次均进入 `chrome-error://chromewebdata/`，错误 `net::ERR_BLOCKED_BY_CLIENT`。云浏览器不能路由到本次执行进程的 loopback；证据见 [`browser-environment.log`](artifacts/browser-environment.log)。

可执行替代覆盖：

- 首页 HTML、哈希资源、深层路由、API loading/empty/error 逻辑通过 HTTP、构建和源码检查。
- 404 catch-all 用 vue-router memory history 实测，未知深层路径解析为 `NotFound`。
- 主题 localStorage、前进/后退和图片 fallback 逻辑有代码/Node 测试覆盖。
- 页面具有图片 alt、表单 aria-label、alert role、404 `aria-labelledby` 和可键盘聚焦的原生控件。
- 登录、登出、注册、错误密码、四角色、后台 CRUD、直接越权请求用真实 HTTP + Cookie + CSRF 操作完成。

未能完成的纯浏览器/视觉项目：桌面与移动 viewport 截图、轮播动画、真实主题切换持久化、浏览器 history 交互、键盘逐项巡检、Console/Network 面板、响应式溢出、长文本视觉布局、真实图片 onerror 呈现。这些项目保持 Environment Blocked，不记作通过。

## 14. 权限和安全测试结果

| 领域 | 执行结果 |
| --- | --- |
| 认证/Session | 注册、登录、错误密码、登出、匿名跳转、HttpOnly、SameSite=Lax 通过；生产模板 `SESSION_SECURE_COOKIE=true` |
| CSRF | 登录和后台新增缺 token 均 419；合法 token 请求成功 |
| RBAC/IDOR | user 无后台；editor 管产品但无用户管理；admin/owner 用户管理；直接构造写请求仍 403 |
| Mass Assignment | 注册伪造 `role=owner/status=suspended` 被忽略；普通 user 仍无后台权限 |
| 最后 owner | 降级/停用/删除和相关并发不变量由测试覆盖 |
| SQL 注入/XSS | SQLi、script、特殊字符输入无 500/SQL/stack；JSON 安全返回；Vue 文本插值转义 |
| URL 安全 | `javascript:`、`data:text`、`vbscript:`、protocol-relative、backslash/control chars、外站图片被拒；legacy API 输出净化 |
| Mixed Content | HTTPS 页面上的同主机 HTTP 图片现在服务端和前端均回退占位图；HTTP 本地开发仍允许 |
| 上传 | PHP 伪装、MIME/扩展不匹配、非图片、超尺寸、重复名测试通过；服务端 MIME + GD 重编码 + 随机名；SVG 不在允许列表 |
| 上传执行 | Nginx `/storage/*.php` 实测 403；合法上传图片 200；`nosniff` 和缓存头存在 |
| 导入 | 非数组根、超长字段、超过 20 文件、单文件/批次超过 2000 记录被拒；JSON 深度/大小限制经代码与套件覆盖 |
| SSRF | 应用不抓取远程图片/链接；URL 字段只作为输出，故无服务器端请求链路；危险协议被拒 |
| 敏感路径 | Laravel server 未返回 `.env`/`.git`/vendor/log 内容；Nginx 对隐藏文件实测 403 |
| 安全头 | nosniff、SAMEORIGIN、Referrer-Policy、Permissions-Policy、CSP `default-src self/object-src none/frame-ancestors self` 通过 |
| 依赖/秘密 | Composer/npm audits 0；Gitleaks 44 commits 0 leaks；构建产物秘密扫描 0 |

补充说明：Laravel built-in server 的 `/.env`、`/.git/config`、`/vendor/...` 中部分路径会被 SPA catch-all 以 200 shell 响应，但断言确认没有返回对应敏感文件内容；Docker Nginx 隐藏文件规则则直接 403。批量导入为 JSON，不生成或执行电子表格公式，因此 CSV/XLS 公式注入不是当前入口。轮播图片经 GD 重编码会移除 EXIF/附加 payload；未发送接近 20MB 的压力载荷，以遵守“不做拒绝服务”边界。

CSP 保留 `'unsafe-inline'`/`'unsafe-eval'` 是文档明确的 Alpine/内联引导兼容权衡，不误报为缺陷。HSTS 只在 HTTPS/可信代理识别正确时由应用添加；当前无 TLS 终止层，未做真实 HSTS 验证。

## 15. 发现的缺陷

| ID | 严重程度 | 模块 | 问题 | 复现步骤 | 根因 | 修复 | 回归测试 | 状态 |
| --- | --- | --- | --- | --- | --- | --- | --- | --- |
| WM-001 | Medium | MySQL/CI | MySQL fulltext job 153 项中 2 项失败 | MySQL 8 + `CATALOG_SEARCH_DRIVER=fulltext vendor/bin/phpunit` | `RefreshDatabase` 事务内新行对 InnoDB FULLTEXT 不可见 | 两个搜索类改用 `DatabaseMigrations`，保持提交级隔离 | SQLite/MySQL 同一 158 项全套；中英/短词诊断 | Fixed |
| WM-002 | Medium | Public API/Security | 手机、推荐和 legacy 轮播记录可返回未经净化的图片/official/link URL | 直接建 unsafe 记录后 GET 相关 API | 写入校验存在，但公共序列化绕过了安全 accessor/净化器 | API 统一使用 `safe_image_url`/`Product::safeImageUrl` 和 `SafeUrl::sanitize` | `ProductImageUrlSafetyTest`、`UrlSafetyTest`、HTTP smoke | Fixed |
| WM-003 | Medium | Auth/Rate limit | API 流量会消耗注册 5/min 配额，合法注册被 429 | 同 IP 请求 5 次 API 后 POST `/register` | Laravel throttle 默认 key 未区分路由组 | API prefix=`api`，注册 prefix=`registration` | `test_api_traffic_does_not_consume_the_registration_rate_limit` | Fixed |
| WM-004 | High | Docker/Nginx | Docker 部署中所有 `/storage/*.png/svg/...` 被通用图片 regex 抢先，alias 上传图片 404 | Nginx：storage txt 200、image 404、PHP 403 | regex location 优先于 `/storage/` prefix alias，并按 `public/storage` 查找 | 通用静态 regex 明确排除 `/storage/`，脚本 deny 仍优先 | 实际 Nginx image 200/PHP 403；`DockerNginxConfigTest` | Fixed |
| WM-005 | Low | Vue Router | 未知单段路径返回 SPA shell 但 router-view 为空 | 检查路由无 catch-all；打开未知单段 URL | 路由表没有 404 route/view | 增加懒加载 catch-all 和可访问 404 页面 | `frontend/tests/router.test.mjs` + production build | Fixed |
| WM-006 | Medium | Frontend/API | HTTPS 站点仍输出同主机 `http://` 图片，浏览器 Mixed Content 阻止加载 | `APP_URL=https://catalog.test` 下净化 `http://catalog.test/x.png` | host allowlist 未比较页面与资源协议 | HTTPS 下拒绝降级 HTTP，HTTP 开发不变；前后端双层 | PHP mixed-content test + Node image test | Fixed |

严重程度统计：Critical 0、High 1、Medium 4、Low 1。确认缺陷 6，已修复 6，未修复确认缺陷 0。

## 16. 已实施的修改

| 文件 | 修改 |
| --- | --- |
| `app/Http/Controllers/Api/PhoneController.php` | 手机图片和 official URL 输出净化 |
| `app/Http/Controllers/Api/HomepageFeaturedPhoneController.php` | 推荐手机图片使用安全 accessor |
| `app/Http/Controllers/Api/HomepageSlideController.php` | legacy 轮播图片/链接在 API 边界净化 |
| `app/Models/Product.php` | HTTPS 页面拒绝降级 HTTP 图片 |
| `routes/api.php` | API throttle key prefix |
| `routes/auth.php` | 注册 throttle key prefix |
| `docker/nginx/default.conf` | 静态图片 regex 排除 `/storage/` alias |
| `frontend/src/utils/image.js` | 前端拒绝 Mixed Content 图片 |
| `frontend/src/router/index.js` | 注册 catch-all route |
| `frontend/src/router/notFoundRoute.js` | 可独立测试的 404 route record |
| `frontend/src/views/NotFound.vue` | 新增可访问 404 页面 |
| `frontend/package.json` | Node test 执行所有 `*.test.mjs` |
| `tests/Feature/CatalogOptimizationTest.php` | MySQL FULLTEXT 兼容隔离 |
| `tests/Feature/SearchDriverTest.php` | MySQL FULLTEXT 兼容隔离与准确命名 |
| `tests/Feature/ProductImageUrlSafetyTest.php` | API URL 与 Mixed Content 回归 |
| `tests/Feature/UrlSafetyTest.php` | legacy 轮播 API 输出回归 |
| `tests/Feature/Auth/RegistrationTest.php` | 独立限流回归 |
| `tests/Unit/DockerNginxConfigTest.php` | Docker Nginx 配置守护 |
| `frontend/tests/image-url-safety.test.mjs` | Mixed Content 前端回归 |
| `frontend/tests/router.test.mjs` | 404 路由回归 |

没有依赖升级、无无关重构、无降低断言、无吞异常、无 `|| true` 掩盖项目命令。未创建本地 commit。

## 17. 新增或修改的测试

- 新增：API 不暴露不安全图片/official URL。
- 新增：legacy 轮播 API 对图片和 link URL 做输出净化。
- 新增：HTTPS Mixed Content 服务端拒绝，HTTP 开发兼容。
- 新增：API 流量不消耗注册配额。
- 新增：Docker Nginx `/storage/` 不被静态正则遮蔽。
- 新增：Vue Router 未知路径命中 NotFound。
- 新增：前端 Mixed Content 图片拒绝。
- 修改：两个搜索测试类使用适合 InnoDB FULLTEXT 的提交级数据库隔离。
- 修改：前端 test script 从单文件扩展到所有 `tests/*.test.mjs`。

定向回归证据：[`api-url-safety-regression.log`](artifacts/api-url-safety-regression.log)、[`rate-limit-regression.log`](artifacts/rate-limit-regression.log)、[`mysql-search-regression.log`](artifacts/mysql-search-regression.log)、[`nginx-config-regression.log`](artifacts/nginx-config-regression.log)、[`frontend-404-regression.log`](artifacts/frontend-404-regression.log)、[`mixed-content-regression.log`](artifacts/mixed-content-regression.log)。

## 18. 完整回归测试结果

| 矩阵 | 通过 | 失败 | 备注 |
| --- | ---: | ---: | --- |
| PHPUnit / SQLite | 158 | 0 | 650 assertions |
| PHPUnit / MySQL 8 FULLTEXT | 158 | 0 | 650 assertions |
| Frontend Node | 11 | 0 | URL/图片/404 route |
| Production HTTP smoke | 87 | 0 | 真实 HTTP、Cookie、CSRF、RBAC、CRUD、API、429 |
| **主矩阵合计** | **414** | **0** | 重复 DB 执行按实际 job 计数 |
| Nginx 运行时探针 | 6 | 0 | 200/403 结果符合预期；不计入上方主矩阵 |
| 定向安全 PHPUnit（主套件子集） | 83 | 0 | 321 assertions；不重复计入合计 |

其他最终检查：

- 18 migrations：SQLite/MySQL 空库执行成功；两库关键 rollback/re-migrate 成功。
- Composer validate/audit/platform：通过，0 advisories。
- Pint：通过。
- PHP syntax：全部项目 PHP 文件通过。
- npm audits：根/前端均 0 vulnerabilities。
- ESLint：0 warnings；Prettier：通过。
- Vite admin/SPA production build：通过。
- build artifact secret/path scan：通过。
- Gitleaks：44 commits，0 leaks。
- `git diff --check`：通过。

## 19. 未解决问题和剩余风险

| 项目 | 影响 | 状态 | 原因/下一验证 |
| --- | --- | --- | --- |
| Docker image build / Compose up | 无法证明完整发布拓扑、镜像大小和健康状态 | Environment Blocked | 当前 daemon socket 权限被沙箱拒绝；在有 Docker 的隔离 runner 重跑 CI docker job |
| DB/upload volume restart/rebuild persistence | 发布硬条件未实证 | Environment Blocked | 需要真实 Compose volume、restart 和 rebuild |
| 实际容器 UID/镜像内容 | 静态配置正确但未运行时确认 | Environment Blocked | `docker inspect/exec/history/image save` |
| 桌面/移动浏览器视觉与交互 | 轮播、主题、history、console、overflow、键盘未实机验证 | Environment Blocked | 云浏览器不能访问 loopback；用可路由 preview/Playwright 重跑 |
| TLS/HSTS/反向代理 | 本地 HTTP 无法验证外层 TLS 和 trusted proxy | Not Fixed | 在 staging HTTPS/负载均衡器验证 |
| CSP `'unsafe-inline'/'unsafe-eval'` | XSS 防线弱于 nonce CSP | Confirmed documented tradeoff | 兼容 Alpine/引导脚本；后续改 nonce + Alpine CSP build |
| 品牌 Logo 再分发权 | 生产法律/资产风险 | Confirmed documented risk | README 已明确，发布方应替换或确认许可 |

最后两项是仓库已明确说明的权衡/风险，不计入本次 6 个代码缺陷。没有未解决的 Critical 或 High 代码缺陷。

## 20. 生产部署就绪结论

| 发布条件 | 结果 |
| --- | --- |
| 后端测试全部通过 | 是 |
| 前端 lint/format/test 全部通过 | 是 |
| 前后台生产构建成功 | 是 |
| SQLite/MySQL 关键测试通过 | 是 |
| 空库全量 migration | 是 |
| Docker 镜像构建 | **未满足：Environment Blocked** |
| Docker Compose 正常启动 | **未满足：Environment Blocked** |
| `/up` | 本地 production-mode 200；Docker 链路未验证 |
| 首页和关键 API | 是，87/87 HTTP |
| 未解决 Critical/High | 是，无未解决项 |
| production debug off | 是，本地实测 + 模板检查 |
| 敏感文件不可通过 Web 读取 | 本地内容检查 + Nginx 403 探针通过；Docker 镜像未实证 |
| 数据库/上传持久化 | **未满足：Docker volume 未验证** |
| 修复均有回归测试 | 是 |
| 修复后完整回归 | 是 |

结论：**Conditionally Ready**。

当前工作树在代码和可运行测试层面通过，且没有未解决 Critical/High 缺陷；但 Docker build/up、容器持久化和真实浏览器巡检是用户定义的硬性完成标准，当前环境无法证明，故不能标记 Ready。部署前还必须把本报告中的未提交修复纳入待发布 commit，并在有 Docker 和可路由浏览器的 staging/CI 环境完成阻塞项。

## 21. 建议的后续行动

1. 在有权限的隔离 Docker runner 上按仓库流程执行 `compose config → build → key → migrate → up`，保存 `ps/logs`，并验证 `/up`、首页和 API。
2. 使用唯一 Compose project name 和随机临时凭据，创建专用 db/upload volumes；写入标记数据和上传文件，依次执行 restart、app/web rebuild，再确认数据仍在。只删除该测试 project 的 volume。
3. 执行镜像审计：大小、layer、`.env`/test DB/node_modules/tests/docs、容器用户、PHP-FPM/Nginx worker、上传目录脚本执行；记录 `docker inspect/history`。
4. 把 `artifacts/nginx-storage-regression.log` 的图片 200 / script 403 探针加入 Docker CI，补足静态配置测试之外的端到端守护。
5. 在可访问 staging URL 的 Playwright/浏览器上跑桌面与移动视口：首页轮播、主题持久化、前进后退、404、错误/空状态、图片回退、长中英文本、键盘与基本无障碍、Console/Network。
6. 评估 nonce CSP 与 Alpine CSP build，移除 `'unsafe-inline'/'unsafe-eval'`；在 TLS 终止层验证 HSTS 与 trusted proxy。
7. 审阅当前 diff 后创建正式 commit/PR；本次审计按约束未 commit、未 push、未修改远程仓库。
