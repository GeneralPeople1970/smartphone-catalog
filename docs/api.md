# API 文档

基础路径使用相对地址 `/api`，不要在前端写死 `phone.com`。本地 Vite 开发如不同域名访问，需要由前端开发服务代理 `/api` 到后端域名。

本文档中的响应内容均为占位示例。

## 已废弃接口

`GET /api/home/featured-phones`

该接口已移除。热门机型请改用 `GET /api/homepage-featured-phones`；临时自由取数仍可用通用手机列表接口 `GET /api/phones`。

## 登录状态

`GET /api/me`

用于前端判断当前浏览器是否已经登录后台账号。同域名部署时会自动携带登录 Cookie；如果是跨域开发环境，前端请求需要带上 credentials，并确保代理到后端。

未登录响应：

```json
{
  "authenticated": false,
  "user": null
}
```

已登录响应：

```json
{
  "authenticated": true,
  "user": {
    "id": 1,
    "name": "Admin",
    "email": "admin@example.com"
  }
}
```

前端逻辑建议：

登录按钮区域请求 `/api/me`，当 `authenticated=true` 时显示 `user.name`，否则显示登录按钮。

## 0. 首页轮播图

`GET /api/homepage-slides`

返回后台已上架的首页轮播图。后台使用「上移 / 下移」维护顺序，接口按内部 `sort_order` 从小到大返回；前端通常直接按数组顺序渲染即可。

支持字段裁剪：

```http
GET /api/homepage-slides?fields=title,image,linkUrl
```

字段：

| 字段 | 说明 |
| --- | --- |
| `id` | 轮播图 ID |
| `title` | 标题 |
| `image` | 图片相对路径，来自 `/storage/homepage` |
| `linkUrl` | 点击跳转地址，可为空 |
| `sortOrder` | 后台顺序值，仅用于调试或前端二次排序；数值越小越靠前 |

字段别名也可传入，但响应仍使用标准字段名：

| 别名 | 等同字段 |
| --- | --- |
| `image_path` / `imagePath` / `imgurl` / `url` | `image` |
| `link_url` / `link` | `linkUrl` |
| `sort` / `sort_order` | `sortOrder` |

示例响应：

```json
[
  {
    "id": 1,
    "title": "Example slide",
    "image": "/storage/homepage/example-slide.png",
    "linkUrl": null,
    "sortOrder": 10
  }
]
```

## 0.1 热门机型

`GET /api/homepage-featured-phones`

返回后台「热门管理」里配置并上架的热门机型。后台使用「上移 / 下移」维护顺序，接口按内部 `sort_order` 从小到大返回；前端通常直接按数组顺序渲染即可，不需要再自行排序。

接口返回全部已上架热门机型；当没有上架数据时，本接口返回空数组 `[]`，前端应隐藏热门机型这一栏。后台已取消“热门显示数量”，数量由热门机型的上架状态决定。

支持字段裁剪：

```http
GET /api/homepage-featured-phones?fields=id,phonename,company,socname,price,imgurl,recommendTitle,recommendDescription
```

默认字段：

```text
id, phonename, company, companyCode, socname, price, displayPrice, battery, imgurl, feature, slug, recommendTitle, recommendDescription, sortOrder
```

全部可选字段：

```text
id, phonename, company, companyCode, socname, price, displayPrice, battery,
imgurl, feature, slug, saledate, brandLogo,
recommendTitle, recommendDescription, sortOrder
```

字段说明：

| 字段 | 说明 |
| --- | --- |
| `id` | 手机 ID，可用于 `/api/phones/{id}` |
| `phonename` | 手机型号 |
| `company` | 品牌展示名 |
| `companyCode` | 品牌代码 |
| `socname` | 处理器 |
| `price` | 数据库价格原值，0 仍返回 0 |
| `displayPrice` | 展示价格，价格为 0 时返回“暂无价格” |
| `battery` | 电池容量 |
| `imgurl` | 手机图片 |
| `feature` | 手机原始卖点 |
| `slug` | 手机 slug，当前可能为空 |
| `saledate` | 发售日期 |
| `brandLogo` | 品牌 LOGO |
| `recommendTitle` | 后台配置的热门展示标题；未配置时使用手机型号 |
| `recommendDescription` | 后台配置的热门展示文案；未配置时使用手机卖点 |
| `sortOrder` | 后台顺序值，仅用于调试或前端二次排序；数值越小越靠前 |

