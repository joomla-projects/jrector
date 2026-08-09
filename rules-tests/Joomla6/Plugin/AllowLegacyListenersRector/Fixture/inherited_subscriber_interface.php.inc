<?php

namespace Joomla\Rector\Tests\Joomla6\Plugin\AllowLegacyListenersRector\Fixture;

use Joomla\CMS\Plugin\CMSPlugin;
use Joomla\Event\SubscriberInterface;

abstract class AbstractBasePlugin extends CMSPlugin implements SubscriberInterface
{
}

class PlgContentInherited extends AbstractBasePlugin
{
    protected $allowLegacyListeners = true;

    public static function getSubscribedEvents(): array
    {
        return [];
    }
}

?>
-----
<?php

namespace Joomla\Rector\Tests\Joomla6\Plugin\AllowLegacyListenersRector\Fixture;

use Joomla\CMS\Plugin\CMSPlugin;
use Joomla\Event\SubscriberInterface;

abstract class AbstractBasePlugin extends CMSPlugin implements SubscriberInterface
{
}

class PlgContentInherited extends AbstractBasePlugin
{
    public static function getSubscribedEvents(): array
    {
        return [];
    }
}

?>
