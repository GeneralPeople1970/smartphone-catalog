<?php

namespace App\Enums;

enum UserStatus: string
{
    case Active = 'active';
    case Suspended = 'suspended';

    /**
     * Human-readable label used in the admin UI.
     */
    public function label(): string
    {
        return match ($this) {
            self::Active => '正常',
            self::Suspended => '停用',
        };
    }
}