字段别名也可传入，但响应仍使用标准字段名：

| 别名 | 等同字段 |
| --- | --- |
| `name` / `model` / `phoneName` | `phonename` |
| `brand` | `company` |
| `brandCode` | `companyCode` |
| `processor` / `soc` | `socname` |
| `image` / `imageUrl` | `imgurl` |
| `title` | `recommendTitle` |
| `description` | `recommendDescription` |
| `sort` / `sort_order` | `sortOrder` |

示例响应：

```json
[
  {
    "id": 1,
    "phonename": "Example Phone A",
    "company": "Example Brand",
    "companyCode": "EXAMPLE",
    "socname": "Example SoC",
    "price": 0,
    "displayPrice": "暂无价格",
    "battery": 0,
    "imgurl": "https://example.com/phone.png",
    "feature": "Synthetic example",
    "slug": "",
    "recommendTitle": "Example Phone A",
    "recommendDescription": "Synthetic example",
    "sortOrder": 10
  }
]
```

## 1. 品牌列表

`GET /api/brands`

支持字段裁剪：

`GET /api/brands?fields=name,code,logo`

可选参数：

| 参数 | 说明 |
| --- | --- |
| `fields` | 逗号分隔，只返回指定字段 |

品牌字段：

| 字段 | 说明 |
| --- | --- |
| `name` | 品牌中文名 |
| `code` | 品牌代码，例如 `XIAOMI` |
| `displayName` | 前端展示名 |
| `logo` | 品牌 LOGO，相对路径，统一来自 `/assets/brands` |
| `path` | 前端品牌页路径 |
| `sort` | 品牌排序 |

示例响应：

```json
[
  {
    "name": "华为",
    "code": "HUAWEI",
    "displayName": "华为",
    "logo": "/assets/brands/Huawei.png",
    "path": "/HUAWEI",
    "sort": 2
  }
]
```

## 2. 手机列表

`GET /api/phones`

用于品牌页、热门机型、搜索列表等场景。返回已发布手机，默认按发售日期排序，新机靠前，发售日期为空或 0 的手机排最后；同一天内同一系列会尽量放在一起。

ID 说明：

`id` 是数据库手机主键，也是详情接口 `/api/phones/{id}` 使用的 ID。后台「批量导入」会使用 JSON 数据里的 `id` 作为手机主键；如果数据库已存在相同 ID，导入会停止并提示错误。

可选参数：

| 参数 | 说明 |
| --- | --- |
| `brand` | 品牌代码或品牌名，例如 `XIAOMI`、`小米` |
| `fields` | 逗号分隔，只返回指定字段 |
| `limit` | 限制返回数量，最大 500；不传则返回匹配的全部数据 |
| `q` | 关键词搜索，支持中英文模糊搜索，匹配型号、处理器、品牌 |
| `ids` | 逗号分隔的手机 ID |
| `name` | 单个或逗号分隔的手机型号，精确匹配 |
| `names` | 单个或逗号分隔的手机型号，精确匹配 |

默认字段：

```text
id, phonename, company, companyCode, socname, price, battery, imgurl
```

全部可选字段：

```text
id, phonename, company, companyCode, socname, price, battery, imgurl,
screenm, charge, storeage, weight, feature, saledate, official,
cpu, gpu, ramfadsf, romagbcz, wifi, bluetooth, screencolor,
location, osui, material, sensor, remark, updateTime, saletime,
slug, brandLogo, displayPrice
```

字段别名也可传入，但响应仍使用标准字段名：

| 别名 | 等同字段 |
| --- | --- |
| `name` / `model` / `phoneName` | `phonename` |
| `brand` | `company` |
| `brandCode` | `companyCode` |
| `processor` / `soc` | `socname` |
| `image` / `imageUrl` | `imgurl` |
| `storage` | `storeage` |
| `releaseDate` | `saledate` |

品牌页示例：

```http
GET /api/phones?brand=XIAOMI&fields=id,phonename,company,socname,price,battery,imgurl,saledate
```

首页示例，取固定 3 台但只返回首页需要的字段：

```http
GET /api/phones?names=Example%20Phone%20A,Example%20Phone%20B&fields=phonename,company,socname,price,feature,imgurl&limit=2
```

