<?php
namespace OCA\Contacts\Cron;

use \OCA\Contacts\AppInfo\Application;
use \OCA\Contacts\Service\SocialUpdateService;

class SocialUpdateTest extends \OC\BackgroundJob\TimedJob {

    /** @var SocialApiController */
    private $social;

    public function __construct(ITimeFactory $time, SocialUpdateService $social) {
        parent::__construct($time);
        $this->social = $social;

        // Run once an hour
        // parent::setInterval(3600);

        // Run every 5 minutes
        parent::setInterval(5 * 60);
    }

    protected function run($arguments) {
        $this->social->doCronNotify();
    }

}

