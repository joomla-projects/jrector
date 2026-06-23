<?php

use Joomla\CMS\Plugin\CMSPlugin;

class PlgContentExample extends CMSPlugin
{
    public function onContentPrepare(): void
    {
    }

    public function onUserLogin(): bool
    {
        return true;
    }
}
-----
<?php

use Joomla\CMS\Plugin\CMSPlugin;

class PlgContentExample extends CMSPlugin implements \Joomla\Event\SubscriberInterface
{
    public function onContentPrepare(): void
    {
    }

    public function onUserLogin(): bool
    {
        return true;
    }
    public static function getSubscribedEvents(): array
    {
        return ['onContentPrepare' => 'onContentPrepare', 'onUserLogin' => 'onUserLogin'];
    }
}