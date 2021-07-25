<?php

use Grav\Common\Grav;
use RocketTheme\Toolbox\File\File;
use RocketTheme\Toolbox\File\YamlFile;
use RocketTheme\Toolbox\ResourceLocator\UniformResourceLocator;

use function PHPUnit\Framework\assertArrayHasKey;
use function PHPUnit\Framework\assertArrayNotHasKey;
use function PHPUnit\Framework\assertCount;
use function PHPUnit\Framework\assertGreaterThan;

/**
 * - Dummy Author 2 is granted a dummy lock in the lock file on page /admin/pages/typography
 * - Auhtor 1 is logging in and tries te get locks on home (succeeds) and typography (fails)
 */

class PageLocksCest
{
    const LOCKFILE = 'user-data://pagelocks/locks.yaml';
    const LOGFILE = 'user-data://pagelocks/debug.log';
    const CONFIGFILE = 'config://plugins/pagelocks.yaml';

    protected YamlFile $lockFile;
    protected YamlFile $configFile;
    protected File $logFile;

    protected array $configDefaults = [
        'enabled' => true,
        'expiresAfter' =>  3600,
        'keepAliveInterval' =>  60,
        'productionMode' =>  false,
        'debug' =>  true,
    ];

    public function __construct()
    {
        $grav = Grav::instance();

        /** @var UniformResourceLocator */
        $locator = $grav['locator'];

        $lockPath = $locator->findResource(self::LOCKFILE, true, true);
        if ($lockPath === false) {
            throw new Exception('Resource "self::LOCKFILE" could not be found');
        }
        $this->lockFile = YamlFile::instance($lockPath);

        $configPath = $locator->findResource(self::CONFIGFILE, true, true);
        if ($configPath === false) {
            throw new Exception('Resource "self::CONFIGFILE" could not be found');
        }
        $this->configFile = YamlFile::instance($configPath);

        $logPath = $locator->findResource(self::LOGFILE, true, true);
        
        if ($logPath === false) {
            throw new Exception('Resource "self::LOGFILE" could not be found');
        }
        $this->logFile = File::instance($logPath);

        $this->logFile->save('');
        $this->logFile->free();

        $this->setConfigDefaults();
    }

    public function _before(): void
    {
    }

    public function _after(): void
    {
        $this->setConfigDefaults();
        $this->set_LOCKFILE([]);
    }

    public function grantLock(AcceptanceTester $I): void
    {
        // Add dummy lock for Author2 on page /admin/pages/typography
        $this->set_LOCKFILE([
            '/admin/pages/typography' => [
                'email' => 'author2@domain.com',
                'fullname' => 'McAuthor 2',
                'timestamp' => time(),
            ]
        ]);

        $I->amOnPage('/admin');

        $this->loginAuthor1($I);
        $I->waitForText('You have been successfully logged in');

        $I->amOnPage('/admin/pages/home');
        $I->see('Home');

        $locks = $this->get_LOCKFILE();

        assertCount(2, $locks);
        assertArrayHasKey('/admin/pages/home', $locks);
        assertArrayHasKey('/admin/pages/typography', $locks);
    }

    public function denyLock(AcceptanceTester $I): void
    {
        // Add dummy lock for Author2 on page /admin/pages/typography
        $this->set_LOCKFILE([
            '/admin/pages/typography' => [
                'email' => 'author2@domain.com',
                'fullname' => 'McAuthor 2',
                'timestamp' => time(),
            ]
        ]);

        $I->amOnPage('/admin');

        $this->loginAuthor1($I);
        $I->waitForText('You have been successfully logged in');

        $I->amOnPage('/admin/pages/typography');
        $I->see('Typography');
        // Check if Lock error alert is shown
        $I->seeElement('.error.alert.pagelocks');
        $I->see('author2@domain.com is currently editing this page.');

        $locks = $this->get_LOCKFILE();

        assertCount(1, $locks);
        assertArrayHasKey('/admin/pages/typography', $locks);
    }

    public function checkLocksAfterPageSwitch(AcceptanceTester $I): void
    {
        $I->amOnPage('/admin');

        $this->loginAuthor1($I);
        $I->waitForText('You have been successfully logged in');

        $I->amOnPage('/admin/pages/home');
        $I->see('Home');

        $locks = $this->get_LOCKFILE();

        assertCount(1, $locks);
        assertArrayHasKey('/admin/pages/home', $locks);

        // Switch to Typography

        $I->amOnPage('/admin/pages/typography');
        $I->see('Typography');

        $locks = $this->get_LOCKFILE();

        assertCount(1, $locks);
        assertArrayHasKey('/admin/pages/typography', $locks);
    }

