<?php

namespace Joomla\Rector\Tests\Joomla6\Plugin\LegacyHandlerSignatureRector\Fixture;

// Does not extend CMSPlugin.
class SomeListener
{
    public function onContentPrepare($context, $article)
    {
        $article->text .= $context;
    }
}
