<?php

/**
 * Minimal stub, so the reflection path of the rule can be tested without a real Joomla.
 */

declare(strict_types=1);

namespace Joomla\CMS\Plugin;

abstract class CMSPlugin
{
    public function getApplication()
    {
        return null;
    }
}
