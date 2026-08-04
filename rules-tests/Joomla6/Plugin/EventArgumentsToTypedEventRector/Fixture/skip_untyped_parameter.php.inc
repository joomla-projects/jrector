<?php

namespace Joomla\Rector\Tests\Joomla6\Plugin\EventArgumentsToTypedEventRector\Fixture;

// Without a type the concrete event class cannot be determined.
// Typing the signature is the job of LegacyHandlerSignatureRector.
class PlgContentUntyped
{
    public function onContentPrepare($event)
    {
        [$context, $item, $params, $page] = array_values($event->getArguments());

        $item->text .= $context;
    }
}
