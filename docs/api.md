# API 手册

## 基础约定

- 基础路径：`/api`
- 前端不要写死域名；同域部署直接请求 `/api/*`
- 跨域本地开发时，由前端开发服务代理到 Laravel
- `GET /api/me` 依赖登录态；同域名部署会自动携带 Cookie

## 已废弃接口

- `GET /api/home/featured-phones` 已移除，改用 `GET /api/homepage-featured-phones`
- `GET /api/site-theme` 已移除，主题改为读取 `localStorage.smartphone_catalog_theme`

## 认证状态

### `GET /api/me`

用途：判断当前浏览器是否已登录后台账号。

核心字段：

- `authenticated`
- `user.id`
- `user.name`
- `user.email`

未登录时返回 `authenticated=false`、`user=null`。

## 契约规则

### 品牌展示

- 品牌接口返回 `name`、`code`、`displayName`
- 手机接口返回 `company`、`companyCode`
- 品牌过滤兼容中文名、英文名、品牌代码和旧别名
- `LENOVO_XIAOXIN`、`LIANXIANG` 仍兼容到 Lenovo

### 字段裁剪

- `GET /api/brands`
- `GET /api/homepage-slides`
- `GET /api/homepage-featured-phones`
- `GET /api/phones`
- `GET /api/phones/{id}`
- `GET /api/phones/detail`

以上接口都支持 `fields` 参数；传入不支持字段时返回 `422`。

### 手机字段别名

以下 `fields` 请求别名在手机类接口间通用（`/api/phones`、`/api/phones/{id}`、`/api/phones/detail`、`/api/search`、`/api/brands/{brand}/search`、`/api/homepage-featured-phones`）：

- `name`、`model`、`phoneName` -> `phonename`
- `brand` -> `company`
- `brandCode` -> `companyCode`
- `processor`、`soc` -> `socname`
- `image`、`imageUrl` -> `imgurl`

各接口的专属别名见下方对应小节。

### 错误格式

- 参数缺失、字段非法或 `limit < 1` 时返回 `422`
- 资源不存在或未发布时返回 `404`

## 接口清单

### `GET /api/brands`

用途：返回品牌目录。

参数：

- `fields`

核心字段：

- `name`
- `code`
- `displayName`
- `logo`
- `path`
- `sort`
- `phoneCount`

### `GET /api/homepage-slides`

用途：返回已启用的首页轮播图，按 `sort_order` 升序。

参数：

- `fields`

核心字段：

- `id`
- `title`
- `image`
- `linkUrl`
- `sortOrder`

兼容字段别名：

- `image_path`、`imagePath`、`imgurl`、`url` -> `image`
- `link_url`、`link` -> `linkUrl`
- `sort`、`sort_order` -> `sortOrder`

### `GET /api/homepage-featured-phones`

用途：返回首页热门机型，仅包含已启用且产品已发布的数据。

参数：

- `fields`

默认字段：

- `id`
- `phonename`
- `company`
- `companyCode`
- `socname`
- `price`
- `displayPrice`
- `battery`
- `imgurl`
- `feature`
- `slug`
- `recommendTitle`
- `recommendDescription`
- `sortOrder`

可选扩展字段：

- `saledate`
- `brandLogo`

兼容字段别名（另见[通用手机字段别名](#手机字段别名)）：

- `title` -> `recommendTitle`
- `description` -> `recommendDescription`
- `sort`、`sort_order` -> `sortOrder`

### `GET /api/phones`

用途：通用手机列表，用于品牌页、搜索结果和自由取数。

参数：

- `brand`：支持品牌代码、英文名、中文名和兼容别名
- `fields`
- `ids`
- `name`
- `names`
- `q`
- `limit`：最大 `500`

默认字段：

- `id`
- `phonename`
- `company`
- `companyCode`
- `socname`
- `price`
- `battery`
- `imgurl`

常用可选字段：

- `displayPrice`
- `slug`
- `brandLogo`
- `feature`
- `saledate`

兼容字段别名（另见[通用手机字段别名](#手机字段别名)）：

- `storage` -> `storeage`
- `releaseDate` -> `saledate`

搜索规则：

- `q` 会匹配型号、品牌、SoC、CPU、GPU、卖点
- 搜索会扩展品牌别名和常见芯片关键词
- 返回结果优先按发售时间排序，未知发售时间排后

### `GET /api/search`

用途：搜索快捷入口。

参数：

- `q`：必填
- `fields`
- `limit`

默认行为：

- 未传 `fields` 时自动使用精简搜索字段
- 未传 `limit` 时默认 `20`

### `GET /api/brands/{brand}/search`

用途：在指定品牌内搜索。

参数：

- 路径参数 `brand`
- 查询参数 `q`
- `fields`
- `limit`

行为：等价于先指定 `brand` 再调用 `/api/search`。

### `GET /api/phones/{id}`

用途：按数字 ID 读取已发布手机详情。

参数：

- `fields`

详情字段在列表字段基础上支持以下规格字段：

- `screenm`
- `charge`
- `storeage`
- `weight`
- `feature`
- `saledate`
- `official`
- `cpu`
- `gpu`
- `ramfadsf`
- `romagbcz`
- `wifi`
- `bluetooth`
- `screencolor`
- `location`
- `osui`
- `material`
- `sensor`

### `GET /api/phones/detail`

用途：按 `slug` 查详情，可选附带品牌过滤。

参数：

- `slug`：必填
- `brand`：可选
- `fields`

行为：

- `slug` 会做 URL 解码、小写化和分隔符归一化
- 品牌过滤规则与 `/api/phones` 一致
- 返回字段范围与 `/api/phones/{id}` 一致
