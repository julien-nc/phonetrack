<?php
/**
 * Nextcloud - phonetrack
 *
 * This file is licensed under the Affero General Public License version 3 or
 * later. See the COPYING file.
 *
 * @author Julien Veyssier <eneiluj@posteo.net>
 * @copyright Julien Veyssier 2017
 */

namespace OCA\PhoneTrack\AppInfo;

use OCP\IContainer;

use OCP\AppFramework\App;
use OCP\AppFramework\IAppContainer;

use OCA\PhoneTrack\Controller\PageController;
use OCA\PhoneTrack\Controller\LogController;
use OCA\PhoneTrack\Controller\UtilsController;

/**
 * Class Application
 *
 * @package OCA\PhoneTrack\AppInfo
 */
class Application extends App {

    /**
     * Constructor
     *
     * @param array $urlParams
     */
    public function __construct(array $urlParams = []) {
        parent::__construct('phonetrack', $urlParams);

        $container = $this->getContainer();
    }

}

