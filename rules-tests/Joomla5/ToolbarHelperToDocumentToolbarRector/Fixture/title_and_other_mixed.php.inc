<?php

use Joomla\CMS\Document\DocumentAwareInterface;

class ExampleView implements DocumentAwareInterface
{
    public function addToolbar(): void
    {
        ToolbarHelper::title('Articles', 'article');
        ToolbarHelper::addNew('article.add');
    }
}
-----
<?php

use Joomla\CMS\Document\DocumentAwareInterface;

class ExampleView implements DocumentAwareInterface
{
    public function addToolbar(): void
    {
        ToolbarHelper::title('Articles', 'article');
        $toolbar = $this->getDocument()->getToolbar();
        $toolbar->addNew('article.add');
    }
}
