<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class HomepageSlide extends Model
{
    protected $fillable = [
        'title',
        'image_path',
        'link_url',
        'sort_order',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'sort_order' => 'integer',
            'is_active' => 'boolean',
        ];
    }
}
