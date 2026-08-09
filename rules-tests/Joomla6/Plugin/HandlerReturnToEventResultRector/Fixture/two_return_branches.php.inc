<?php

namespace Joomla\Rector\Tests\Joomla6\Plugin\HandlerReturnToEventResultRector\Fixture;

use Joomla\CMS\Event\Model\BeforeSaveEvent;
use Joomla\CMS\Plugin\CMSPlugin;

class PlgContentTwoBranches extends CMSPlugin
{
    public function onContentBeforeSave(BeforeSaveEvent $event)
    {
        $context = $event->getContext();

        if ($context !== 'com_content.article') {
            return false;
        }

        return true;
    }
}

?>
-----
<?php

namespace Joomla\Rector\Tests\Joomla6\Plugin\HandlerReturnToEventResultRector\Fixture;

use Joomla\CMS\Event\Model\BeforeSaveEvent;
use Joomla\CMS\Plugin\CMSPlugin;

class PlgContentTwoBranches extends CMSPlugin
{
    public function onContentBeforeSave(BeforeSaveEvent $event): void
    {
        $context = $event->getContext();

        if ($context !== 'com_content.article') {
            $event->addResult(false);
            return;
        }

        $event->addResult(true);
    }
}

?>
