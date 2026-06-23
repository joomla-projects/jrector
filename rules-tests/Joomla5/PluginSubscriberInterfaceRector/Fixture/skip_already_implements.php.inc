<?php

use Joomla\CMS\Plugin\CMSPlugin;
use Joomla\Event\SubscriberInterface;

class PlgContentExample extends CMSPlugin implements SubscriberInterface
{
    public static function getSubscribedEvents(): array
    {
        return ['onContentPrepare' => 'onContentPrepare'];
    }

    public function onContentPrepare(): void
    {
    }
}