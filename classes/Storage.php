<?php

namespace Grav\Plugin\PageLocks;

use Exception;
use Grav\Common\Grav;
use Grav\Common\Yaml;
use Grav\Plugin\PageLocks\Data\Locks;
use Grav\Plugin\PageLocks\Traits\NoIndexAccessTrait;
use RocketTheme\Toolbox\File\File;
use RocketTheme\Toolbox\ResourceLocator\UniformResourceLocator;

class Storage
{
    use NoIndexAccessTrait;
    
    const LOCKFILE = 'user-data://pagelocks/locks.yaml';

    protected Grav $grav;
    protected File $file;

    public function __construct()
    {
        $this->grav = Grav::instance();

        $pathToLocks = $this->getPathToLocks();
        $this->file = File::instance($pathToLocks);       
    }

    /**
     * Read all locks for write access.
     * @return Locks containing all locks
     */
    public function readLocksForUpdate(): Locks
    {
        $this->file->lock();

        /** @var Locks */
        $locks = new Locks();
        $locks->load(Yaml::parse($this->file->content()));

        return $locks;
    }

    /**
     * Write locks to file and release file.
     */
    public function saveLocks(Locks $locks): void
    {
        $this->file->save(Yaml::dump($locks->toArray(), JSON_PRETTY_PRINT));
        $this->file->unlock();
        $this->file->free();
    }

    /**
     * Get the path to the lock file (/path/to/user/data/pagelocks/locks.yaml)
     * 
     * @return string Path to locks.yaml file
     * @throws Exception When path to lock file cannot be found.
     */
    public function getPathToLocks(): string {
        /** @var UniformResourceLocator */
        $locator = Grav::instance()['locator'];
        $path = $locator->findResource(self::LOCKFILE, true, true);

        if ($path === false) {
            throw new Exception('Path for lock file cannot be found');
        }

        return $path;
    }
}
