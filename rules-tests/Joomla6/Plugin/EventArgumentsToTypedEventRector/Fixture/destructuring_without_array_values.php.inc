<?php

namespace Joomla\Rector\Tests\Joomla6\Plugin\EventArgumentsToTypedEventRector\Fixture;

use Joomla\CMS\Event\Finder\AfterDeleteEvent;

class PlgFinderExample
{
    public function onFinderAfterDelete(AfterDeleteEvent $event)
    {
        [$context, $table] = $event->getArguments();

        return $context . $table->id;
    }
}

?>
-----
<?php

namespace Joomla\Rector\Tests\Joomla6\Plugin\EventArgumentsToTypedEventRector\Fixture;

use Joomla\CMS\Event\Finder\AfterDeleteEvent;

class PlgFinderExample
{
    public function onFinderAfterDelete(AfterDeleteEvent $event)
    {
        $context = $event->getContext();
        $table = $event->getItem();

        return $context . $table->id;
    }
}

?>
