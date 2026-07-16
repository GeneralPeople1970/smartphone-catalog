<?php

return [

    /*
    |--------------------------------------------------------------------------
    | 目录搜索驱动
    |--------------------------------------------------------------------------
    |
    | like      —— 默认。对 products.search_text 做 LIKE '%kw%'，跨 SQLite/MySQL
    |              通用，适合中小数据量（公开接口另有限流兜底）。
    | fulltext  —— 生产 MySQL 大数据量方案。使用 ngram 解析器的 FULLTEXT 索引
    |              （迁移 2026_07_16_000002 仅在 MySQL 上创建），BOOLEAN MODE
    |              短语匹配，兼容中文分词；单字关键词与非 MySQL 连接自动降级
    |              回 LIKE，保证搜索永远可用。
    |
    | 切换：.env 设 CATALOG_SEARCH_DRIVER=fulltext（需先跑迁移建索引）。
    |
    */

    'search' => [
        'driver' => env('CATALOG_SEARCH_DRIVER', 'like'),
    ],

];
