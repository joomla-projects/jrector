<?php

namespace Joomla\Rector\Tests\Joomla6\Plugin\LegacyHandlerSignatureRector\Fixture;

use Joomla\CMS\Event\Content\ContentPrepareEvent;
use Joomla\CMS\Plugin\CMSPlugin;

class PlgContentSkips extends CMSPlugin
{
    // Idempotence: already takes the event object.
    public function onContentPrepare(ContentPrepareEvent $event)
    {
        $context = $event->getContext();
    }

    // Unknown event name — not in the generated event name map.
    public function onSomethingEntirelyMadeUp($context, $article)
    {
        $article->text .= $context;
    }

    // A reference parameter that is reassigned cannot be expressed with a getter.
    public function onContentAfterTitle($context, &$article, &$params)
    {
        $article = 'replaced';
    }

    // More parameters than ContentPrepareEvent has arguments.
    public function onContentBeforeDisplay($context, $article, $params, $page, $extra, $andMore)
    {
        return $context;
    }
}
