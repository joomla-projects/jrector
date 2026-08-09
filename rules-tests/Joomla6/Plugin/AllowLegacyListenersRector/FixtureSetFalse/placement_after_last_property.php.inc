<?php

namespace Joomla\Rector\Tests\Joomla6\Plugin\AllowLegacyListenersRector\FixtureSetFalse;

use Joomla\CMS\Plugin\CMSPlugin;
use Joomla\Event\SubscriberInterface;

class PlgContentPlacement extends CMSPlugin implements SubscriberInterface
{
    protected $autoloadLanguage = true;

    protected $foo = 'bar';

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

class PlgContentPlacement extends CMSPlugin implements SubscriberInterface
{
    protected $autoloadLanguage = true;

    protected $foo = 'bar';
    protected $allowLegacyListeners = false;

    public static function getSubscribedEvents(): array
    {
        return [];
    }
}

?>
