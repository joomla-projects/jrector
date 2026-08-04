<?php

namespace Joomla\Rector\Tests\Joomla6\Plugin\EventArgumentsToTypedEventRector\Fixture;

use Joomla\CMS\Event\Content\ContentPrepareEvent;
use Joomla\CMS\Event\Finder\AfterDeleteEvent;

class PlgContentUnresolvable
{
    // More destructuring targets than the event has known arguments.
    public function tooManyTargets(AfterDeleteEvent $event)
    {
        [$context, $item, $extra] = array_values($event->getArguments());

        return $context . $extra;
    }

    // By-reference destructuring does not survive the rewrite to getters.
    public function byRefTarget(ContentPrepareEvent $event)
    {
        [$context, &$item] = array_values($event->getArguments());

        $item = 'changed';
    }

    // A hole skips a position, so the remaining targets no longer line up.
    public function withHole(ContentPrepareEvent $event)
    {
        [$context, , $params] = array_values($event->getArguments());

        return $context . $params;
    }

    // A dynamic argument name cannot be mapped to a getter.
    public function dynamicName(ContentPrepareEvent $event, string $name)
    {
        return $event->getArgument($name) . $event[$name];
    }

    // A default value has no getter equivalent.
    public function withDefault(ContentPrepareEvent $event)
    {
        return $event->getArgument('page', 1);
    }

    // Writing, unsetting and testing an argument is not a read access.
    public function writeAccess(ContentPrepareEvent $event)
    {
        $event['params'] = null;

        if (isset($event['page'])) {
            unset($event['page']);
        }

        return empty($event['context']);
    }

    // The event variable is reassigned, so later accesses may not be the event any more.
    public function reassigned(ContentPrepareEvent $event)
    {
        $context = $event['context'];

        $event = null;

        return $context;
    }

    // The result argument belongs to the event result handling, not here.
    public function resultArgument(AfterDeleteEvent $event)
    {
        return $event->getArgument('result');
    }
}
