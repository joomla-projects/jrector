<?php

namespace Joomla\Rector\Tests\Joomla7\GetInstanceToContainerRector\Fixture;

use Joomla\CMS\Cache\Cache;
use Joomla\CMS\Cache\CacheController;

class CacheUser
{
    public function run(array $options)
    {
        $cache    = Cache::getInstance('output', $options);
        $callback = CacheController::getInstance('callback', ['defaultgroup' => 'com_example']);
        $default  = CacheController::getInstance();

        return JCache::getInstance('page');
    }
}

?>
-----
<?php

namespace Joomla\Rector\Tests\Joomla7\GetInstanceToContainerRector\Fixture;

use Joomla\CMS\Cache\Cache;
use Joomla\CMS\Cache\CacheController;

class CacheUser
{
    public function run(array $options)
    {
        $cache    = \Joomla\CMS\Factory::getContainer()->get(\Joomla\CMS\Cache\CacheControllerFactoryInterface::class)->createCacheController('output', $options);
        $callback = \Joomla\CMS\Factory::getContainer()->get(\Joomla\CMS\Cache\CacheControllerFactoryInterface::class)->createCacheController('callback', ['defaultgroup' => 'com_example']);
        $default  = \Joomla\CMS\Factory::getContainer()->get(\Joomla\CMS\Cache\CacheControllerFactoryInterface::class)->createCacheController();

        return \Joomla\CMS\Factory::getContainer()->get(\Joomla\CMS\Cache\CacheControllerFactoryInterface::class)->createCacheController('page');
    }
}

?>
