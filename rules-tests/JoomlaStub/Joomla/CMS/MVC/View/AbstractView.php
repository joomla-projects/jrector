<?php

/**
 * Minimal stub, so the rules can be tested without requiring a real Joomla installation.
 */

declare(strict_types=1);

namespace Joomla\CMS\MVC\View;

abstract class AbstractView
{
    public function getModel($name = null)
    {
        return null;
    }
}
