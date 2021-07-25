<?php

namespace Grav\Plugin\PageLocks\Data;

use Exception;
use Grav\Common\Grav;
use Grav\Common\Uri;
use Grav\Plugin\PageLocks\Traits\NoIndexAccessTrait;

/**
 * Contains filtered content of $_POST
 */
class FilteredPost
{
    use NoIndexAccessTrait;

    /** Current url in Admin */
    public string $url = '';

    /** Route of page */
    public string $route = '';

    /** Timestamp when locks has been acquired or extended request */
    public int $lastTimestamp = 0;

    public function __construct()
    {
        /** @var Uri */
        $uri = Grav::instance()['uri'];

        $post = $uri->post();

        if (!$post) {
            return;
        }

        $request = $uri->param('pagelocks');

        switch ($request) {
            case 'acquireLock':
                $this->route = filter_var($post['route'], FILTER_SANITIZE_URL);
                $this->lastTimestamp = filter_var(filter_var($post['lastTimestamp'], FILTER_SANITIZE_NUMBER_INT));
            case 'forceRemoveLock':
                $this->url = filter_var(filter_var($post['url'], FILTER_SANITIZE_STRING));
                break;
            case 'readLocks':
            case 'removeLock':
                break;
            default:
                throw new Exception("Case '{$request}' is not being handled.");
        }
    }
}
