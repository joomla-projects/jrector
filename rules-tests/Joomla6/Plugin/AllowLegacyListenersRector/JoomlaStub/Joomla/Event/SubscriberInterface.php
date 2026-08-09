<?php

/**
 * Minimal stub of the Joomla Framework interface, so the reflection path of the rule can be
 * tested without requiring a real Joomla installation.
 */

declare(strict_types=1);

namespace Joomla\Event;

interface SubscriberInterface
{
    public static function getSubscribedEvents(): array;
}
