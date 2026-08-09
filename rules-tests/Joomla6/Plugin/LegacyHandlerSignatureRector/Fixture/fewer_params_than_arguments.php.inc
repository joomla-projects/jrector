<?php

namespace Joomla\Rector\Tests\Joomla6\Plugin\LegacyHandlerSignatureRector\Fixture;

use Joomla\CMS\Plugin\CMSPlugin;

class PlgContentFewerParams extends CMSPlugin
{
    /**
     * Prepares the content.
     *
     * @param   string  $context  The context.
     * @param   object  $article  The article.
     *
     * @return  void
     */
    public function onContentPrepare($context, $article)
    {
        $article->text .= $context;
    }
}

?>
-----
<?php

namespace Joomla\Rector\Tests\Joomla6\Plugin\LegacyHandlerSignatureRector\Fixture;

use Joomla\CMS\Plugin\CMSPlugin;

class PlgContentFewerParams extends CMSPlugin
{
    /**
     * Prepares the content.
     *
     * @param   \Joomla\CMS\Event\Content\ContentPrepareEvent  $event  The event.
     *
     * @return  void
     */
    public function onContentPrepare(\Joomla\CMS\Event\Content\ContentPrepareEvent $event): void
    {
        $context = $event->getContext();
        $article = $event->getItem();
        $article->text .= $context;
    }
}

?>
