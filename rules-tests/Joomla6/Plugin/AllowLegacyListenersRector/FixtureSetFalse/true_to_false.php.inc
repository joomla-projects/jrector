<?php

namespace Joomla\Rector\Tests\Joomla6\Plugin\AllowLegacyListenersRector\FixtureSetFalse;

use Joomla\CMS\Plugin\CMSPlugin;
use Joomla\Event\SubscriberInterface;

class PlgContentToggle extends CMSPlugin implements SubscriberInterface
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

namespace Joomla\Rector\Tests\Joomla6\Plugin\AllowLegacyListenersRector\FixtureSetFalse;

use Joomla\CMS\Plugin\CMSPlugin;
use Joomla\Event\SubscriberInterface;

class PlgContentToggle extends CMSPlugin implements SubscriberInterface
{
    protected $allowLegacyListeners = false;

    public static function getSubscribedEvents(): array
    {
        return [];
    }
}

?>
