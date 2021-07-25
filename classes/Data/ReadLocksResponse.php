<?php

namespace Grav\Plugin\PageLocks\Data;

use Grav\Plugin\PageLocks\Traits\NoIndexAccessTrait;

/**
 * Response to 'readLocks' request
 */
class ReadLocksResponse
{
    use NoIndexAccessTrait;

    public Locks $locks;
    public string $alert;
    public string $countAlert;

    /**
     * @param Locks $locks The locks currenty set on pages
     * @param string $alert Message describing result of 'readLocks' request
     * @param string $countAlert Message describing the number of locks found
     */
    public function __construct(Locks $locks, string $alert, string $countAlert) {
        $this->locks = $locks;
        $this->alert = $alert;
        $this->countAlert = $countAlert;
    }
}
