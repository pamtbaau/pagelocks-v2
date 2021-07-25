<?php

namespace Grav\Plugin\PageLocks\Data;

use Grav\Plugin\PageLocks\Traits\NoIndexAccessTrait;

/**
 * Response to 'acquireLock' request
 */
class AcquireLockResponse
{
    use NoIndexAccessTrait;

    public bool $isLockAcquired;
    public int $lastTimestamp;
    public string $lockedByUser;
    public string $alert;

    /**
     * @param bool $isLockAcquired `true` if locks as been successfully acquired, else `false`
     * @param int $lastTimestamp The `unixepoch` in seconds when locks has been acquired, or `0` when no lock has been acquired.
     * @param string $lockedByUser The email address of the user acquiring the lock
     * @param string $alert Message describing result of 'acquireLock' request
     */
    public function __construct(bool $isLockAcquired, int $lastTimestamp, string $lockedByUser, string $alert) {
        $this->isLockAcquired = $isLockAcquired;
        $this->lastTimestamp = $lastTimestamp;
        $this->lockedByUser = $lockedByUser;
        $this->alert = $alert;
    }
}
