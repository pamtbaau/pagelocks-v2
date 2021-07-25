<?php

namespace Grav\Plugin\PageLocks\Traits;

use Exception;

/**
 * Block code from accessing undefined class properties.
 */
trait NoIndexAccessTrait {

    /** 
     * Prevent code from getting value undefined property 
     * 
     * @param string $name The name of the property to set
     */
    public function __get(string $name): void {
        $class = static::class;

        throw new Exception("Code is trying to get undefined property '$class::$name'");
    }
    
    /** 
     * Prevent code from setting value to undefined property 
     * 
     * @param string $name The name of the property to set
     * @param mixed $value The value to assign to the property
     */
    public function __set(string $name, $value): void {
        $class = static::class;

        throw new Exception("Code is trying to set undefined property '$class::$name'");
    }
}