<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * Thrown when a role change, suspension or deletion would leave the system
 * without any active owner. The surrounding transaction is rolled back.
 */
class LastActiveOwnerException extends RuntimeException
{
    public function __construct(string $message = '系统必须至少保留一名启用状态的所有者，该操作已被拒绝。')
    {
        parent::__construct($message);
    }
}
