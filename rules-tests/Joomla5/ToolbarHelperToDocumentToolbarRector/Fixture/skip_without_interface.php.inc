<?php

// Class does not implement DocumentAwareInterface — nothing should change.
class ExampleView
{
    public function addToolbar(): void
    {
        ToolbarHelper::addNew('article.add');
        ToolbarHelper::editList('article.edit');
    }
}
