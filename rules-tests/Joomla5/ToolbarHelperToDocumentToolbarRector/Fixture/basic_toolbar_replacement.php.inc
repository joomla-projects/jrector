<?php

use Joomla\CMS\Document\DocumentAwareInterface;

class ExampleView implements DocumentAwareInterface
{
    public function addToolbar(): void
    {
        ToolbarHelper::addNew('article.add');
        ToolbarHelper::editList('article.edit');
        ToolbarHelper::deleteList('', 'article.delete');
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
        $toolbar->deleteList('', 'article.delete');
    }
}
