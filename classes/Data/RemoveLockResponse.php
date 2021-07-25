<?php

namespace Grav\Plugin\PageLocks\Data;

use Grav\Plugin\PageLocks\Traits\NoIndexAccessTrait;

/**
 * Response to 'removeLock' request
 */
class RemoveLockResponse
{
    use NoIndexAccessTrait;

    public bool $isLockRemoved = false;
    public string $alert = '';

    /**
     * @param bool $isLockRemoved `true` if lock has been successfully removed, else `false`
     * @param string $alert Message describing result of 'removeLock' request
     */
    public function __construct(bool $isLockRemoved, string $alert) {
        $this->isLockRemoved = $isLockRemoved;
        $this->alert = $alert;
    }
}
