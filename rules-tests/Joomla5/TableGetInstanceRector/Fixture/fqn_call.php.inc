<?php

class MyModel
{
    public function getArticleTable(): void
    {
        $articleTable = \Joomla\CMS\Table\Table::getInstance('Article');
    }
}
-----
<?php

class MyModel
{
    public function getArticleTable(): void
    {
        $db = \Joomla\CMS\Factory::getDbo();
        $articleTable = new \Joomla\CMS\Table\Article($db);
    }
}
