<?php

namespace Joomla\Rector\Tests\Joomla7\GetInstanceToContainerRector\Fixture;

use Joomla\CMS\Cache\Cache;
use Joomla\CMS\Plugin\CMSPlugin;
use Joomla\CMS\Session\Session;

class PlgSystemExample extends CMSPlugin
{
    public function onAfterRoute(): void
    {
        $token = Session::getInstance()->getFormToken();
        $cache = Cache::getInstance('output');
    }

    public static function helper()
    {
        return Session::getInstance();
    }
}

?>
-----
<?php

namespace Joomla\Rector\Tests\Joomla7\GetInstanceToContainerRector\Fixture;

use Joomla\CMS\Cache\Cache;
use Joomla\CMS\Plugin\CMSPlugin;
use Joomla\CMS\Session\Session;

class PlgSystemExample extends CMSPlugin
{
    public function onAfterRoute(): void
    {
        $token = $this->getApplication()->getSession()->getFormToken();
        $cache = \Joomla\CMS\Factory::getContainer()->get(\Joomla\CMS\Cache\CacheControllerFactoryInterface::class)->createCacheController('output');
    }

    public static function helper()
    {
        return \Joomla\CMS\Factory::getApplication()->getSession();
    }
}

?>
