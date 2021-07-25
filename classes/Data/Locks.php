<?php

namespace Grav\Plugin\PageLocks\Data;

use ArrayAccess;
use Countable;
use Exception;
use Grav\Common\Grav;
use Grav\Plugin\PageLocks\Traits\NoIndexAccessTrait;
use Iterator;
use JsonSerializable;

/**
 * Array of urls and their accompanying lock
 */
class Locks implements ArrayAccess, Countable, Iterator, JsonSerializable
{
    use NoIndexAccessTrait;

    /** @var array<string, Lock> */
    private $container = [];

    /** @var int */
    private $index = 0;

    /** 
     * Parse array of urls and accompanying lock data into Locks
     * 
     * @param array<string, array{email: string, fullname: string, timestamp: int}> $data */
    public function load(array $data): Locks
    {
        foreach ($data as $url => $lockItem) {
            /** @var Lock */
            $lock = new Lock();
            $this->container[$url] = $lock->load($lockItem);
        }

        return $this;
    }

    public function toArray(): array
    {
        $locks = [];

        foreach ($this->container as $url => $lock) {
            $locks[$url] = (array) $lock;
        }

        return $locks;
    }

    /**
     * @param string $url
     * @param Lock $lock
     */
    public function offsetSet($url, $lock)
    {
        $this->container[$url] = $lock;
    }

    /**
     * @param string $url
     * @return bool
     */
    public function offsetExists($url)
    {
        return isset($this->container[$url]);
    }

    /**
     * @param string $url
     */
    public function offsetUnset($url)
    {
        unset($this->container[$url]);
    }

    /**
     * @param string $url
     * @return Lock
     * @throws Exception If offset does not exist
     */
    public function offsetGet($url)
    {
        if (isset($this->container[$url])) {
            return $this->container[$url];
        } else {
            throw new Exception("No lock for route $url");
        }
    }

    /*
     * Implementation of Countable
     */

    /**
     * @return int
     */
    public function count()
    {
        return count($this->container);
    }

    /*
     * Implementation of Iterator
     */

    /**
     * @return void
     */
    public function rewind()
    {
        $this->index = 0;
    }

    /**
     * @return Lock
     */
    public function current()
    {
        $urls = array_keys($this->container);
        $lock = $this->container[$urls[$this->index]];

        return $lock;
    }

    /**
     * @return string
     */
    public function key()
    {
        $urls = array_keys($this->container);
        $url = $urls[$this->index];

        return $url;
    }

    public function next()
    {
        $this->index++;
    }

    /**
     * $return bool
     */
    public function valid()
    {
        $urls = array_keys($this->container);
        $isValid = isset($urls[$this->index]);

        return $isValid;
    }

    public function jsonSerialize() {
        $locks = [];

        foreach ($this->container as $url => $lock) {
            $locks[$url] = (array) $lock;
        }

        return $locks;
    }
}
