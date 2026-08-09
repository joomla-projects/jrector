<?php

namespace Joomla\Rector\Tests\Joomla6\Plugin\HandlerReturnToEventResultRector\Fixture;

use Joomla\CMS\Event\Model\BeforeSaveEvent;

// Does not extend CMSPlugin.
class SomeListener
{
    public function onContentBeforeSave(BeforeSaveEvent $event)
    {
        return true;
    }
}
