<?php

namespace Joomla\Rector\Tests\Joomla6\Plugin\LegacyHandlerSignatureRector\Fixture;

use Joomla\CMS\Plugin\CMSPlugin;

class PlgContentStandard extends CMSPlugin
{
    public function onContentPrepare($context, &$article, &$params, $page = 0)
    {
        if ($context !== 'com_content.article') {
            return;
        }
        $article->text .= '<p>foo</p>';
    }
}

?>
-----
<?php

namespace Joomla\Rector\Tests\Joomla6\Plugin\LegacyHandlerSignatureRector\Fixture;

use Joomla\CMS\Plugin\CMSPlugin;

class PlgContentStandard extends CMSPlugin
{
    public function onContentPrepare(\Joomla\CMS\Event\Content\ContentPrepareEvent $event): void
    {
        $context = $event->getContext();
        $article = $event->getItem();
        $params = $event->getParams();
        $page = $event->getPage();
        if ($context !== 'com_content.article') {
            return;
        }
        $article->text .= '<p>foo</p>';
    }
}

?>
