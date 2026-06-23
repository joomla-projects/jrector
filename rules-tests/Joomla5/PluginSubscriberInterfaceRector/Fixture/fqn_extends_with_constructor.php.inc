<?php

class PlgSystemExample extends \Joomla\CMS\Plugin\CMSPlugin
{
    public function __construct(&$subject, array $config = [])
    {
        parent::__construct($subject, $config);
    }

    public function onAfterInitialise(): void
    {
    }
}
-----
<?php

class PlgSystemExample extends \Joomla\CMS\Plugin\CMSPlugin implements \Joomla\Event\SubscriberInterface
{
    public function __construct(&$subject, array $config = [])
    {
        parent::__construct($subject, $config);
    }

    public function onAfterInitialise(): void
    {
    }
    public static function getSubscribedEvents(): array
    {
        return ['onAfterInitialise' => 'onAfterInitialise'];
    }
}