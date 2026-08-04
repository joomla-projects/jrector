<?php

namespace Joomla\Rector\Tests\Joomla6\Plugin\EventArgumentsToTypedEventRector\Fixture;

use Joomla\CMS\Event\Content\ContentPrepareEvent;
use Joomla\Event\Event;

// The closure parameter shadows the handler parameter, so inside the closure body
// $event is a different, untyped event object.
class PlgContentShadowedByClosure
{
    public function onContentPrepare(ContentPrepareEvent $event)
    {
        $callback = function (Event $event) {
            return $event['context'] . $event->getArgument('subject');
        };

        return $callback;
    }
}