    public function clearExpiredLocks(AcceptanceTester $I): void
    {
        // Add dummy lock for Author2 on page /admin/pages/typography
        $this->set_LOCKFILE([
            '/admin/pages/dummy1' => [
                'email' => 'dummy1@domain.com',
                'fullname' => 'Dummy 1',
                'timestamp' => 0,     // Timestamp of 1970-01-01
            ],
            '/admin/pages/dummy2' => [
                'email' => 'dummy2@domain.com',
                'fullname' => 'Dummy 2',
                'timestamp' => 0,    // Timestamp of 1970-01-01
            ]
        ]);

        $locks = $this->get_LOCKFILE();
        assertCount(2, $locks);

        $I->amOnPage('/admin');

        $this->loginAuthor1($I);
        $I->waitForText('You have been successfully logged in');

        $I->amOnPage('/admin/pages/home');
        $I->see('Home');

        $locks = $this->get_LOCKFILE();
        assertCount(1, $locks);
    }

    public function keepAlive(AcceptanceTester $I): void
    {
        $this->setConfig('keepAliveInterval', 1);

        $I->amOnPage('/admin');

        $this->loginAuthor1($I);
        $I->waitForText('You have been successfully logged in');

        $I->amOnPage('/admin/pages/home');
        $I->see('Home');

        $I->wait(5);

        $log = $this->logFile->content();
        $extendsFound = preg_match_all('/Lock extended: Held by author1@domain.com/', $log);

        assertGreaterThan(3, $extendsFound);
    }

    public function forceRemoveLock(AcceptanceTester $I): void
    {
        // Add dummy lock for Author2 on page /admin/pages/typography
        $this->set_LOCKFILE([
            '/admin/pages/typography' => [
                'email' => 'author2@domain.com',
                'fullname' => 'McAuthor 2',
                'timestamp' => time(),
            ]
        ]);

        $I->amOnPage('/admin');

        $this->loginAuthor1($I);
        $I->waitForText('You have been successfully logged in');

        $I->amOnPage('/admin/locks');
        $I->see('Locks');

        $I->seeElement('.page-delete.delete-action');
        $I->click('.page-delete.delete-action');

        $I->acceptPopup();

        $I->waitForText('Lock has been removed successfully.');
        $I->see('Lock has been removed successfully.');

        $I->waitForText('Found 0 lock(s)');
        $I->see('Found 0 lock(s)');

        $locks = $this->get_LOCKFILE();
        assertCount(0, $locks);
        assertArrayNotHasKey('/admin/pages/typography', $locks);
    }

    public function checkInactiveUserRegainingLock(AcceptanceTester $I): void
    {
        $this->setConfig('keepAliveInterval', 1);

        $I->amOnPage('/admin');

        $this->loginAuthor1($I);
        $I->waitForText('You have been successfully logged in');

        $I->amOnPage('/admin/pages/home');
        $I->see('Home');

        $locks = $this->get_LOCKFILE();

        assertCount(1, $locks);
        assertArrayHasKey('/admin/pages/home', $locks);

        // Remove lock as if it has expired
        $this->set_LOCKFILE([]);

        $I->wait(3);

        $locks = $this->get_LOCKFILE();

        assertCount(1, $locks);
        assertArrayHasKey('/admin/pages/home', $locks);
    }

    public function checkInactiveUserLoosingLock(AcceptanceTester $I): void
    {
        $this->setConfig('keepAliveInterval', 2);

        $I->amOnPage('/admin');

        $this->loginAuthor1($I);
        $I->waitForText('You have been successfully logged in');

        $I->amOnPage('/admin/pages/home');
        $I->see('Home');

        $locks = $this->get_LOCKFILE();

        assertCount(1, $locks);
        assertArrayHasKey('/admin/pages/home', $locks);

        // Remove lock as if it has expired and touch the page as if edited
        $this->set_LOCKFILE([]);
        touch('/www/grav/site-dev/user/pages/01.home/default.md', time());

        $I->wait(3);

        $locks = $this->get_LOCKFILE();

        assertCount(0, $locks);
        assertArrayNotHasKey('/admin/pages/home', $locks);

        $I->see('Lock on page has expired or has been removed.');
    }

    private function loginAuthor1(AcceptanceTester $I): void
    {
        $I->fillField('data[username]', 'author1');
        $I->fillField('data[password]', 'Page@Locks0');

        $I->click('.button.primary');
    }

    private function set_LOCKFILE(array $locks): void
    {
        $this->lockFile->save($locks);
        $this->lockFile->unlock();
        $this->lockFile->free();
    }

    private function get_LOCKFILE(): array
    {
        $locks = $this->lockFile->content();
        $this->lockFile->unlock();
        $this->lockFile->free();

        return $locks;
    }

    protected function setConfigDefaults(): void
    {
        $this->configFile->save($this->configDefaults);
        $this->configFile->free();
    }

    /**
     * @param string $key
     * @param int|bool $value
     */
    protected function setConfig(string $key, $value): void
    {
        $defaults = $this->configDefaults;
        $defaults[$key] = $value;

        $this->configFile->save($defaults);
        $this->configFile->free();
    }
}
