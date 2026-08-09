<?php

namespace Joomla\Rector\Tests\Joomla6\Plugin\AllowLegacyListenersRector\Fixture;

use Joomla\Event\SubscriberInterface;

// Not a CMSPlugin subclass.
class SomeService implements SubscriberInterface
{
    protected $allowLegacyListeners = true;

    public static function getSubscribedEvents(): array
    {
        return [];
    }
}
