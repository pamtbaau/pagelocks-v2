<?php

namespace Grav\Plugin\PageLocks;

use Exception;
use Grav\Common\Data\Data;
use Grav\Common\Grav;
use Grav\Common\Uri;
use Grav\Common\User\DataUser\User;
use Grav\Plugin\PageLocks\Data\Lock;
use Grav\Plugin\PageLocks\Data\Locks;
use Grav\Plugin\PageLocks\Data\FilteredPost;
use Grav\Plugin\PageLocks\Data\AcquireLockResponse;
use Grav\Plugin\PageLocks\Data\ReadLocksResponse;
use Grav\Plugin\PageLocks\Data\RemoveLockResponse;
use Grav\Plugin\PageLocks\Traits\NoIndexAccessTrait;

/**
 * Handles are LockPage requests.
 *
 * @psalm-suppress PropertyNotSetInConstructor
 */
class LockHandler
{
    use NoIndexAccessTrait;

    private const NEWLOCK = 0;

    private Grav $grav;
    private Uri $uri;
    private Data $config;
    private Storage $storage;
    private User $user;
    private Locks $locks;
    private bool $debug;
    private FilteredPost $filteredPost;
    private Logger $logger;

    public function __construct()
    {
        $this->grav = Grav::instance();
        $this->uri = $this->grav['uri'];
        $this->config = $this->grav['config'];
        $this->user = $this->grav['user'];

        $this->logger = new Logger();
        $this->storage = new Storage();
    }

    /**
     * Handle async requests send by javascript injected by PageLocks into Admin panel.
     *
     * @return ReadLocksResponse|AcquireLockResponse|RemoveLockResponse|RemoveLockResponse|null
     * The response specific to each type of request, or null if request is not from PageLocks.
     */
    public function handleRequest()
    {
        if (!$this->isSendByPageLocks()) {
            return null;
        }

        $this->filteredPost = new FilteredPost();

        $this->locks = $this->storage->readLocksForUpdate();

        $this->clearExpiredLocks();

        $request = $this->uri->param('pagelocks');

        switch ($request) {
            case 'readLocks':
                $response = $this->handleReadLocks();
                break;
            case 'acquireLock':
                $response = $this->handleAcquireLock();
                break;
            case 'removeLock':
                $response = $this->handleRemoveLock();
                break;
            case 'forceRemoveLock':
                $response = $this->handleForceRemoveLock();
                break;
            default:
                throw new Exception("Unkown request '$request'.");
        }

        $this->storage->saveLocks($this->locks);

        return $response;
    }

    /**
     * Check if request originated from front-end javascript from PageLocks.
     *
     * @return bool True if request is sent by PageLocks
     */
    private function isSendByPageLocks(): bool
    {
        return $this->uri->param('pagelocks') !== false;
    }

    /**
     * Loops through all locks and removes expired locks
     */
    private function clearExpiredLocks(): void
    {
        $expiresAfter = $this->config->get('plugins.pagelocks.expiresAfter', 3600);
        $now = time();

        foreach ($this->locks as $url => $lock) {
            $lastTimestamp = $lock->timestamp;
            $elapsedTime = $now - $lastTimestamp;

            if ($elapsedTime > $expiresAfter) {
                $this->logger->log("$url : Clear expired lock\n");

                unset($this->locks[$url]);
            }
        }
    }

    /**
     * Wrap already fetched locks in a ReadLocksResponse.
     *
     * @return ReadLocksResponse Response containg locks.
     */
    private function handleReadLocks(): ReadLocksResponse
    {
        $this->logger->log("/admin/locks : Read all locks\n");

        return new ReadLocksResponse(
            $this->locks,
            $this->translate('PLUGIN_PAGELOCKS.ALERT.CONFIRM_DELETE'),
            $this->translate(['PLUGIN_PAGELOCKS.ALERT.LOCK_COUNT', count($this->locks)]),
        );
    }

    /**
     * Set lock if page is not already locked by other user.
     *
     * @return AcquireLockResponse Response for 'acquireLock' request.
     */
    private function handleAcquireLock(): AcquireLockResponse
    {
        $lock = $this->isPageLocked();

        if ($lock) {
            if ($this->isLockedByCurrentUser($lock)) {
                return $this->keepLockAlive($lock);
            } else {
                return $this->pageAlreadyLocked($lock);
            }
        } else {
            if ($this->isKeepAliveRequest()) {
                return $this->tryReclaimingLock();
            } else {
                return $this->acquireLock();
            }
        }
    }

    /**
     * Handle request when user is NOT on page.
     * 
     * @return RemoveLockResponse
     */
    private function handleRemoveLock(): RemoveLockResponse
    {
        $isRemoved = $this->removeLockOfUser();

        return new RemoveLockResponse(
            $isRemoved,
            '',
        );
    }

    /**
     * Forcefully remove lock held on page.
     *
     * @return RemoveLockResponse Response for 'forceRemoveLocks' request.
     */
    private function handleForceRemoveLock(): RemoveLockResponse
    {
        $url = $this->filteredPost->url;

        if ($this->removeLock()) {
            $this->logger->log("$url : Lock removed by force by {$this->user->email}\n");

            return new RemoveLockResponse(
                true,
                $this->translate('PLUGIN_PAGELOCKS.ALERT.LOCK_REMOVED')
            );
        } else {
            return new RemoveLockResponse(
                false,
                $this->translate('PLUGIN_PAGELOCKS.ALERT.LOCK_NOT_REMOVED')
            );
        }
    }