只取型号和处理器示例：

```http
GET /api/phones?brand=APPLE&fields=phonename,socname&limit=10
```

示例响应：

```json
[
  {
    "phonename": "Example Phone A",
    "company": "Example Brand",
    "socname": "Example SoC",
    "price": 0,
    "feature": "Synthetic example",
    "imgurl": "https://example.com/phone.png"
  }
]
```

价格说明：

`price` 保持数据库原值，价格为 0 时仍返回 `0`。如前端需要直接显示“暂无价格”，可在 `fields` 中加入 `displayPrice`。

## 3. 搜索手机

`GET /api/search?q={keyword}`

用于前端搜索框和搜索结果页。底层仍读取已发布手机，排序规则与手机列表一致：有发售日期的数据按新到旧排序，空发售日期排最后，同一天内尽量按系列排序。

可选参数：

| 参数 | 说明 |
| --- | --- |
| `q` | 必填，搜索关键词，匹配型号、处理器、品牌 |
| `brand` | 可选，限制品牌范围 |
| `fields` | 可选，只返回指定字段 |
| `limit` | 可选，限制返回数量；不传默认 20，最大 500 |

默认字段：

```text
id, phonename, company, companyCode, socname, price, displayPrice, battery, imgurl, slug, saledate
```

模糊搜索能力：

| 输入示例 | 可匹配方向 |
| --- | --- |
| `小米` / `xiaomi` / `mi` | 小米品牌和小米型号 |
| `红米` / `redmi` | 红米品牌和红米型号 |
| `苹果` / `apple` / `iphone` / `ipad` | 苹果、iPhone、iPad |
| `华为` / `huawei` | 华为 |
| `荣耀` / `honor` | 荣耀 |
| `高通骁龙` / `Qualcomm Snapdragon` / `snapdragon` | 骁龙处理器 |
| `联发科` / `MediaTek` / `Dimensity` | 天玑/联发科处理器 |
| `麒麟` / `Kirin` | 麒麟处理器 |

搜索会同时做去空格、连字符和下划线后的紧凑匹配。

示例：

```http
GET /api/search?q=Example%20Phone&fields=phonename,company,socname,price,displayPrice,saledate&limit=5
```

英文模糊搜索示例：

```http
GET /api/search?q=example&fields=phonename,company,socname,saledate&limit=5
```

处理器模糊搜索示例：

```http
GET /api/search?q=Qualcomm%20Snapdragon&fields=phonename,company,socname,saledate&limit=5
```

示例响应：

```json
[
  {
    "phonename": "Example Phone A",
    "company": "Example Brand",
    "socname": "Example SoC",
    "price": 0,
    "displayPrice": "暂无价格",
    "saledate": 0
  }
]
```

## 4. 品牌内搜索

`GET /api/brands/{brand}/search?q={keyword}`

用于分类页里只搜索当前品牌下的型号。`brand` 支持品牌代码或品牌名，例如 `XIAOMI`、`小米`、`APPLE`、`苹果`。搜索能力与 `/api/search` 一致，支持中英文模糊和紧凑匹配。

等价写法：

```http
GET /api/search?brand=EXAMPLE&q=Phone
```

推荐写法：

```http
GET /api/brands/EXAMPLE/search?q=Phone&fields=phonename,company,socname,saledate&limit=10
```

示例响应：

```json
[
  {
    "phonename": "Example Phone A",
    "company": "Example Brand",
    "socname": "Example SoC",
    "saledate": 0
  }
]
```

## 5. 手机详情，按 ID

`GET /api/phones/{id}`

支持 `fields` 裁剪：

```http
GET /api/phones/1?fields=phonename,company,socname,price,displayPrice,saledate,official,imgurl
```

默认返回列表字段 + 详情字段。

## 6. 手机详情，按品牌和 slug

`GET /api/phones/detail?brand={brand}&slug={slug}`

用于保留前端旧详情路由。支持 `fields` 裁剪。

示例：

```http
GET /api/phones/detail?brand=EXAMPLE&slug=example-phone-a&fields=phonename,socname,price,displayPrice,saledate
```

## 错误格式

传入不支持的字段会返回 `422`：

```json
{
  "message": "不支持的字段。",
  "invalidFields": ["badField"],
  "allowedFields": ["id", "phonename"]
}
```
