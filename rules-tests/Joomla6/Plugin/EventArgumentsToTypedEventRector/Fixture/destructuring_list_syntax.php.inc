<?php

namespace Joomla\Rector\Tests\Joomla6\Plugin\EventArgumentsToTypedEventRector\Fixture;

use Joomla\CMS\Event\Content\ContentPrepareEvent;

class PlgContentListSyntax
{
    public function onContentPrepare(ContentPrepareEvent $event)
    {
        // Read the article and its context.
        list($context, $item) = array_values($event->getArguments());

        $item->text .= $context;
    }
}

?>
-----
<?php

namespace Joomla\Rector\Tests\Joomla6\Plugin\EventArgumentsToTypedEventRector\Fixture;

use Joomla\CMS\Event\Content\ContentPrepareEvent;

class PlgContentListSyntax
{
    public function onContentPrepare(ContentPrepareEvent $event)
    {
        // Read the article and its context.
        $context = $event->getContext();
        $item = $event->getItem();

        $item->text .= $context;
    }
}

?>
