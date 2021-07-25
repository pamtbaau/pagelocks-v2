<?php

use Grav\Common\Grav;
use Grav\Common\Uri;
use Grav\Common\User\DataUser\User;
use Grav\Plugin\PageLocks\Data\AcquireLockResponse;
use Grav\Plugin\PageLocks\Data\ReadLocksResponse;
use Grav\Plugin\PageLocks\Data\RemoveLockResponse;
use Grav\Plugin\PageLocks\LockHandler;
use RocketTheme\Toolbox\File\YamlFile;
use RocketTheme\Toolbox\ResourceLocator\UniformResourceLocator;

class LockHandlerTest extends \Codeception\Test\Unit
{
    const LOCKFILE = 'user-data://pagelocks/locks.yaml';

    protected Grav $grav;
    protected Uri $uri;
    protected YamlFile $lockFile;

    public function _before(): void
    {
        Grav::resetInstance();
        $this->grav = Grav::instance();

        /** @var Grav */
        $grav = Grav::instance();

        $grav['config']->init();

        /** @var User */
        $user = new User();
        $user->email = 'user1@domain.com';
        $user->fullname = 'User 1';
        $user->authenticated = true;
        $user->save();

        $grav['user'] = $user;

        /** @var UniformResourceLocator */
        $locator = $grav['locator'];
        $lockPath = $locator->findResource(self::LOCKFILE, true, true);

        if ($lockPath === false) {
            $this->fail('Path to Lockfile could not be found.');
        }

        $this->lockFile = YamlFile::instance($lockPath);

        /** @var Uri */
        $this->uri = $grav['uri'];
        $this->uri->init();
    }

    protected function _after(): void
    {
    }

    public function testReadEmptyLocks(): void
    {
        $this->uri->setUriProperties(
            [
                'params' => [
                    'pagelocks' => 'readLocks'
                ]
            ]
        );

        $this->set_LOCKFILE([]);

        $lockHandler = new LockHandler();

        /** @var ReadLocksResponse */
        $response = $lockHandler->handleRequest();

        $this->assertEquals(0, count($response->locks));
        $this->assertEquals('Have you confirmed the page is no longer being edited by %s?', $response->alert);
        $this->assertEquals('Found 0 lock(s)', $response->countAlert);
    }

    public function testReadLocks(): void
    {
        $this->uri->setUriProperties(
            [
                'params' => [
                    'pagelocks' => 'readLocks'
                ]
            ]
        );

        $this->set_LOCKFILE([
            '/admin/pages/page1' => [
                'email' => 'user1@domain.com',
                'fullname' => 'User 1',
                'timestamp' => time(),
            ]
        ]);

        $lockHandler = new LockHandler();

        /** @var ReadLocksResponse */
        $response = $lockHandler->handleRequest();

        $this->assertEquals(1, count($response->locks));
        $this->assertEquals(true, isset($response->locks['/admin/pages/page1']));
        $this->assertEquals('user1@domain.com', $response->locks['/admin/pages/page1']->email);
        $this->assertEquals('Have you confirmed the page is no longer being edited by %s?', $response->alert);
        $this->assertEquals('Found 1 lock(s)', $response->countAlert);
    }

    public function testAcquireLockAlreadyLocked(): void
    {
        $this->uri->setUriProperties(
            [
                'params' => [
                    'pagelocks' => 'acquireLock'
                ]
            ]
        );
        $this->set_POST([
            'url' => '/admin/pages/page1',
            'route' => 'page1',
            'lastTimestamp' => 0,
        ]);

        $this->set_LOCKFILE([
            '/admin/pages/page1' => [
                'email' => 'user2@domain.com',
                'fullname' => 'User 2',
                'timestamp' => time(),
            ]
        ]);

        $lockHandler = new LockHandler();

        /** @var AcquireLockResponse */
        $response = $lockHandler->handleRequest();

        $this->assertEquals(false, $response->isLockAcquired);
        $this->assertEquals('user2@domain.com', $response->lockedByUser);
        $this->assertEquals(0, $response->lastTimestamp);
        $this->assertEquals("user2@domain.com is currently editing this page. \nPlease try again later, or contact user2@domain.com to coordinate editing of page.\n", $response->alert);
    }

    public function testAcquireLockGranted(): void
    {
        $this->uri->setUriProperties(
            [
                'params' => [
                    'pagelocks' => 'acquireLock'
                ]
            ]
        );
        $this->set_POST([
            'url' => '/admin/pages/page2',
            'route' => 'page2',
            'lastTimestamp' => 0,
        ]);

        $this->set_LOCKFILE([
            '/admin/pages/page1' => [
                'email' => 'user2@domain.com',
                'fullname' => 'User 2',
                'timestamp' => time(),
            ]
        ]);

        $now = time();

        $lockHandler = new LockHandler();

        /** @var AcquireLockResponse */
        $response = $lockHandler->handleRequest();

        $this->assertEquals(true, $response->isLockAcquired);
        $this->assertEquals('user1@domain.com', $response->lockedByUser);
        $this->assertLessThanOrEqual($now, $response->lastTimestamp);
        $this->assertEquals('Lock has been acquired successfully', $response->alert);
    }

    public function testClearExpiredLocks(): void
    {
        $this->uri->setUriProperties(
            [
                'params' => [
                    'pagelocks' => 'readLocks'
                ]
            ]
        );

        $this->set_LOCKFILE([
            '/admin/pages/page1' => [
                'email' => 'user2@domain.com',
                'fullname' => 'User 2',
                'timestamp' => 0,
            ],
            '/admin/pages/page2' => [
                'email' => 'user2@domain.com',
                'fullname' => 'User 2',
                'timestamp' => time(),
            ]
        ]);

        $lockHandler = new LockHandler();

        /** @var ReadLocksResponse */
        $response = $lockHandler->handleRequest();

        $this->assertEquals(1, count($response->locks));
        $this->assertEquals(true, isset($response->locks['/admin/pages/page2']));
    }

    public function testForceRemoveLock(): void
    {
        $this->uri->setUriProperties(
            [
                'params' => [
                    'pagelocks' => 'forceRemoveLock'
                ]
            ]
        );

        $this->set_POST([
            'url' => '/admin/pages/page2',
        ]);

        $this->set_LOCKFILE([
            '/admin/pages/page1' => [
                'email' => 'user1@domain.com',
                'fullname' => 'User 1',
                'timestamp' => 0,
            ],
            '/admin/pages/page2' => [
                'email' => 'user2@domain.com',
                'fullname' => 'User 2',
                'timestamp' => 0,
            ]
        ]);

        $lockHandler = new LockHandler();

        /** @var RemoveLockResponse */
        $response = $lockHandler->handleRequest();

        $this->assertEquals(true, $response->isLockRemoved);
        $this->assertEquals('Lock has been removed successfully.', $response->alert);
    }

    private function set_LOCKFILE(array $locks): void
    {
        $this->lockFile->save($locks);
        $this->lockFile->free();
    }

    private function set_POST(array $post): void
    {
        global $_POST;
        $_POST = $post;
    }
}
