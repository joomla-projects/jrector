<?php

namespace Joomla\Rector\Tests\Joomla6\Plugin\HandlerReturnToEventResultRector\Fixture;

use Joomla\CMS\Event\Content\ContentPrepareEvent;
use Joomla\CMS\Event\Model\BeforeSaveEvent;
use Joomla\CMS\Plugin\CMSPlugin;

class PlgContentSkips extends CMSPlugin
{
    // Idempotence: already reports through the event.
    public function onContentBeforeSave(BeforeSaveEvent $event)
    {
        $event->addResult(true);
    }

    // A valueless return stays as it is.
    public function onContentAfterSave(BeforeSaveEvent $event)
    {
        if ($event->getContext() === '') {
            return;
        }
    }

    // ContentPrepareEvent is not result aware, so addResult() does not exist on it.
    public function onContentPrepare(ContentPrepareEvent $event)
    {
        return $event->getContext();
    }

    // No event parameter at all.
    public function onSomethingElse($context)
    {
        return $context;
    }

    // Not an event handler: no on-prefix and no subscriber entry.
    public function helper(BeforeSaveEvent $event)
    {
        return $event->getContext();
    }
}
