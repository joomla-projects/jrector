<?php

use Joomla\CMS\Object\LegacyPropertyManagementTrait;

// Dynamic keys cannot be converted to a property access — nothing should change.
class ExampleView
{
    use LegacyPropertyManagementTrait;

    public function display(string $key): void
    {
        $value = $this->get($key, '');
        $this->set($key, 'new');
    }
}
