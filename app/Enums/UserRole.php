<?php

namespace App\Enums;

enum UserRole: string
{
    case User = 'user';
    case Editor = 'editor';
    case Admin = 'admin';
    case Owner = 'owner';

    /**
     * Relative privilege level; a higher rank outranks a lower one.
     */
    public function rank(): int
    {
        return match ($this) {
            self::User => 0,
            self::Editor => 1,
            self::Admin => 2,
            self::Owner => 3,
        };
    }

    /**
     * Whether this role is at least as privileged as the given role.
     */
    public function atLeast(self $role): bool
    {
        return $this->rank() >= $role->rank();
    }

    /**
     * Human-readable label used in the admin UI.
     */
    public function label(): string
    {
        return match ($this) {
            self::User => '普通用户',
            self::Editor => '编辑',
            self::Admin => '管理员',
            self::Owner => '所有者',
        };
    }
}
