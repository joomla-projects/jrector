<?php

namespace Joomla\Rector\Tests\Joomla6\Plugin\EventArgumentsToTypedEventRector\Fixture;

use Joomla\CMS\Event\Content\ContentPrepareEvent;

// Numeric argument access is deprecated in Joomla and does not identify an argument
// reliably, see AbstractEvent::getArgument().
class PlgContentNumericIndex
{
    public function onContentPrepare(ContentPrepareEvent $event)
    {
        $context = $event->getArgument(0);
        $item    = $event->getArgument('1');
        $params  = $event[2];

        $item->text .= $context . $params;
    }
}
