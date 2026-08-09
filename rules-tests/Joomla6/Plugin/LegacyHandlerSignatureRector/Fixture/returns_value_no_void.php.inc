<?php

namespace Joomla\Rector\Tests\Joomla6\Plugin\LegacyHandlerSignatureRector\Fixture;

use Joomla\CMS\Plugin\CMSPlugin;

class PlgContentReturns extends CMSPlugin
{
    public function onContentPrepare($context, $article)
    {
        return true;
    }
}

?>
-----
<?php

namespace Joomla\Rector\Tests\Joomla6\Plugin\LegacyHandlerSignatureRector\Fixture;

use Joomla\CMS\Plugin\CMSPlugin;

class PlgContentReturns extends CMSPlugin
{
    public function onContentPrepare(\Joomla\CMS\Event\Content\ContentPrepareEvent $event)
    {
        $context = $event->getContext();
        $article = $event->getItem();
        return true;
    }
}

?>
