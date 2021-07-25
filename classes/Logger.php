<?php

namespace Grav\Plugin\PageLocks;

use Exception;
use Grav\Common\Grav;
use Grav\Plugin\PageLocks\Traits\NoIndexAccessTrait;
use RocketTheme\Toolbox\File\File;
use RocketTheme\Toolbox\ResourceLocator\UniformResourceLocator;

/**
 * Utility class to handle logging related functions.
 */
class Logger
{
    use NoIndexAccessTrait;

    private const LOGFILE = 'user-data://pagelocks/debug.log';

    private Grav $grav;
    private string $pathToLogFile;
    private bool $debug;

    public function __construct()
    {
        $this->grav = Grav::instance();
        $this->pathToLogFile = $this->getPathToLogFile();
        $this->debug = $this->grav['config']->get('plugins.pagelocks.debug', true);
    }

    /**
     * Log data in log file for debugging purposes.
     */
    public function log(string $message): void
    {
        if (!$this->debug) {
            return;
        }

        /** @var File */
        $file = File::instance($this->pathToLogFile);
        $file->lock();

        $content = $file->content();
        $now = date('c', time());

        $content = "$now\t$message$content";

        $file->save($content);
        $file->unlock();
        $file->free();
    }

    /**
     * Get the path to the debug.log file.
     *
     * @return string The path to the debug.log file
     * @throws Exception When path to log file cannot be found.
     */
    private function getPathToLogFile(): string
    {
        /** @var UniformResourceLocator */
        $locator = $this->grav['locator'];

        $path = $locator->findResource(self::LOGFILE, true, true);

        if ($path === false) {
            throw new Exception('Path for log file cannot be found');
        }

        return $path;
    }
}