    /**
     * Check if lock on page is held by current user
     * 
     * @return bool `true` if current users holds lock on page, else `false`
     */
    private function isLockedByCurrentUser(Lock $lock): bool
    {
        return $lock->email === $this->user->email;
    }

    /**
     * Check if request is intended to extend/keelAlive an existing lock.
     * 
     * @return bool `true` if request wants to extend the lock.
     */
    private function isKeepAliveRequest(): bool
    {
        return $this->filteredPost->lastTimestamp > 0;
    }

    /**
     * Set lock on page.
     * 
     * @return AcquireLockResponse Contains reponse
     */
    private function acquireLock(): AcquireLockResponse
    {
        $url = $this->filteredPost->url;

        $this->removeLockOfUser();
        $lock = $this->setLock();

        $this->logger->log("$url : Lock acquired: By {$this->user->email}\n");

        return new AcquireLockResponse(
            true,
            $lock->timestamp,
            $lock->email,
            $this->translate('PLUGIN_PAGELOCKS.ALERT.LOCK_GRANTED'),
        );
    }

    /**
     * Create reponse when acquiring lock failed.
     * 
     * @return AcquireLockResponse Contains reponse when acquiring lock has failed
     */
    private function pageAlreadyLocked(Lock $lock): AcquireLockResponse
    {
        $url = $this->filteredPost->url;

        $this->logger->log("$url : Lock request by {$this->user->email}, already locked by {$lock->email}\n");

        return new AcquireLockResponse(
            false,
            0,
            $lock->email,
            $this->translate([
                'PLUGIN_PAGELOCKS.ALERT.ALREADY_LOCKED',
                $lock->email, $lock->email
            ])
        );
    }
    /**
     * Update timestamp of lock to extend the lifetime of the lock.
     *
     * @return AcquireLockResponse Response for 'keepAlive' request.
     */
    private function keepLockAlive(Lock $lock): AcquireLockResponse
    {
        $url = $this->filteredPost->url;

        $this->logger->log("$url : Lock extended: Held by {$this->locks[$url]->email}\n");

        $lock->timestamp = time();

        return new AcquireLockResponse(
            true,
            $lock->timestamp,
            $lock->email,
            $this->translate('PLUGIN_PAGELOCKS.ALERT.LOCK_GRANTED'),
        );
    }

    /**
     * If previous lock has expired, try regaining the lock.
     * 
     * @return AcquireLockResponse Containg the reponse for the client.
     */
    private function tryReclaimingLock(): AcquireLockResponse
    {
        /** @var PageHandler */
        $pageHandler = new PageHandler();

        $route = $this->filteredPost->route;
        $url = $this->filteredPost->url;

        if ($pageHandler->hasPageBeenModified($route, $this->filteredPost->lastTimestamp)) {
            $this->logger->log("$url : Lock expired and page has been changed.\n");

            return new AcquireLockResponse(
                false,
                0,
                '',
                $this->translate('PLUGIN_PAGELOCKS.ALERT.LOCK_EXPIRED'),
            );
        } else {
            return $this->acquireLock();
        }
    }

    /**
     * Remove the lock for the current url
     * 
     * @return bool If lock removed, return `true`, else `false`
     */
    private function removeLock(): bool
    {
        $url = $this->filteredPost->url;

        if (isset($this->locks[$url])) {
            unset($this->locks[$url]);

            return true;
        }

        return false;
    }

    /**
     * Remove lock held by user if user is no longer on same page.
     * 
     * @return bool False if user did not held any lock.
     */
    private function removeLockOfUser(): bool
    {
        foreach ($this->locks as $lockRoute => $lock) {
            if ($lock->email === $this->user->email) {
                $this->logger->log("$lockRoute : Lock removed: Was held by {$lock->email}\n");

                unset($this->locks[$lockRoute]);

                return true;
            }
        }

        return false;
    }

    /**
     * Set lock on page for user.
     * 
     * @return Lock The lock acquired by user.
     */
    private function setLock(): Lock
    {
        $url = $this->filteredPost->url;

        $lock = new Lock();
        $lock->load([
            // Psalm does not support (yet) @property on interfaces: cast required
            'email' => (string) $this->user->email,
            'fullname' => (string) $this->user->fullname,
            'timestamp' => time(),
        ]);

        $this->locks[$url] = $lock;

        return $lock;
    }

    /**
     * Check if page is locked by other user
     *
     * @return ?Lock The lock already set on page, or false if not locked.
     */
    private function isPageLocked()
    {
        $url = $this->filteredPost->url;

        if (isset($this->locks[$url])) {
            $lock = $this->locks[$url];

            return $lock;
        }

        return null;
    }

    /**
     * Translate alerts
     *
     * @param string|array $args Can be a string, or an array containing a string and its parameters.
     */
    private function translate($args): string
    {
        return $this->grav['language']->translate($args);
    }
}
