<?php

namespace Joomla\Rector\Tests\Joomla6\Plugin\EventArgumentsToTypedEventRector\Fixture;

use Joomla\CMS\Event\Content\ContentPrepareEvent;
use Joomla\CMS\Event\Finder\AfterDeleteEvent;
use Joomla\CMS\Event\Model\AfterSaveEvent;

class PlgContentMultipleHandlers
{
    public function onContentPrepare(ContentPrepareEvent $event)
    {
        [$context, $item] = array_values($event->getArguments());

        $item->text .= $context;
    }

    public function onFinderAfterDelete(AfterDeleteEvent $event)
    {
        $context = $event->getArgument('context');

        return $context;
    }

    public function onContentAfterSave(AfterSaveEvent $event)
    {
        $item = $event['subject'];

        return $item->id;
    }
}

?>
-----
<?php

namespace Joomla\Rector\Tests\Joomla6\Plugin\EventArgumentsToTypedEventRector\Fixture;

use Joomla\CMS\Event\Content\ContentPrepareEvent;
use Joomla\CMS\Event\Finder\AfterDeleteEvent;
use Joomla\CMS\Event\Model\AfterSaveEvent;

class PlgContentMultipleHandlers
{
    public function onContentPrepare(ContentPrepareEvent $event)
    {
        $context = $event->getContext();
        $item = $event->getItem();

        $item->text .= $context;
    }

    public function onFinderAfterDelete(AfterDeleteEvent $event)
    {
        $context = $event->getContext();

        return $context;
    }

    public function onContentAfterSave(AfterSaveEvent $event)
    {
        $item = $event->getItem();

        return $item->id;
    }
}

?>
