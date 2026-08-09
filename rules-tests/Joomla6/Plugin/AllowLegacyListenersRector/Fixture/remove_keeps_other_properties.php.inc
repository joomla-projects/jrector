<?php

namespace Joomla\Rector\Tests\Joomla6\Plugin\AllowLegacyListenersRector\Fixture;

use Joomla\CMS\Plugin\CMSPlugin;
use Joomla\Event\SubscriberInterface;

class PlgContentGrouped extends CMSPlugin implements SubscriberInterface
{
    protected $autoloadLanguage = true;

    protected $allowLegacyListeners = false;

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

class PlgContentGrouped extends CMSPlugin implements SubscriberInterface
{
    protected $autoloadLanguage = true;

    public static function getSubscribedEvents(): array
    {
        return [];
    }
}

?>
