<?php

/**
 * Minimal stub of the Joomla HTML document, so the type based receiver check of the rule can be
 * tested without requiring a real Joomla installation.
 */

declare(strict_types=1);

namespace Joomla\CMS\Document;

class HtmlDocument
{
    public function countModules(string $positionName, bool $withContentOnly = false)
    {
        return 0;
    }
}
