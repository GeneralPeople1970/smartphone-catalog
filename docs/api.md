# API 手册

## 基础约定

- 基础路径：`/api`
- 前端不要写死域名；同域部署直接请求 `/api/*`
- 跨域本地开发时，由前端开发服务代理到 Laravel
- `GET /api/me` 读取浏览器登录态，未登录也可调用；同域部署会携带 Cookie
- 公开接口统一限流（默认每 IP 每分钟 120 次），响应带 `X-RateLimit-*` 头，超限返回 `429`
- `/api/phones` 的默认 `limit` 为 `500`，搜索快捷入口默认 `20`；上限均为 `500`，超过上限时截到 `500`，小于 `1` 或非整数时返回 `422`
- 手机列表与搜索统一按“有日期优先、日期倒序、名称升序、ID 升序”在数据库分页；空日期和 `0` 都排后，不再对单页做二次排序
- 支持默认 `page`（页码 `1..100000`）和 `cursor` 两种模式，保留总数查询；具体返回结构与响应头见[分页模式](#分页模式)
- 参数类型错误、越界页码、无效游标等返回 `422` JSON；错误字段保持已有契约，见[错误格式](#错误格式)
- 前台品牌列表与品牌内搜索显式传 `limit=24` 并按需加载更多；后台推荐选择器每次请求最多 20 条。这些调用策略不改变公开 API 的默认值

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
- `GET /api/site-theme` 已移除；主题仅跟随浏览器的 `prefers-color-scheme` 自动切换，浅色主色固定为 `#007bff`，深色使用低饱和蓝 `#3b82c4`

## 认证状态

### `GET /api/me`

用途：判断当前浏览器是否已登录后台账号。

核心字段：

- `authenticated`
- `user.id`
- `user.name`
- `user.email`
- `user.canAccessAdmin`：是否具备内容管理权限（editor 及以上为 `true`）；前台用户名入口统一指向 `/dashboard`，普通用户可访问只读控制台，服务端中间件与 Policy 执行最终授权

未登录时返回 `authenticated=false`、`user=null`。

## 契约规则

### 品牌展示

- 品牌接口返回 `name`、`code`、`displayName`
- 手机接口返回 `company`、`companyCode`
- 品牌过滤兼容中文名、英文名、品牌代码和旧别名
- `LENOVO_XIAOXIN`、`LIANXIANG` 仍兼容到 Lenovo
- 品牌定义来自共享的 `resources/data/brands.json`，前端路由、后台选项与 API 共用
- 手机保存的品牌字段决定 `company`、`companyCode`、品牌过滤与 `phoneCount`；旧来源文件不覆盖新归属。将 Apple 来源机型改为 Xiaomi 后，这些接口均按 Xiaomi 返回

### 手机字段与价格

列表、搜索、详情和推荐共用 `PhoneFields` 输出映射。价格既可为普通数字，也可为 `3999 起` 等文本；不能安全转换的超大整数、科学计数法或高精度文本保留原文。`displayPrice` 返回展示文本，缺失时为 `暂无价格`，不要求客户端把文本价格转成数字。列表与详情应使用同一格式化逻辑。

`storeage`、`ramfadsf`、`romagbcz` 等历史拼写保持不变。完整参数中的合法扩展字段会保存，但公开响应仍按各接口字段白名单裁剪。

### 字段裁剪

- `GET /api/brands`
- `GET /api/homepage-slides`
- `GET /api/homepage-featured-phones`
- `GET /api/phones`
- `GET /api/phones/{id}`
- `GET /api/phones/detail`
- `GET /api/search`
- `GET /api/brands/{brand}/search`

以上接口都支持逗号分隔的 `fields` 参数及平铺数组形式；请求别名映射到规范字段名后返回，传入该接口不支持的字段时返回 `422`。推荐接口只支持其下文列出的手机字段，不自动开放所有规格字段。

### 手机字段别名

以下 `fields` 请求别名在手机类接口间通用（`/api/phones`、`/api/phones/{id}`、`/api/phones/detail`、`/api/search`、`/api/brands/{brand}/search`、`/api/homepage-featured-phones`）：

- `name`、`model`、`phoneName` -> `phonename`
- `brand` -> `company`
- `brandCode` -> `companyCode`
- `processor`、`soc` -> `socname`
- `image`、`imageUrl` -> `imgurl`

各接口的专属别名见下方对应小节。

### 错误格式

| 情况 | 状态码及响应 |
| --- | --- |
| 参数类型、必填项或范围校验失败 | `422`，包含 `message` 和按参数名组织的 `errors` |
| 请求不支持的字段 | `422`，包含 `message`、`invalidFields`、`allowedFields` |
| 游标内容无效或 `limit < 1` | `422`，包含 `message`；超长游标先由参数长度校验处理 |
| 资源不存在或未发布 | `404` |
| 请求超过限流预算 | `429`，可根据 `Retry-After` 延后重试 |

网络失败没有 HTTP 状态码，客户端应与资源不存在区分。错误展示不能把 `429` 或网络失败当作“没有机型”。

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

`phoneCount` 只统计该品牌已发布机型，按保存的品牌及可确认别名汇总，不按 `source_file` 推断归属。

### `GET /api/homepage-slides`

用途：返回已启用的首页轮播图，按 `sort_order`、`id` 升序。

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

用途：返回首页热门机型，仅包含推荐已启用且手机已发布的数据，按 `sort_order`、`id` 升序。后台未勾选上架的推荐或轮播不出现在对应公开接口。

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
- `ids`：逗号分隔或平铺数组，最多使用 100 个有效 ID
- `name`、`names`：精确名称筛选，支持逗号分隔或平铺数组，合并去重后最多使用 100 项
- `q`：关键词；本入口沿用截取前 191 字符的兼容行为
- `limit`：默认 `500`，最小 `1`，大于 `500` 时截到 `500`
- `page`：可选，从 `1` 开始的页码；配合 `limit` 在数据库层分页，分页元数据见响应头 `X-Total-Count` / `X-Per-Page` / `X-Current-Page`
- `paginate`：可选，`page`（默认）或 `cursor`
- `cursor`：可选，最长 4096 字节的游标令牌（见下）；原有转义编码仍可读取

#### 分页模式

两种模式使用相同顺序：有日期优先、日期倒序、名称升序、ID 升序。相同日期和重复名称仍有唯一 ID 作为最后比较项，分页大小不会改变顺序；`null`、`0` 日期都按未知日期处理。

- **page（默认）**：`?page=N&limit=M`，响应体为手机数组；超出总页数返回 `[]`，页码超过 `100000` 返回 `422`。
- **cursor**：首次传 `?paginate=cursor`，之后保持筛选条件并传入上一响应的 `meta.nextCursor`。非空 `cursor` 会自动启用此模式。内部使用 Laravel `cursorPaginate` 并适配旧游标，无需客户端重新编码；不要把它当作 Laravel 原生游标自行解析。

例如请求 `?fields=id,phonename&limit=2&paginate=cursor`，响应结构为：

```json
{
  "data": [
    { "id": 101, "phonename": "Phone A" },
    { "id": 102, "phonename": "Phone B" }
  ],
  "meta": {
    "nextCursor": "eyJ...",
    "hasMore": true,
    "perPage": 2,
    "total": 3
  }
}
```

示例中的游标仅表示结构，实际应原样使用接口返回值。最后一页 `meta.nextCursor` 为 `null`，`meta.hasMore` 为 `false`；字段名保持 camelCase。

| 响应头 | page 模式 | cursor 模式 |
| --- | --- | --- |
| `X-Total-Count` | 筛选后的总数 | 筛选后的总数 |
| `X-Per-Page` | 实际页大小 | 实际页大小 |
| `X-Current-Page` | 当前页码 | 不返回 |
| `X-Pagination-Mode` | `page` | `cursor` |

游标分页避免 OFFSET，但仍执行总数查询和数据库筛选、排序，不能据此认为整个请求耗时恒定。品牌或关键词改变时应重置游标、取消旧请求；品牌页和品牌内搜索每次加载 24 条，用户选择“加载更多”才继续。

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
- 返回结果使用上述固定分页顺序

### `GET /api/search`

用途：搜索快捷入口。

参数：

- `q`：必填字符串，最长 191 字符，超长返回 `422`
- `brand`：可选，与手机列表的品牌过滤相同
- `fields`
- `limit`
- `page`、`paginate`、`cursor`：与手机列表相同

默认行为：

- 未传 `fields` 时自动使用精简搜索字段
- 未传 `limit` 时默认 `20`
- 默认响应为数组，启用游标后返回相同的 `{data, meta}` 结构与分页响应头
- 后台推荐选择器以本接口每次最多获取 20 条并保留已选项；空关键词改用 `/api/phones?limit=20`

### `GET /api/brands/{brand}/search`

用途：在指定品牌内搜索。

参数：

- 路径参数 `brand`
- 查询参数 `q`：必填字符串，最长 191 字符
- `fields`
- `limit`
- `page`、`paginate`、`cursor`

行为：等价于先指定路径中的 `brand` 再调用 `/api/search`，默认页大小仍为 20；前台品牌内搜索显式传 `limit=24&paginate=cursor`，不会自动遍历整个品牌。

### `GET /api/phones/{id}`

用途：按数字 ID 读取已发布手机详情。

参数：

- `fields`

默认返回列表的默认字段及以下规格字段；`displayPrice`、`slug`、`brandLogo` 可通过 `fields` 显式请求：

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

## 后台写入边界

本手册中的目录 API 保持只读。机型新建、编辑及 JSON 导入仍使用带认证、权限和 CSRF 保护的 `/admin/products` 表单，由共享服务校验和事务写入；完整参数根节点必须是对象，已知字段严格校验，合法扩展保留，导入整批成功或整批回滚。具体限制、品牌审计命令与测试方式见[开发手册](DEVELOPMENT.md#手机写入与导入)。
