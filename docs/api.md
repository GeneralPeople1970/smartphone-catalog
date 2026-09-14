# API 参考

**简体中文** · [English](api.en.md)

[项目介绍](../README.md) · [开发手册](DEVELOPMENT.md)

公开目录 API 提供品牌、机型、搜索和首页内容。基础路径为 `/api`，所有接口均使用 `GET`；目录只返回已发布机型。

## 基础约定

- 目录接口无需登录。`/api/me` 使用浏览器会话 Cookie，同域请求即可读取登录状态。
- 请求时发送 `Accept: application/json`，以 JSON 接收成功结果及 HTTP 错误。
- 默认限流为每分钟 120 次：匿名请求按 IP 计数，已认证的会话请求按账号计数。成功响应包含 `X-RateLimit-Limit`、`X-RateLimit-Remaining`；超限返回 `429`，并附带 `Retry-After` 和 `X-RateLimit-Reset`。
- 同域部署使用 `/api/*` 相对地址；本地跨域开发通过 Vite 代理，配置见[开发手册](DEVELOPMENT.md)。

```sh
curl -H "Accept: application/json" "http://127.0.0.1:8000/api/phones?brand=XIAOMI&fields=id,phonename,displayPrice&limit=24"
```

## 接口一览

| 接口                                | 用途               | 成功响应           |
| ----------------------------------- | ------------------ | ------------------ |
| `GET /api/me`                       | 当前会话           | 对象               |
| `GET /api/brands`                   | 品牌目录           | 数组               |
| `GET /api/homepage-slides`          | 首页轮播图         | 数组               |
| `GET /api/homepage-featured-phones` | 首页热门推荐       | 数组               |
| `GET /api/phones`                   | 机型列表与筛选     | 数组或游标分页对象 |
| `GET /api/search`                   | 关键词搜索         | 数组或游标分页对象 |
| `GET /api/brands/{brand}/search`    | 品牌内搜索         | 数组或游标分页对象 |
| `GET /api/phones/{id}`              | 按数字 ID 查询详情 | 对象               |
| `GET /api/phones/detail`            | 按 slug 查询详情   | 对象               |

## 列表与搜索

以下参数适用于 `/api/phones`、`/api/search` 和 `/api/brands/{brand}/search`。筛选条件可组合使用；品牌内搜索以路径中的品牌为准。

