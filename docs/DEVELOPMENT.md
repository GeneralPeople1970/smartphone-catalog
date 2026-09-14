# 开发手册

**简体中文** · [English](DEVELOPMENT.en.md) · [项目首页](../README.md) · [API 文档](api.md) · [部署指南](DEPLOYMENT.md)

本文说明本地开发、业务约定和验证方式。首次运行从 [快速开始](../README.md#快速开始) 开始；生产配置与运维见部署指南。

[本地开发](#本地开发) · [项目结构](#项目结构) · [核心规则](#核心规则) · [测试与检查](#测试与检查) · [参与贡献](#参与贡献)

## 本地开发

| 依赖           | 要求                                                                    |
| -------------- | ----------------------------------------------------------------------- |
| PHP / Composer | PHP 8.5、Composer 2；Laravel 必需扩展、`fileinfo`、`gd` 及对应 PDO 驱动 |
| Node.js / npm  | Node.js 24.x（≥ 24.11.0）、npm 11                                       |
| 数据库         | SQLite（默认）或 MySQL；CI 使用 SQLite 与 MySQL 8                       |

完成 README 中的安装和账号初始化后，在项目根目录安装前端开发依赖：

```sh
npm ci
npm --prefix frontend ci
```

### 开发与构建

需要热更新时，在独立终端中运行相应服务：

| 命令                   | 服务                                                  |
| ---------------------- | ----------------------------------------------------- |
| `php artisan serve`    | Laravel、API 与管理后台，默认 `http://127.0.0.1:8000` |
| `npm run dev:admin`    | Blade 后台的 Vite 热更新                              |
| `npm run dev:frontend` | Vue 前台的独立 Vite 服务                              |

前台独立开发时，在 `frontend/.env.local` 设置 API 代理，然后访问 Vite 输出的前台地址：

```dotenv
VITE_API_PROXY_TARGET=http://127.0.0.1:8000
```

登录、注册和后台页面使用 Laravel 地址。Laravel 首页读取已构建的 `public/frontend/index.html`；Vue 源码的热更新在独立 Vite 服务中查看。代理仅转发 `/api`，不包含 `/assets`、`/storage` 或认证路由，完整图片与认证流程应在构建后通过 Laravel 同源地址验证。

`composer run dev` 合并启动 Laravel、队列监听、Pail 日志和后台 Vite。Pail 需要 `pcntl`，原生 Windows 可使用上面的独立服务命令；Vue Vite 仍需单独启动。

```sh
npm run build
```

构建输出为 `public/build/`（后台）和 `public/frontend/`（前台）。两者随源码提交；修改资源或共享品牌 JSON 后，应重新构建。`build:admin`、`build:frontend` 可分别构建对应部分。

CI 重新构建后运行 `npm run check:build-sync`，检查两套产物是否与已提交版本一致。该命令本身不构建，本地应在提交源码与产物后运行。文件使用 LF 换行，遵循 [`.gitattributes`](../.gitattributes)。

## 项目结构

| 路径                         | 职责                                       |
| ---------------------------- | ------------------------------------------ |
| `app/Http/`、`app/Policies/` | 请求、认证、授权与响应                     |
| `app/Services/`              | 目录写入、导入、首页排序、验证码与账号变更 |
| `app/Support/`               | 品牌定义、查询、API 字段与 URL 规则        |
| `resources/`                 | Blade、后台资源及共享品牌 JSON             |
| `frontend/src/`              | Vue 页面、组件、路由与展示工具             |
| `routes/`                    | Web、API 和认证路由                        |
| `database/`                  | 迁移、工厂与本地测试种子                   |
| `tests/`                     | PHPUnit、真实并发与 Playwright 测试        |
| `docker/`                    | Nginx、PHP 和容器启动配置                  |

`/api/*` 提供公开只读接口；`/dashboard`、`/profile` 使用会话认证；`/admin/*` 额外按角色授权。其他公开页面交给 Vue 路由。生产 Web 根目录为 `public/`。

## 核心规则

### 品牌与数据规则

[brands.json](../resources/data/brands.json) 是品牌名称、代码、显示名、别名、Logo 与旧路由的共同来源。新增或调整品牌时，同时验证前台路由、后台选项和 API 输出。

保存的 `brand` 决定归属，`source_file` 只用于导入推断与溯源。例如 Apple 来源的记录改为 Xiaomi 后，列表、详情、推荐和品牌计数均按 Xiaomi 返回。未知的已保存品牌也不会被来源文件重新归类。

历史数据先执行只读检查，再按需应用可确认的别名修正：

```sh
php artisan catalog:normalize-brands
php artisan catalog:normalize-brands --apply
```

`--apply` 会锁定并复核记录，只规范与已识别来源不冲突的别名。未知品牌和归属冲突留待人工核查，保留来源信息。

### 手机写入与导入

| 入口                            | 共享实现                                           |
| ------------------------------- | -------------------------------------------------- |
| 新建、编辑与导入校验            | [ProductData](../app/Services/ProductData.php)     |
| 事务保存、唯一 slug、主字段同步 | [ProductWriter](../app/Services/ProductWriter.php) |
| 文件解析、批次校验与整批事务    | [ProductImport](../app/Services/ProductImport.php) |

后台“完整参数”的 JSON 根节点必须是对象；留空表示空对象。`true`、`42`、`null`、字符串和数组根节点均无效。合法扩展字段保留，嵌套 `{}` 与 `[]` 保持区别；编辑时省略参数字段会保留已有扩展，主表字段始终覆盖对应参数。

导入文件则使用对象数组。以下为格式示例，`id` 需替换为未占用的正整数，发布状态由导入表单选择：

```json
[
    {
        "id": 10001,
        "company": "Xiaomi",
        "phonename": "Example Phone",
        "socname": "Example SoC",
        "price": "3999 起",
        "battery": "5000 mAh",
        "saledate": 20260901
    }
]
```

| 字段或限制         | 规则                                                                         |
| ------------------ | ---------------------------------------------------------------------------- |
| 品牌、名称、处理器 | 文本，最长 191 字符；`name`、`image`、`processor` 等导入别名使用对应字段规则 |
| 图片地址 / 价格    | 最长 2048 / 100 字符；价格接受数字或文本                                     |
| `id`               | 导入必填正整数，沿用为记录 ID；已有或批内重复 ID 会报错                      |
| `saledate`         | `0..99991231` 的整数或整数字符串；空值与 `0` 表示未知日期                    |
| 电池容量           | `0..30000` 的整数；导入兼容 `5000 mAh`，`0` 表示未知容量                     |
| 文件               | 最多 20 个 JSON；单文件 ≤ 2 MiB，合计 ≤ 10 MiB；文件名 ≤ 191 字符            |
| 记录与嵌套         | 整批 ≤ 2000 条；JSON 深度上限 32，扩展字符串 ≤ 5000 字符                     |

导入品牌缺失或无法识别时可从文件名推断，已识别的显式品牌优先。重复来源、非法记录或保存失败会取消整批写入；错误标明文件、记录序号与字段。

价格在保存和输出时保留文本语义。`3999 起`、会溢出或损失精度的数字文本不能强制转为普通数字；列表、详情与推荐共用格式化规则。`official` 链接经过 `SafeUrl` 净化。

### 派生列与搜索

`Product` 保存时生成 `release_date`、`search_text`、`slug_key`，分别用于日期排序、统一搜索和详情直查。新增可搜索或排序字段时，更新对应派生方法及测试；历史回填使用新迁移或专用命令。

<a id="搜索与性能"></a>

搜索统一调用 `Product::scopeSearch()`。默认 `CATALOG_SEARCH_DRIVER=like` 对 `search_text` 做包含匹配；`fulltext` 使用 MySQL ngram FULLTEXT 索引，需先完成迁移。非 MySQL 连接或短于 2 字符的关键词回退 LIKE。两种驱动共用品牌和芯片别名扩展，但匹配结果不保证完全一致。

性能调整以实际数据和执行计划为依据；包含匹配、品牌归一表达式和排序条件都会影响索引使用。

### 列表分页与详情直查

[PhoneQuery](../app/Support/PhoneQuery.php) 固定按“有日期优先、日期倒序、名称升序、ID 升序”在数据库排序后分页，`null` 与 `0` 日期排后。分页大小不改变同一结果集的顺序。

游标分页使用 Laravel `cursorPaginate`，以查询别名归一空日期，由 `ListCursor` 适配旧编码；页码和游标模式都保留总数查询。详情按规范化 `slug_key` 直接查询，重复键取最小 ID。响应头、元数据、字段别名与旧拼写属于接口契约，见 [API 文档](api.md)。

[PhoneFields](../app/Support/PhoneFields.php) 统一手机字段映射。增加公开字段时，同时检查各接口白名单、默认字段和请求别名。

### 布局与导航规则

- Vue 前台使用 Bootstrap，Blade 后台使用 Tailwind 与现有公共样式；共用 [shared-navigation.css](../resources/css/shared-navigation.css) 的导航尺寸、容器宽度与主题变量。
- 后台使用顶部导航；页面内容、页头与表单使用对应的公共容器类。主题跟随 `prefers-color-scheme`，不另存手动主题状态。
- 机型卡片使用 `PhoneCard` 的真实链接；图片回退使用 `PhoneImage`，价格与电池格式化使用 `frontend/src/utils/phone.js`。
- 品牌列表和品牌内搜索各自管理请求状态，每次加载 24 条。切换条件时重置对应游标并取消旧请求；后台推荐选择器每次最多 20 条并保留已选项。
- 共用 `resources/js/http.js` 与 `latest-request.js` 处理 HTTP 状态、取消和过期响应。首页区块独立失败；详情区分不存在、限流和网络失败。离页时释放请求、计时器与轮播实例。

### 后台表单与控件规则

复用 `resources/css/app.css` 的控件变量和 `admin-*` 类，避免页面级固定尺寸或颜色覆盖。页头与表单使用相同宽度容器，字段保留 label、说明和错误区域。

`admin-feedback` 展示提交结果；多记录表单通过表单标识隔离旧值和校验错误。复选框使用统一组件与 `$request->boolean('is_active')`，未勾选保存为关闭。

轮播与热门推荐共用 [HomepageOrder](../app/Services/HomepageOrder.php)，在事务中读取、锁定并更新顺序。排序规则集中在该服务，避免在控制器中重复实现。

### 上传与存储

轮播上传使用 `public` 磁盘，保存到 `storage/app/public/homepage/`，经 `/storage/homepage/` 访问。服务端依据实际 MIME 校验内容，限制单边 4000 像素、总计 1000 万像素，再由 GD 重编码并生成安全扩展名。修改存储方案时需同时调整服务端写入与公开访问配置。

`ImageUrl` 统一图片地址，`SafeUrl` 处理可点击链接。仅接受允许的站内路径与 HTTP(S) 地址；图片拒绝 HTTPS 页面上的 HTTP 降级。手机图失败回退 `/assets/logo.png`，品牌图失败隐藏或展示品牌名。

[ManagedImage](../app/Services/ManagedImage.php) 仅删除应用管理且确认无引用的首页图片，包括对同源绝对地址的引用检查。外链不下载、不删除。旧轮播图片可迁移：

```sh
php artisan homepage-slides:migrate-storage
```

可选 `--delete-source` 仅在复制、校验和数据库引用更新成功后删除旧文件。

### 权限系统

| 角色     | 能力                                                                |
| -------- | ------------------------------------------------------------------- |
| `user`   | 只读控制台与自身资料管理                                            |
| `editor` | 增加机型、导入、首页推荐与轮播管理                                  |
| `admin`  | 增加站点设置与用户管理；只可调整 user/editor 的角色、状态和验证状态 |
| `owner`  | 管理其他高权限账号，受自我保护与最后所有者规则约束                  |

角色为 `user/editor/admin/owner`，状态为 `active/suspended`。服务端中间件和 Policy 执行授权；菜单可见性只反映权限。被停用的账号无法登录，已有会话在下一次受保护请求时失效。

[OwnerGuard](../app/Services/OwnerGuard.php) 在事务中锁定最新账号与有效 owner 集合，已有 active owner 时变更后不得降为零；允许从零初始化所有者。角色、状态和人工验证状态修改在锁后复查授权；本人删号在锁定账号后检查最后 owner 约束。资料修改锁定最新邮箱，新地址不能继承旧地址的验证结果。

### 用户管理与初始化 owner

先注册账号，再提升权限；系统不会自动创建 owner：

```sh
php artisan user:promote owner@example.com --role=owner
```

命令要求账号已存在，`--role` 接受四种角色。非交互环境可加 `--force`，它只跳过确认，仍受最后 owner 保护。Web 页面禁止修改自己的角色或停用自己；验证状态不能由用户自己修改，也不能修改 owner 的验证状态。

### 站点设置与邮箱验证

`/admin/settings` 的 `registration_email_verification` 默认关闭。关闭时新注册账号直接标记为已验证并登录；开启时，未验证的非 owner 账号先完成游客验证码流程，再建立登录会话。开关不批量改写已有账号的验证状态，owner 始终豁免强制验证。

| 验证规则 | 值                                |
| -------- | --------------------------------- |
| 验证码   | 6 位数字，仅存哈希并绑定邮箱      |
| 有效期   | 10 分钟                           |
| 错误尝试 | 每个验证码最多 5 次，达到上限作废 |
| 重发间隔 | 同一账号 60 秒，发送成功后计数    |

[EmailVerification](../app/Services/EmailVerification.php) 按账号共享原子锁，统一签发、错误计数与消费；消费成功只发生一次。错误尝试保持原到期时间，重发失败保留旧验证码，锁等待超时返回可重试失败。

生产缓存必须支持跨进程共享数据和原子锁，默认使用数据库的 `cache` 与 `cache_locks` 表。开启验证前配置真实 SMTP；`log`、`array`、`null` 邮件驱动不会投递。验证码邮件同步发送，发送失败保留账号并允许重试。密码重置继续使用 Laravel Password broker。

## 测试与检查

| 命令                         | 覆盖范围                             |
| ---------------------------- | ------------------------------------ |
| `composer test`              | 清除配置缓存后运行 PHPUnit           |
| `php vendor/bin/pint --test` | PHP 格式                             |
| `npm run check`              | 开源边界、ESLint、Prettier 与 Vitest |
| `npm run build`              | 两套生产构建                         |
| `npm run test:browser`       | 浏览器交互与布局                     |
| `npm run check:build-sync`   | 已提交产物是否与构建结果同步         |

PHPUnit 默认使用 SQLite 内存库，但外部环境变量和配置缓存可改变数据库连接。全套测试包含迁移与数据清理，**只使用专用测试库**。直接运行 `php vendor/bin/phpunit` 前先清除配置缓存。

目录测试复用 `Product::factory()`，草稿使用 `draft()`，并覆盖同日、空日期、重复名称、文本价格、异常导入与历史品牌。真实并发测试启动独立 PHP 进程，共享数据库缓存与锁；默认使用独立磁盘 SQLite，MySQL 使用随机前缀表。

### MySQL 与并发验证

先创建专用测试数据库及账号，在新的终端替换下面的连接信息。将 `DB_URL` 设为 Laravel 识别的空值字面量 `(null)`，避免 `.env` 中的 URL 覆盖分项连接配置：

```sh
php artisan config:clear
DB_CONNECTION=mysql DB_HOST=127.0.0.1 DB_PORT=3306 \
DB_DATABASE=catalog_test DB_USERNAME=catalog_test DB_PASSWORD='your-test-password' \
DB_URL='(null)' CONCURRENCY_DB_CONNECTION=mysql CATALOG_SEARCH_DRIVER=fulltext \
php vendor/bin/phpunit
```

<details>
<summary>PowerShell</summary>

```powershell
php artisan config:clear
$env:DB_CONNECTION = 'mysql'
$env:DB_HOST = '127.0.0.1'
$env:DB_PORT = '3306'
$env:DB_DATABASE = 'catalog_test'
$env:DB_USERNAME = 'catalog_test'
$env:DB_PASSWORD = 'your-test-password'
$env:DB_URL = '(null)'
$env:CONCURRENCY_DB_CONNECTION = 'mysql'
$env:CATALOG_SEARCH_DRIVER = 'fulltext'
php vendor/bin/phpunit
```

</details>

`CONCURRENCY_DB_CONNECTION=mysql` 必须显式设置，否则真实并发套件仍使用 SQLite。并发用例的表隔离不代表整个测试集可以连接业务库。Docker 入口脚本测试需要 POSIX `sh`，缺少时跳过，由 Linux CI 验证。

### 浏览器回归

安装依赖并构建资源后运行：

```sh
npx playwright install chromium
npm run test:browser
```

[Playwright 配置](../playwright.config.mjs) 自动创建临时 SQLite 和测试数据，启动专用 PHP 服务，默认端口 `8765`。用例覆盖搜索、翻页、品牌切换、详情返回、错误区块、轮播生命周期、移动布局及多表单回填；失败截图与 trace 保存到临时目录的 `results/`。

| 可选环境变量                     | 用途                                 |
| -------------------------------- | ------------------------------------ |
| `PHP_BINARY`                     | PHP CLI 的路径                       |
| `PLAYWRIGHT_CHROMIUM_EXECUTABLE` | 已安装的 Chrome / Chromium 路径      |
| `BROWSER_TEST_PORT`              | 未占用的本地端口                     |
| `BROWSER_TEST_RUNTIME`           | 专用临时目录的绝对路径；默认自动创建 |

## 参与贡献

- 按变更运行相关检查，业务行为修改应补回归测试，并同步维护中英文文档。
- 使用 Composer/npm 正常解析依赖，提交对应的 `composer.lock`、`package-lock.json`、`frontend/package-lock.json`，避免强制绕过上游版本或平台约束。
- 修改数据结构时新增迁移；保留历史迁移、旧路由、字段拼写与接口兼容。
- `.env`、数据库、上传数据、私有机型资料、凭据和本地审计输出不纳入版本库。`npm run check:open-source` 检查已跟踪文件的分发边界。

依赖更新时补充检查：

```sh
composer validate --strict
composer check-platform-reqs
composer audit
npm audit --audit-level=high
npm --prefix frontend audit --audit-level=high
```

[CI](../.github/workflows/ci.yml) 验证 PHP、前端、构建同步、浏览器、MySQL、Docker 启动与秘密扫描。GitHub Actions 固定到提交 SHA，由 Dependabot 管理更新。

<a id="容器化部署docker"></a>
<a id="nginx-示例"></a>
<a id="关于-csp"></a>

## 部署

Docker 首次部署、手动发布、Nginx 示例、CSP、备份与更新统一见 [部署指南](DEPLOYMENT.md)。
