<?php

namespace Joomla\Rector\Tests\Joomla6\Plugin\EventArgumentsToTypedEventRector\Fixture;

use Joomla\Rector\Tests\Joomla6\Plugin\EventArgumentsToTypedEventRector\Source\CustomEvent;
use Joomla\Rector\Tests\Joomla6\Plugin\EventArgumentsToTypedEventRector\Source\ExtendedCustomEvent;

class PlgContentConfiguredEvent
{
    public function onCustom(CustomEvent $event)
    {
        [$context, $item] = array_values($event->getArguments());

        return $context . $item->id;
    }

    public function onExtendedCustom(ExtendedCustomEvent $event)
    {
        $context = $event->getArgument('context');

        return $context;
    }
}

?>
-----
<?php

namespace Joomla\Rector\Tests\Joomla6\Plugin\EventArgumentsToTypedEventRector\Fixture;

use Joomla\Rector\Tests\Joomla6\Plugin\EventArgumentsToTypedEventRector\Source\CustomEvent;
use Joomla\Rector\Tests\Joomla6\Plugin\EventArgumentsToTypedEventRector\Source\ExtendedCustomEvent;

class PlgContentConfiguredEvent
{
    public function onCustom(CustomEvent $event)
    {
        $context = $event->getContext();
        $item = $event->getItem();

        return $context . $item->id;
    }

    public function onExtendedCustom(ExtendedCustomEvent $event)
    {
        $context = $event->getContext();

        return $context;
    }
}

?>
