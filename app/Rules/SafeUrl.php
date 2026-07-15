<?php

namespace App\Rules;

use App\Support\SafeUrl as SafeUrlSupport;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class SafeUrl implements ValidationRule
{
    /**
     * Reject URLs that are not a site-relative path or an http(s) URL.
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (is_string($value) && ! SafeUrlSupport::passes($value)) {
            $fail('请填写有效的链接（仅支持站内路径、http 或 https）。');
        }
    }
}
