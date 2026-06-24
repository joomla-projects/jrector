<?php

use Joomla\CMS\Document\DocumentAwareInterface;

class ExampleView implements DocumentAwareInterface
{
    public function addToolbar(): void
    {
        \Joomla\CMS\Toolbar\ToolbarHelper::addNew('article.add');
        \Joomla\CMS\Toolbar\ToolbarHelper::editList('article.edit');
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
