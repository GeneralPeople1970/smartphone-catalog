# API 手册

## 基础约定

- 基础路径：`/api`
- 前端不要写死域名；同域部署直接请求 `/api/*`
- 跨域本地开发时，由前端开发服务代理到 Laravel
- `GET /api/me` 依赖登录态；同域名部署会自动携带 Cookie
- 公开接口统一限流（默认每 IP 每分钟 120 次），响应带 `X-RateLimit-*` 头，超限返回 `429`
- 列表接口 `limit` 未传时默认 `500`，且上限为 `500`；搜索关键词、`ids`/`names` 数量均有上限
- 列表接口在数据库层排序并分页，响应头返回 `X-Total-Count`（匹配总数）、`X-Per-Page`、`X-Current-Page` 作为分页元数据
- `/api/phones` 支持两种分页模式：默认 `page`（兼容模式，页码上限 `100000`）与 `cursor`（游标/键集分页，深分页推荐，见接口详情）
- 查询参数统一校验：预期字符串处传入数组/对象、`page`/`limit` 非整数或超范围、`cursor` 无效等，一律返回统一 `422` JSON（`{"message": ..., "errors": ...}`），不会产生 `500`

## 接口速览

| 方法与路径 | 用途 |
| --- | --- |
| `GET /api/me` | 当前浏览器登录状态 |
| `GET /api/brands` | 品牌目录 |
| `GET /api/homepage-slides` | 已启用的首页轮播图 |
| `GET /api/homepage-featured-phones` | 首页热门机型 |
| `GET /api/phones` | 通用手机列表（品牌页 / 搜索 / 取数） |
| `GET /api/search` | 搜索快捷入口 |
| `GET /api/brands/{brand}/search` | 指定品牌内搜索 |
| `GET /api/phones/{id}` | 按数字 ID 读取详情 |
| `GET /api/phones/detail` | 按 `slug` 读取详情 |

字段裁剪、别名与各接口详情见下方[契约规则](#契约规则)与[接口清单](#接口清单)。

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
- `user.canAccessAdmin`：是否可访问后台（editor 及以上为 `true`）；前台据此决定用户名链接指向 `/dashboard` 还是 `/profile`，服务端中间件与 Policy 仍是真正的权限关卡

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
- `limit`：默认 `500`，最大 `500`
- `page`：可选，从 `1` 开始的页码；配合 `limit` 在数据库层分页，分页元数据见响应头 `X-Total-Count` / `X-Per-Page` / `X-Current-Page`
- `paginate`：可选，`page`（默认）或 `cursor`
- `cursor`：可选，游标分页令牌（见下）

分页模式：

- **page（默认，兼容模式）**：`?page=N&limit=M`，响应体为手机数组，分页元数据在响应头（`X-Total-Count` / `X-Per-Page` / `X-Current-Page`）；页码上限 `100000`，超过返回 `422`；超出总页数安全返回空数组 `[]`。
- **cursor（游标/键集分页，推荐用于深分页）**：传 `?paginate=cursor`（或直接带 `cursor=`）。排序确定（发售时间倒序，`id` 作为唯一 tie-breaker），不使用 OFFSET，深翻页恒为 `O(limit)`。响应体为对象：

```json
{
  "data": [ /* 手机数组 */ ],
  "meta": {
    "nextCursor": "eyJ...",   // 下一页游标；无更多数据时为 null
    "hasMore": true,
    "perPage": 500,
    "total": 1421
  }
}
```

  逐页把上一响应的 `meta.nextCursor` 作为下次请求的 `cursor` 传入，直至 `nextCursor` 为 `null`。响应头附带 `X-Pagination-Mode: cursor`；无效 `cursor` 返回 `422`。

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
