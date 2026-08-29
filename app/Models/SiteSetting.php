<?php

namespace App\Models;

use App\Support\SiteSettings;
use Illuminate\Database\Eloquent\Model;

/**
 * A single key/value row of site configuration that operators can change at
 * runtime, without a deploy. Read through {@see SiteSettings}.
 */
class SiteSetting extends Model
{
    protected $fillable = [
        'key',
        'value',
    ];
}
