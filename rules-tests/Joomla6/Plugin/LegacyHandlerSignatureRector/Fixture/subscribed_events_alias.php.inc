<?php

namespace Joomla\Rector\Tests\Joomla6\Plugin\LegacyHandlerSignatureRector\Fixture;

use Joomla\CMS\Plugin\CMSPlugin;
use Joomla\Event\SubscriberInterface;

class PlgContentAlias extends CMSPlugin implements SubscriberInterface
{
    public static function getSubscribedEvents(): array
    {
        return ['onContentPrepare' => 'handlePrepare'];
    }

    public function handlePrepare($context, $article)
    {
        $article->text .= $context;
    }
}

?>
-----
<?php

namespace Joomla\Rector\Tests\Joomla6\Plugin\LegacyHandlerSignatureRector\Fixture;

use Joomla\CMS\Plugin\CMSPlugin;
use Joomla\Event\SubscriberInterface;

class PlgContentAlias extends CMSPlugin implements SubscriberInterface
{
    public static function getSubscribedEvents(): array
    {
        return ['onContentPrepare' => 'handlePrepare'];
    }

    public function handlePrepare(\Joomla\CMS\Event\Content\ContentPrepareEvent $event): void
    {
        $context = $event->getContext();
        $article = $event->getItem();
        $article->text .= $context;
    }
}

?>
