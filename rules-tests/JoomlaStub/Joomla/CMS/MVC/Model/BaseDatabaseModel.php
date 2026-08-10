<?php

/**
 * Minimal stub, so the rules can be tested without requiring a real Joomla installation.
 *
 * It carries exactly the two traits/interfaces the Joomla 5 rules detect: the DatabaseAwareTrait
 * for GetDboToGetDatabaseRector and CurrentUserInterface for CurrentUserInterfaceGetUserRector.
 */

declare(strict_types=1);

namespace Joomla\CMS\MVC\Model;

use Joomla\CMS\User\CurrentUserInterface;
use Joomla\Database\DatabaseAwareTrait;

abstract class BaseDatabaseModel implements CurrentUserInterface
{
    use DatabaseAwareTrait;

    public function getCurrentUser()
    {
        return null;
    }

    public function getDbo()
    {
        return null;
    }
}
