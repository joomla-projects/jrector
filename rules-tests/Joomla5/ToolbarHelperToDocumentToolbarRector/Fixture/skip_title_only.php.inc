<?php

use Joomla\CMS\Document\DocumentAwareInterface;

// Only ToolbarHelper::title() — nothing should change.
class ExampleView implements DocumentAwareInterface
{
    public function addToolbar(): void
    {
        ToolbarHelper::title('Articles', 'article');
    }
}
