<?php

namespace Joomla\Rector\Tests\Joomla6\Plugin\EventArgumentsToTypedEventRector\Fixture;

use Joomla\CMS\Event\Content\ContentPrepareEvent;

class PlgContentArrayAccess
{
    public function onContentPrepare(ContentPrepareEvent $event)
    {
        $context = $event['context'];
        $item    = $event['subject'];

        $item->text .= $context . $event['params']->get('foo');
    }
}

?>
-----
<?php

namespace Joomla\Rector\Tests\Joomla6\Plugin\EventArgumentsToTypedEventRector\Fixture;

use Joomla\CMS\Event\Content\ContentPrepareEvent;

class PlgContentArrayAccess
{
    public function onContentPrepare(ContentPrepareEvent $event)
    {
        $context = $event->getContext();
        $item    = $event->getItem();

        $item->text .= $context . $event->getParams()->get('foo');
    }
}

?>
