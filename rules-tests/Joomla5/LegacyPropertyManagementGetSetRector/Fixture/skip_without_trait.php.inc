<?php

// Class does not use LegacyPropertyManagementTrait — nothing should change.
class ExampleViewWithoutTrait
{
    public function display(): void
    {
        $title = $this->get('title', '');
        $this->set('active', true);
    }
}
