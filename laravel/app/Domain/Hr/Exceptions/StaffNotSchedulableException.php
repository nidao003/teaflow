<?php

declare(strict_types=1);

namespace App\Domain\Hr\Exceptions;

/**
 * 员工不可排班（停用、门店不匹配等）。
 */
class StaffNotSchedulableException extends HrException
{
    public static function disabled(int $staffId): self
    {
        return new self(sprintf('员工 #%d 已停用，不可排班', $staffId));
    }

    public static function storeMismatch(int $staffId, int $staffStoreId, int $storeId): self
    {
        return new self(sprintf(
            '员工 #%d 归属门店 #%d，与排班门店 #%d 不一致',
            $staffId,
            $staffStoreId,
            $storeId
        ));
    }
}
