# API reference

[简体中文](api.md) · **English**

[Project overview](../README.en.md) · [Development guide](DEVELOPMENT.en.md)

The public catalog API provides brands, phones, search results, and homepage content. The base path is `/api`, and every endpoint uses `GET`. Only published phones appear in the catalog.

## Conventions

- Catalog endpoints require no authentication. `/api/me` reads the browser session cookie on same-origin requests.
- Send `Accept: application/json` to receive successful responses and HTTP errors as JSON.
- The default rate limit is 120 requests per minute, counted by IP for anonymous requests and by account for authenticated session requests. Successful responses include `X-RateLimit-Limit` and `X-RateLimit-Remaining`. Exceeding the limit returns `429` with `Retry-After` and `X-RateLimit-Reset`.
- Use relative `/api/*` URLs for same-origin deployments. For local development across ports, use the Vite proxy described in the [development guide](DEVELOPMENT.en.md).

```sh
curl -H "Accept: application/json" "http://127.0.0.1:8000/api/phones?brand=XIAOMI&fields=id,phonename,displayPrice&limit=24"
```

## Endpoints

| Endpoint                            | Purpose                     | Successful response               |
| ----------------------------------- | --------------------------- | --------------------------------- |
| `GET /api/me`                       | Current session             | Object                            |
| `GET /api/brands`                   | Brand directory             | Array                             |
| `GET /api/homepage-slides`          | Homepage slides             | Array                             |
| `GET /api/homepage-featured-phones` | Homepage recommendations    | Array                             |
| `GET /api/phones`                   | Phone list and filters      | Array or cursor pagination object |
| `GET /api/search`                   | Keyword search              | Array or cursor pagination object |
| `GET /api/brands/{brand}/search`    | Search within a brand       | Array or cursor pagination object |
| `GET /api/phones/{id}`              | Phone details by numeric ID | Object                            |
| `GET /api/phones/detail`            | Phone details by slug       | Object                            |

## Lists and search

The following parameters apply to `/api/phones`, `/api/search`, and `/api/brands/{brand}/search`. Filters can be combined. For brand search, the path parameter determines the brand.

