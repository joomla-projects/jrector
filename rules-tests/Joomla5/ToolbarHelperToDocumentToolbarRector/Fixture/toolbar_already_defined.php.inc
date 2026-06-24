<?php

use Joomla\CMS\Document\DocumentAwareInterface;

class ExampleView implements DocumentAwareInterface
{
    public function addToolbar(): void
    {
        $toolbar = $this->getDocument()->getToolbar();
        ToolbarHelper::addNew('article.add');
        ToolbarHelper::editList('article.edit');
    }
}
-----
<?php

use Joomla\CMS\Document\DocumentAwareInterface;

class ExampleView implements DocumentAwareInterface
{
    public function addToolbar(): void
    {
        $toolbar = $this->getDocument()->getToolbar();
        $toolbar->addNew('article.add');
        $toolbar->editList('article.edit');
    }
}
