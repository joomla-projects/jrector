<?php

/**
 * Minimal stub, so the rules can be tested without requiring a real Joomla installation.
 */

declare(strict_types=1);

namespace Joomla\CMS;

abstract class Factory
{
    public static function getDbo()
    {
        return null;
    }

    public static function getUser()
    {
        return null;
    }

    public static function getApplication()
    {
        return null;
    }
}
