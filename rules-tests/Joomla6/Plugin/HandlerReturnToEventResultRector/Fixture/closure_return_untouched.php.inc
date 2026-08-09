<?php

namespace Joomla\Rector\Tests\Joomla6\Plugin\HandlerReturnToEventResultRector\Fixture;

use Joomla\CMS\Event\Model\BeforeSaveEvent;
use Joomla\CMS\Plugin\CMSPlugin;

class PlgContentClosure extends CMSPlugin
{
    public function onContentBeforeSave(BeforeSaveEvent $event)
    {
        $filter = static function ($item) {
            return $item !== null;
        };

        return $filter($event->getItem());
    }
}

?>
-----
<?php

namespace Joomla\Rector\Tests\Joomla6\Plugin\HandlerReturnToEventResultRector\Fixture;

use Joomla\CMS\Event\Model\BeforeSaveEvent;
use Joomla\CMS\Plugin\CMSPlugin;

class PlgContentClosure extends CMSPlugin
{
    public function onContentBeforeSave(BeforeSaveEvent $event): void
    {
        $filter = static function ($item) {
            return $item !== null;
        };

        $event->addResult($filter($event->getItem()));
    }
}

?>
