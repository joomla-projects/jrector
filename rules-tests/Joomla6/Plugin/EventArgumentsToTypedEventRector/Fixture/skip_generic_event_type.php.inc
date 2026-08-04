<?php

namespace Joomla\Rector\Tests\Joomla6\Plugin\EventArgumentsToTypedEventRector\Fixture;

use Joomla\Event\Event;

// A generic Event carries no argument list, so no getter can be resolved.
class PlgContentGenericEvent
{
    public function onContentPrepare(Event $event)
    {
        [$context, $item, $params, $page] = array_values($event->getArguments());

        $subject = $event->getArgument('subject');

        $item->text .= $context . $subject;
    }
}
