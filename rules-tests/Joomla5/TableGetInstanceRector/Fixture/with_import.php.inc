<?php

use Joomla\CMS\Table\Table;

class MyModel
{
    public function getContentTable(): void
    {
        $table = Table::getInstance('Content');
    }
}
-----
<?php

use Joomla\CMS\Table\Table;

class MyModel
{
    public function getContentTable(): void
    {
        $db = \Joomla\CMS\Factory::getDbo();
        $table = new \Joomla\CMS\Table\Content($db);
    }
}