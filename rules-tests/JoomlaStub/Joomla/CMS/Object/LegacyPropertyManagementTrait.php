<?php

/**
 * Minimal stub, so the rules can be tested without requiring a real Joomla installation.
 */

declare(strict_types=1);

namespace Joomla\CMS\Object;

trait LegacyPropertyManagementTrait
{
    public function get($property, $default = null)
    {
        return $default;
    }

    public function set($property, $value = null)
    {
        return null;
    }
}