| 参数            | 默认值                | 规则                                                                           |
| --------------- | --------------------- | ------------------------------------------------------------------------------ |
| `brand`         | 无                    | 最长 191 字符，接受品牌代码、英文名、中文名和已知别名                          |
| `q`             | 见右侧                | 列表可省略，超长时截取前 191 字符；两个搜索入口必填，最长 191 字符             |
| `ids`           | 无                    | 逗号分隔或平铺数组；按整数解析，取前 100 个大于 `0` 的值；无有效值时忽略该筛选 |
| `name`、`names` | 无                    | 按名称精确筛选；逗号分隔或平铺数组，合并去重后取前 100 项                      |
| `fields`        | 依接口而定            | 见[字段选择](#字段选择)                                                        |
| `limit`         | 列表 `500`；搜索 `20` | 整数，最小 `1`；超过 `500` 时按 `500` 处理                                     |
| `page`          | `1`                   | 整数，范围 `1..100000`                                                         |
| `paginate`      | `page`                | `page` 或 `cursor`                                                             |
| `cursor`        | 无                    | 最长 4096 字节的令牌；非空时自动使用游标模式                                   |

关键词匹配机型名、品牌、SoC、CPU、GPU、卖点及来源 ID，并扩展已知品牌和芯片别名；全数字关键词也会匹配机型 ID。搜索结果使用与列表相同的固定顺序。

```http
GET /api/phones?ids[]=101&ids[]=102&fields[]=id&fields[]=name
GET /api/search?q=snapdragon&limit=20
GET /api/brands/XIAOMI/search?q=pro&limit=24&paginate=cursor
```

品牌过滤忽略大小写及首尾空格，兼容 `LENOVO_XIAOXIN`、`LIANXIANG` 等旧代码。品牌定义统一维护在 [brands.json](../resources/data/brands.json)。机型保存的品牌决定返回值和过滤结果，来源文件不覆盖已确认的品牌归属。

### 分页模式

排序固定为：**有日期优先 → 日期倒序 → 名称升序 → ID 升序**。`null` 或 `0` 日期视为未知日期。数据未变化时，不同分页大小下的结果顺序一致。

**页码模式**返回手机数组，超过实际总页数返回 `[]`；超出允许的页码范围返回 `422`。

```http
GET /api/phones?fields=id,phonename&page=2&limit=24
```

**游标模式**首次传 `paginate=cursor`，响应示例如下：

```http
GET /api/phones?fields=id,phonename&limit=2&paginate=cursor
```

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

示例令牌仅用于说明结构。下一次请求保留筛选条件，把 `meta.nextCursor` 原样作为 `cursor` 传回；不要自行解码或构造令牌。旧版游标仍可使用，令牌格式不是 Laravel 原生游标格式。最后一页返回 `nextCursor: null`、`hasMore: false`。

`meta` 字段使用 camelCase，`total` 始终为筛选后的全部记录数。两种模式的响应头如下：

| 响应头              | 页码模式     | 游标模式     |
| ------------------- | ------------ | ------------ |
| `X-Total-Count`     | 筛选后的总数 | 筛选后的总数 |
| `X-Per-Page`        | 实际页大小   | 实际页大小   |
| `X-Current-Page`    | 当前页码     | 不返回       |
| `X-Pagination-Mode` | `page`       | `cursor`     |

游标分页仍执行总数查询。切换品牌或关键词时，应重置分页并取消旧请求。项目前台的品牌页与品牌内搜索每次读取 24 条；后台推荐选择器每次最多读取 20 条。这些调用方式不改变 API 默认页大小。

## 字段选择

除 `/api/me` 外，所有接口均支持逗号分隔或平铺数组形式的 `fields`，例如机型列表可传 `fields=id,phonename` 或 `fields[]=id&fields[]=phonename`。省略或传空值时使用默认字段；指定后只返回所选字段。字段名区分大小写，别名会转换为规范字段名，未支持的字段返回 `422`。

### 手机字段

列表、搜索、详情共用以下字段；热门推荐仅支持下表所列的子集。

| 字段组   | 字段                                                                                                                                                                                             |
| -------- | ------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------ |
| 基础字段 | `id`、`phonename`、`company`、`companyCode`、`socname`、`price`、`battery`、`imgurl`                                                                                                             |
| 规格字段 | `screenm`、`charge`、`storeage`、`weight`、`feature`、`saledate`、`official`、`cpu`、`gpu`、`ramfadsf`、`romagbcz`、`wifi`、`bluetooth`、`screencolor`、`location`、`osui`、`material`、`sensor` |
| 附加字段 | `slug`、`brandLogo`、`displayPrice`                                                                                                                                                              |

| 接口                                        | 默认字段                                                                                            | 其他可选字段               |
| ------------------------------------------- | --------------------------------------------------------------------------------------------------- | -------------------------- |
| `/api/phones`                               | 基础字段                                                                                            | 全部规格字段、附加字段     |
| `/api/search`、`/api/brands/{brand}/search` | 基础字段、附加字段                                                                                  | 全部规格字段               |
| `/api/phones/{id}`、`/api/phones/detail`    | 基础字段、规格字段                                                                                  | 全部附加字段               |
| `/api/homepage-featured-phones`             | 基础字段及 `displayPrice`、`feature`、`slug`、`recommendTitle`、`recommendDescription`、`sortOrder` | 仅 `saledate`、`brandLogo` |

- `company` 为品牌展示名，`companyCode` 为规范代码，例如 `小米` 和 `XIAOMI`。
- `price` 可为数字或文本，例如 `3999`、`"3999 起"`；缺失时为 `""`。无法安全转换的超大整数、科学计数法和高精度文本保留为字符串。
- `displayPrice` 始终为展示文本；缺失或值为 `0`、`0.0`、`0.00` 时返回 `暂无价格`。列表、详情和推荐使用相同规则。
- `storeage`、`ramfadsf`、`romagbcz` 等历史拼写保留。完整参数中的合法扩展字段会保存，公开接口只输出白名单字段。

### 手机字段别名

别名仅适用于接口允许的目标字段。例如，热门推荐接受 `releaseDate`，但不接受映射为 `storeage` 的 `storage`。

| 请求别名                     | 返回字段      |
| ---------------------------- | ------------- |
| `name`、`model`、`phoneName` | `phonename`   |
| `brand`                      | `company`     |
| `brandCode`                  | `companyCode` |
| `processor`、`soc`           | `socname`     |
| `image`、`imageUrl`          | `imgurl`      |
| `storage`                    | `storeage`    |
| `releaseDate`                | `saledate`    |

## 其他接口说明

### 会话：`GET /api/me`

未登录时返回：

```json
{ "authenticated": false, "csrfToken": null, "user": null }
```

登录后，`authenticated` 为 `true`，`csrfToken` 为当前会话的 CSRF 令牌，`user` 包含 `id`、`name`、`email`、`canAccessAdmin`。其中 `canAccessAdmin` 表示角色是否达到 editor 或以上；实际访问权限仍由服务端中间件与 Policy 检查。

### 品牌：`GET /api/brands`

仅支持 `fields` 参数，默认返回以下全部字段。结果顺序遵循共享品牌目录，包含没有已发布机型的品牌。

| 字段          | 含义                                             |
| ------------- | ------------------------------------------------ |
| `name`        | 规范品牌名，如 `Xiaomi`                          |
| `code`        | 品牌代码，如 `XIAOMI`                            |
| `displayName` | 展示名，如 `小米`                                |
| `logo`        | 品牌标志路径                                     |
| `path`        | 前台品牌页面路径，如 `/XIAOMI`                   |
| `sort`        | 目录排序值                                       |
| `phoneCount`  | 该品牌已发布机型数量，按保存的品牌及已知别名汇总 |

### 轮播：`GET /api/homepage-slides`

仅支持 `fields` 参数，返回已启用的轮播，按 `sort_order`、`id` 升序。

| 默认及可选字段 | 含义                  | 请求别名                                   |
| -------------- | --------------------- | ------------------------------------------ |
| `id`           | 轮播 ID               | —                                          |
| `title`        | 标题                  | —                                          |
| `image`        | 图片 URL              | `image_path`、`imagePath`、`imgurl`、`url` |
| `linkUrl`      | 跳转 URL，可为 `null` | `link_url`、`link`                         |
| `sortOrder`    | 排序值                | `sort`、`sort_order`                       |

### 推荐：`GET /api/homepage-featured-phones`

仅支持 `fields` 参数，返回推荐已启用且机型已发布的记录，按推荐记录的 `sort_order`、`id` 升序。响应中的 `id` 是机型 ID；手机字段范围见[字段选择](#字段选择)。

| 推荐字段               | 含义                             | 请求别名             |
| ---------------------- | -------------------------------- | -------------------- |
| `recommendTitle`       | 推荐标题；未设置时使用机型名称   | `title`              |
| `recommendDescription` | 推荐说明；未设置时使用 `feature` | `description`        |
| `sortOrder`            | 推荐排序值                       | `sort`、`sort_order` |

### 详情：`GET /api/phones/{id}` 与 `GET /api/phones/detail`

按 ID 查询只需数字 ID 和可选的 `fields`。按 slug 查询的参数如下；两者字段范围相同，机型不存在或未发布时返回 `404`。

| 参数     | 规则                                                                 |
| -------- | -------------------------------------------------------------------- |
| `slug`   | 必填字符串，最长 191 字符；查询前进行 URL 解码、转小写和分隔符归一化 |
| `brand`  | 可选，最长 191 字符；品牌过滤规则与列表一致                          |
| `fields` | 可选，省略时返回基础字段与规格字段                                   |

```http
GET /api/phones/101?fields=id,phonename,displayPrice
GET /api/phones/detail?slug=phone-a&brand=XIAOMI&fields=id,phonename,displayPrice
```

若规范化后的 slug 匹配多条已发布机型，返回 ID 最小的一条；使用 `brand` 可缩小查询范围。

## 错误格式

| 情况                                                   | HTTP 状态码 | JSON 字段                                   |
| ------------------------------------------------------ | ----------- | ------------------------------------------- |
| 列表、搜索或 slug 详情的参数类型、必填项或范围校验失败 | `422`       | `message`、按参数名组织的 `errors`          |
| 请求不支持的字段                                       | `422`       | `message`、`invalidFields`、`allowedFields` |
| 游标内容无效，或 `limit < 1`                           | `422`       | `message`；超长游标先触发参数长度校验       |
| 资源不存在或未发布                                     | `404`       | `message`                                   |
| 请求超出限流预算                                       | `429`       | `message`，响应头包含重试时间               |

例如，`GET /api/brands?fields=unknown` 返回 `422`：

```json
{
    "message": "不支持的字段。",
    "invalidFields": ["unknown"],
    "allowedFields": [
        "name",
        "code",
        "displayName",
        "logo",
        "path",
        "sort",
        "phoneCount"
    ]
}
```

错误提示文本可能使用中文或框架默认语言，应按状态码和字段处理。网络失败没有 HTTP 状态码；客户端应将其与 `404`、`429` 分开处理。

## 后台写入边界

目录 API 保持只读。机型新增、编辑和 JSON 导入通过 `/admin/products` 后台表单完成，受登录、账号状态、邮箱验证设置、角色权限及 CSRF 保护。完整参数根节点必须是对象，已知字段严格校验，合法扩展字段保留；导入整批成功或整批回滚。格式与限制见[手机写入与导入](DEVELOPMENT.md#手机写入与导入)。

旧入口 `/api/home/featured-phones` 已移除，请使用 `/api/homepage-featured-phones`。`/api/site-theme` 也已移除，前台主题跟随系统设置。
