<?php

namespace Joomla\Rector\Tests\Joomla6\Plugin\EventArgumentsToTypedEventRector\Fixture;

use Joomla\CMS\Event\Content\ContentPrepareEvent;

class PlgContentExample
{
    public function myOnContentPrepare(ContentPrepareEvent $event)
    {
        [$context, $item, $params, $page] = array_values($event->getArguments());

        if ($context !== 'com_content.article') {
            return;
        }

        $item->text .= 'foo';
    }
}

?>
-----
<?php

namespace Joomla\Rector\Tests\Joomla6\Plugin\EventArgumentsToTypedEventRector\Fixture;

use Joomla\CMS\Event\Content\ContentPrepareEvent;

class PlgContentExample
{
    public function myOnContentPrepare(ContentPrepareEvent $event)
    {
        $context = $event->getContext();
        $item = $event->getItem();
        $params = $event->getParams();
        $page = $event->getPage();

        if ($context !== 'com_content.article') {
            return;
        }

        $item->text .= 'foo';
    }
}

?>
