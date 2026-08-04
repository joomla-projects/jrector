<?php

namespace Joomla\Rector\Tests\Joomla6\Plugin\EventArgumentsToTypedEventRector\Fixture;

use Joomla\CMS\Event\Content\ContentPrepareEvent;

class PlgContentAlreadyConverted
{
    public function onContentPrepare(ContentPrepareEvent $event)
    {
        $context = $event->getContext();
        $item    = $event->getItem();
        $params  = $event->getParams();
        $page    = $event->getPage();

        if ($context !== 'com_content.article') {
            return;
        }

        $item->text .= $params->get('foo') . $page;
    }
}
