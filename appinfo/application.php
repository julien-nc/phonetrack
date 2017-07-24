<?php
/**
 * ownCloud - gpsphonetracking
 *
 *
 * @author
 *
 * @copyright
 */

namespace OCA\GpsPhoneTracking\AppInfo;



use OCP\IContainer;

use OCP\AppFramework\App;
use OCP\AppFramework\IAppContainer;

use OCA\GpsPhoneTracking\Controller\PageController;

/**
 * Class Application
 *
 * @package OCA\GpsPhoneTracking\AppInfo
 */
class Application extends App {

    /**
     * Constructor
     *
     * @param array $urlParams
     */
    public function __construct(array $urlParams = []) {
        parent::__construct('gpsphonetracking', $urlParams);

        $container = $this->getContainer();

        /**
         * Controllers
         */
        $container->registerService(
            'PageController', function (IAppContainer $c) {
                return new PageController(
                    $c->query('AppName'),
                    $c->query('Request'),
                    $c->query('UserId'),
                    $c->query('ServerContainer')->getUserFolder($c->query('UserId')),
                    $c->query('ServerContainer')->getConfig(),
                    $c->getServer()->getShareManager(),
                    $c->getServer()->getAppManager()
                );
            }
        );
    }

}

