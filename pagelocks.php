<?php

namespace Grav\Plugin;

use Composer\Autoload\ClassLoader;
use Exception;
use Grav\Common\Assets;
use Grav\Common\Grav;
use Grav\Common\Plugin;
use Grav\Plugin\PageLocks\Data\FilteredPost;
use Grav\Plugin\PageLocks\Data\Lock;
use Grav\Plugin\PageLocks\Data\Locks;
use Grav\Plugin\PageLocks\LockHandler;
use Grav\Plugin\PageLocks\PageHandler;
use Grav\Plugin\PageLocks\Storage;

/**
 * Class PageLocksPlugin
 * @package Grav\Plugin
 */
class PageLocksPlugin extends Plugin
{
    protected string $route = 'locks';

    /**
     * @return array
     *
     * The getSubscribedEvents() gives the core a list of events
     *     that the plugin wants to listen to. The key of each
     *     array section is the event that the plugin listens to
     *     and the value (in the form of an array) contains the
     *     callable (or function) as well as the priority. The
     *     higher the number the higher the priority.
     */
    public static function getSubscribedEvents(): array
    {
        return [
            'onPluginsInitialized' => [
                // Uncomment following line when plugin requires Grav < 1.7
                // ['autoload', 100000],
                ['onPluginsInitialized', 0]
            ]
        ];
    }

    /**
     * Composer autoload
     *
     * @return ClassLoader
     */
    public function autoload(): ClassLoader
    {
        return require __DIR__ . '/vendor/autoload.php';
    }

    /**
     * Initialize the plugin
     */
    public function onPluginsInitialized(): void
    {
        if ($this->isAdmin()) {
            /** @var LockHandler */
            $lockHandler = new lockHandler();
            $response = $lockHandler->handleRequest();

            if ($response) {
                // if there was an async PageLocks request, return response and stop processing.
                echo json_encode($response);
                die();
            }

            $this->enable([
                // Put your main events here
                'onAssetsInitialized' => ['onAssetsInitialized', 0],
                'onAdminMenu' => ['onAdminMenu', 0],
                'onTwigTemplatePaths' => ['onTwigTemplatePaths', 0],
            ]);

            return;
        }

        // Enable the main events we are interested in
        $this->enable([
            // Put your main events here
        ]);
    }

    /**
     * Add assets required for page
     */
    public function onAssetsInitialized(): void
    {
        if (!$this->config) {
            throw new Exception('Property "$this->config" should not be null.');
        }

        // Should minified assets be used?
        $min = $this->config->get('plugins.pagelocks.productionMode', true) ? '.min' : '';

        /** @var Assets */
        $assets = $this->grav['assets'];

        // Add script for all Admin pages. Must at least check on which page user is.

        /** @psalm-suppress TooManyArguments */
        $assets->addJs("plugin://pagelocks/js/pagelocker$min.js", ['type' => 'module']);

        $keepAliveInterval = $this->config->get('plugins.pagelocks.keepAliveInterval', 60);

        $assets->addInlineJs(
            "
            const pagelocksConfig = {
                keepAliveInterval: $keepAliveInterval,
            };
            "
        );
        $assets->addCss("plugin://pagelocks/css/pagelocker$min.css");

        // Add scripts required for Admin page of PageLocks
        // ends with $this->config['plugins']['admin']['route']/locks

        $route = $this->grav['uri']->uri();
        $pagelocksadmin = $this->config->get('plugins.admin.route', '/admin') . "/locks";

        if (strpos($route, $pagelocksadmin) !== false) {
            /** @psalm-suppress TooManyArguments */
            $assets->addJs("plugin://pagelocks/js/pagelocks-admin$min.js", ['type' => 'module']);
            $assets->addCss("plugin://pagelocks/css/pagelocks-admin$min.css");
        }
    }

    /**
     * Add navigation item to the admin plugin
     */
    public function onAdminMenu(): void
    {
        $this->grav['twig']->plugins_hooked_nav['PLUGIN_PAGELOCKS.LOCKS'] = [
            'route' => $this->route,
            'icon' => 'fa-lock'
        ];
    }

    /**
     * Add plugin templates path
     */
    public function onTwigTemplatePaths(): void
    {
        $this->grav['twig']->twig_paths[] = __DIR__ . '/admin/templates';
    }
}
