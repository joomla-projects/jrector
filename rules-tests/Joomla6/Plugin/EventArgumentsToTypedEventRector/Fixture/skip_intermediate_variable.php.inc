<?php

namespace Joomla\Rector\Tests\Joomla6\Plugin\EventArgumentsToTypedEventRector\Fixture;

use Joomla\CMS\Event\Content\ContentPrepareEvent;

// Indexed access through an intermediate variable is out of scope.
class PlgContentIntermediateVariable
{
    public function onContentPrepare(ContentPrepareEvent $event)
    {
        $args    = $event->getArguments();
        $context = $args[0];
        $item    = $args[1];

        $item->text .= $context;
    }
}
