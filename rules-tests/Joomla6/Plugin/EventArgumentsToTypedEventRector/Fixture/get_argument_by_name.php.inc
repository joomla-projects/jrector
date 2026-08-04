<?php

namespace Joomla\Rector\Tests\Joomla6\Plugin\EventArgumentsToTypedEventRector\Fixture;

use Joomla\CMS\Event\Content\ContentPrepareEvent;

class PlgContentGetArgument
{
    public function onContentPrepare(ContentPrepareEvent $event)
    {
        $context = $event->getArgument('context');
        $item    = $event->getArgument('subject');

        if ($event->getArgument('page') === null) {
            return;
        }

        $item->text .= $context;
    }
}

?>
-----
<?php

namespace Joomla\Rector\Tests\Joomla6\Plugin\EventArgumentsToTypedEventRector\Fixture;

use Joomla\CMS\Event\Content\ContentPrepareEvent;

class PlgContentGetArgument
{
    public function onContentPrepare(ContentPrepareEvent $event)
    {
        $context = $event->getContext();
        $item    = $event->getItem();

        if ($event->getPage() === null) {
            return;
        }

        $item->text .= $context;
    }
}

?>
