<?php

namespace Joomla\Rector\Tests\Joomla7\GetInstanceToContainerRector\Fixture;

use Acme\Session;
use Joomla\CMS\Cache\CacheController;
use Joomla\CMS\Router\Router;
use Joomla\CMS\Table\Table;

// Other getInstance() methods, a class of the same short name in another namespace,
// and addIncludePath(), which has no replacement.
class UnrelatedService
{
    public function run()
    {
        $table  = Table::getInstance('Content');
        $router = Router::getInstance('site');
        $own    = Session::getInstance();

        CacheController::addIncludePath(__DIR__);
    }
}
