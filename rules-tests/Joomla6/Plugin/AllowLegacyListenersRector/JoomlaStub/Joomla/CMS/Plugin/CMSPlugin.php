<?php

/**
 * Minimal stub of the Joomla CMS plugin base class, so the reflection path of the rule can be
 * tested without requiring a real Joomla installation.
 */

declare(strict_types=1);

namespace Joomla\CMS\Plugin;

abstract class CMSPlugin
{
    protected $allowLegacyListeners = true;
}
