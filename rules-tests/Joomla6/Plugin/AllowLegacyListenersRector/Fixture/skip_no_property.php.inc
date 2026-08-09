<?php

namespace Joomla\Rector\Tests\Joomla6\Plugin\AllowLegacyListenersRector\Fixture;

use Joomla\CMS\Plugin\CMSPlugin;
use Joomla\Event\SubscriberInterface;

// Idempotence: nothing to remove.
class PlgContentAlreadyClean extends CMSPlugin implements SubscriberInterface
{
    protected $autoloadLanguage = true;

    public static function getSubscribedEvents(): array
    {
        return [];
    }
}
