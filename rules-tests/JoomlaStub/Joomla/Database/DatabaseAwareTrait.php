<?php

/**
 * Minimal stub, so the rules can be tested without requiring a real Joomla installation.
 */

declare(strict_types=1);

namespace Joomla\Database;

trait DatabaseAwareTrait
{
    public function getDatabase()
    {
        return null;
    }
}
