<?php

namespace Grav\Plugin\PageLocks;

use Exception;
use Grav\Common\Grav;
use Grav\Common\Page\Pages;
use Grav\Plugin\PageLocks\Traits\NoIndexAccessTrait;

/**
 * Utility class to handle Page related functions.
 */
class PageHandler
{
    use NoIndexAccessTrait;

    /**
     * Check if page has been edited/changed since last keepAlive request.
     */
    public function hasPageBeenModified(string $route, int $lastTmestamp): bool
    {
        $grav = Grav::instance();

        $grav['admin']->enablePages();

        /** @var Pages */
        $pages = $grav['pages'];
        $routes = $pages->routes();
        $path = $routes["/$route"];
        $page = $pages->get($path);

        if ($page) {
            $lastModified = $page->modified();
        } else {
            throw new Exception("Page with route '$route' should exist.");
        }

        return $lastModified > $lastTmestamp;
    }
}
