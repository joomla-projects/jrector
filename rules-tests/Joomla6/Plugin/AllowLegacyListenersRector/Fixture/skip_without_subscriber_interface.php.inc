<?php

namespace Joomla\Rector\Tests\Joomla6\Plugin\AllowLegacyListenersRector\Fixture;

use Joomla\CMS\Plugin\CMSPlugin;

// Without SubscriberInterface the property is still read by CMSPlugin::registerListeners().
class PlgContentNoSubscriber extends CMSPlugin
{
    protected $allowLegacyListeners = true;
}