| Parameter       | Default                   | Rules                                                                                                                              |
| --------------- | ------------------------- | ---------------------------------------------------------------------------------------------------------------------------------- |
| `brand`         | None                      | Up to 191 characters; accepts a brand code, English name, Chinese name, or known alias                                             |
| `q`             | See rules                 | Optional for the list, which truncates it to 191 characters; required for both search endpoints, with a 191-character maximum      |
| `ids`           | None                      | Comma-separated values or a flat array; parsed as integers, using the first 100 values greater than `0`; ignored if none are valid |
| `name`, `names` | None                      | Exact name filter; comma-separated values or flat arrays, merged and deduplicated before taking the first 100 entries              |
| `fields`        | Varies by endpoint        | See [field selection](#field-selection)                                                                                            |
| `limit`         | List: `500`; search: `20` | Integer, minimum `1`; values above `500` are capped at `500`                                                                       |
| `page`          | `1`                       | Integer in `1..100000`                                                                                                             |
| `paginate`      | `page`                    | `page` or `cursor`                                                                                                                 |
| `cursor`        | None                      | Token of up to 4096 bytes; a nonempty value enables cursor mode automatically                                                      |

Keywords match phone names, brands, SoCs, CPUs, GPUs, features, and source IDs, with expansion for known brand and chipset aliases. All-digit keywords also match phone IDs. Search results use the same fixed order as phone lists.

```http
GET /api/phones?ids[]=101&ids[]=102&fields[]=id&fields[]=name
GET /api/search?q=snapdragon&limit=20
GET /api/brands/XIAOMI/search?q=pro&limit=24&paginate=cursor
```

Brand filters ignore case and surrounding whitespace and accept legacy codes such as `LENOVO_XIAOXIN` and `LIANXIANG`. Definitions are maintained in [brands.json](../resources/data/brands.json). A phone's saved brand determines its output and filter results; its source file does not override that assignment.

### Pagination

The fixed order is **dated phones first → date descending → name ascending → ID ascending**. Dates of `null` or `0` are treated as unknown. With unchanged data, different page sizes produce the same ordering.

**Page mode** returns an array of phones. Pages beyond the result set return `[]`; page numbers outside the allowed range return `422`.

```http
GET /api/phones?fields=id,phonename&page=2&limit=24
```

**Cursor mode** starts with `paginate=cursor`:

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

The example token is illustrative. Keep the same filters and pass `meta.nextCursor` unchanged as `cursor` in the next request. Do not decode or construct tokens. Legacy cursors remain supported; the token format differs from Laravel's native cursor format. The final page returns `nextCursor: null` and `hasMore: false`.

The `meta` keys use camelCase, and `total` is always the full count after filtering. Pagination headers are:

| Header              | Page mode             | Cursor mode           |
| ------------------- | --------------------- | --------------------- |
| `X-Total-Count`     | Total after filtering | Total after filtering |
| `X-Per-Page`        | Effective page size   | Effective page size   |
| `X-Current-Page`    | Current page number   | Omitted               |
| `X-Pagination-Mode` | `page`                | `cursor`              |

Cursor pagination still queries the total count. Reset pagination and cancel earlier requests when changing the brand or keyword. The project's brand pages and brand searches request 24 phones at a time; the admin recommendation picker requests at most 20. These client choices do not change the API defaults.

## Field selection

Every endpoint except `/api/me` accepts `fields` as comma-separated values or a flat array. For example, phone lists accept `fields=id,phonename` or `fields[]=id&fields[]=phonename`. Omitting the parameter or leaving it empty returns the default fields; specifying it returns only the selected fields. Field names are case-sensitive, aliases resolve to canonical names, and unsupported fields return `422`.

### Phone fields

Lists, search, and details share the fields below. Homepage recommendations accept only the subset specified in the next table.

| Group          | Fields                                                                                                                                                                                           |
| -------------- | ------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------ |
| Core           | `id`, `phonename`, `company`, `companyCode`, `socname`, `price`, `battery`, `imgurl`                                                                                                             |
| Specifications | `screenm`, `charge`, `storeage`, `weight`, `feature`, `saledate`, `official`, `cpu`, `gpu`, `ramfadsf`, `romagbcz`, `wifi`, `bluetooth`, `screencolor`, `location`, `osui`, `material`, `sensor` |
| Additional     | `slug`, `brandLogo`, `displayPrice`                                                                                                                                                              |

| Endpoint                                    | Default fields                                                                                     | Other available fields                  |
| ------------------------------------------- | -------------------------------------------------------------------------------------------------- | --------------------------------------- |
| `/api/phones`                               | Core                                                                                               | All specification and additional fields |
| `/api/search`, `/api/brands/{brand}/search` | Core and additional                                                                                | All specification fields                |
| `/api/phones/{id}`, `/api/phones/detail`    | Core and specifications                                                                            | All additional fields                   |
| `/api/homepage-featured-phones`             | Core plus `displayPrice`, `feature`, `slug`, `recommendTitle`, `recommendDescription`, `sortOrder` | Only `saledate` and `brandLogo`         |

- `company` is the brand display name, and `companyCode` is its canonical code, such as `小米` and `XIAOMI`.
- `price` can be a number or text, such as `3999` or `"3999 起"` (from 3999). A missing price is `""`. Integers too large to convert safely, scientific notation, and high-precision text remain strings.
- `displayPrice` is always display text. Missing prices and values of `0`, `0.0`, or `0.00` return `暂无价格` (price unavailable). Lists, details, and recommendations use the same rule.
- Legacy spellings such as `storeage`, `ramfadsf`, and `romagbcz` are preserved. Valid extension fields in stored specifications are retained, but public responses include only allowlisted fields.

### Phone field aliases

An alias is available only when the endpoint allows its target field. For example, recommendations accept `releaseDate`, but reject `storage` because its target is `storeage`.

| Request alias                | Response field |
| ---------------------------- | -------------- |
| `name`, `model`, `phoneName` | `phonename`    |
| `brand`                      | `company`      |
| `brandCode`                  | `companyCode`  |
| `processor`, `soc`           | `socname`      |
| `image`, `imageUrl`          | `imgurl`       |
| `storage`                    | `storeage`     |
| `releaseDate`                | `saledate`     |

## Endpoint details

### Session: `GET /api/me`

An unauthenticated request returns:

```json
{ "authenticated": false, "csrfToken": null, "user": null }
```

When signed in, `authenticated` is `true`, `csrfToken` is the session's CSRF token, and `user` contains `id`, `name`, `email`, and `canAccessAdmin`. The capability flag indicates a role of editor or above; server middleware and policies enforce actual access.

### Brands: `GET /api/brands`

Accepts only `fields`, defaulting to all fields below. Results follow the shared brand directory order and include brands with no published phones.

| Field         | Meaning                                                         |
| ------------- | --------------------------------------------------------------- |
| `name`        | Canonical brand name, such as `Xiaomi`                          |
| `code`        | Brand code, such as `XIAOMI`                                    |
| `displayName` | Display name, such as `小米`                                    |
| `logo`        | Brand logo path                                                 |
| `path`        | Frontend brand page path, such as `/XIAOMI`                     |
| `sort`        | Directory sort value                                            |
| `phoneCount`  | Published phone count, grouped by saved brand and known aliases |

### Slides: `GET /api/homepage-slides`

Accepts only `fields`. Returns enabled slides ordered by `sort_order`, then `id`, both ascending.

| Default and available field | Meaning                        | Request aliases                            |
| --------------------------- | ------------------------------ | ------------------------------------------ |
| `id`                        | Slide ID                       | —                                          |
| `title`                     | Title                          | —                                          |
| `image`                     | Image URL                      | `image_path`, `imagePath`, `imgurl`, `url` |
| `linkUrl`                   | Destination URL; may be `null` | `link_url`, `link`                         |
| `sortOrder`                 | Sort value                     | `sort`, `sort_order`                       |

### Recommendations: `GET /api/homepage-featured-phones`

Accepts only `fields`. Returns enabled recommendations whose phones are published, ordered by the recommendation record's `sort_order`, then `id`, both ascending. The response `id` is the phone ID. Available phone fields are listed under [field selection](#field-selection).

| Recommendation field   | Meaning                                             | Request aliases      |
| ---------------------- | --------------------------------------------------- | -------------------- |
| `recommendTitle`       | Recommendation title; falls back to the phone name  | `title`              |
| `recommendDescription` | Recommendation description; falls back to `feature` | `description`        |
| `sortOrder`            | Recommendation sort value                           | `sort`, `sort_order` |

### Details: `GET /api/phones/{id}` and `GET /api/phones/detail`

Lookup by ID requires a numeric ID and optionally `fields`. Lookup by slug accepts the parameters below. Both endpoints support the same fields and return `404` for missing or unpublished phones.

| Parameter | Rules                                                                                                    |
| --------- | -------------------------------------------------------------------------------------------------------- |
| `slug`    | Required string of up to 191 characters; URL-decoded, lowercased, and separator-normalized before lookup |
| `brand`   | Optional, up to 191 characters; uses the same brand filtering as the list                                |
| `fields`  | Optional; defaults to core and specification fields                                                      |

```http
GET /api/phones/101?fields=id,phonename,displayPrice
GET /api/phones/detail?slug=phone-a&brand=XIAOMI&fields=id,phonename,displayPrice
```

If a normalized slug matches multiple published phones, the endpoint returns the one with the lowest ID. Use `brand` to narrow the lookup.

## Errors

| Condition                                                                            | HTTP status | JSON fields                                                            |
| ------------------------------------------------------------------------------------ | ----------- | ---------------------------------------------------------------------- |
| List, search, or slug lookup: invalid type, missing parameter, or out-of-range value | `422`       | `message` and `errors` keyed by parameter name                         |
| Unsupported requested field                                                          | `422`       | `message`, `invalidFields`, `allowedFields`                            |
| Invalid cursor contents or `limit < 1`                                               | `422`       | `message`; an oversized cursor fails parameter length validation first |
| Missing or unpublished resource                                                      | `404`       | `message`                                                              |
| Rate limit exceeded                                                                  | `429`       | `message`, with retry timing in response headers                       |

For example, `GET /api/brands?fields=unknown` returns `422`:

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

Message text may be Chinese or use the framework's default language. Handle errors by status code and fields. Network failures have no HTTP status code and should be handled separately from `404` and `429`.

## Admin writes

The catalog API is read-only. Phone creation, editing, and JSON imports use the `/admin/products` forms, protected by authentication, account status, email verification settings, roles, and CSRF checks. The specifications root must be an object; known fields are strictly validated and valid extension fields are preserved. Imports either succeed or roll back as a batch. See [product writes and imports](DEVELOPMENT.en.md#product-writes-and-imports) for formats and limits.

The legacy `/api/home/featured-phones` endpoint has been removed; use `/api/homepage-featured-phones`. `/api/site-theme` has also been removed, and the frontend follows the system theme.
