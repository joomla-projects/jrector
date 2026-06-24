<?php

use Joomla\CMS\Document\DocumentAwareInterface;

class ExampleView implements DocumentAwareInterface
{
    public function addToolbarForList(): void
    {
        ToolbarHelper::addNew('article.add');
        ToolbarHelper::deleteList('', 'article.delete');
    }

    public function addToolbarForEdit(): void
    {
        ToolbarHelper::apply('article.apply');
        ToolbarHelper::save('article.save');
        ToolbarHelper::cancel('article.cancel');
    }
}
-----
<?php

use Joomla\CMS\Document\DocumentAwareInterface;

class ExampleView implements DocumentAwareInterface
{
    public function addToolbarForList(): void
    {
        $toolbar = $this->getDocument()->getToolbar();
        $toolbar->addNew('article.add');
        $toolbar->deleteList('', 'article.delete');
    }

    public function addToolbarForEdit(): void
    {
        $toolbar = $this->getDocument()->getToolbar();
        $toolbar->apply('article.apply');
        $toolbar->save('article.save');
        $toolbar->cancel('article.cancel');
    }
}
