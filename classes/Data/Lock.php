<?php

namespace Grav\Plugin\PageLocks\Data;

use Grav\Plugin\PageLocks\Traits\NoIndexAccessTrait;

class Lock
{
    use NoIndexAccessTrait;

    public string $email = '';
    public string $fullname = '';
    public int $timestamp = 0;

    /** 
     * Parse array of lock data into Lock
     * @param array{email: string, fullname: string, timestamp: int} $data 
     */
    public function load($data): Lock
    {
        foreach ($data as $key => $value) {
            $this->{$key} = $value;
        }

        return $this;
    }
}

$x = new Lock();