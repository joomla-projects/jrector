<?php

use Joomla\CMS\Table\Table;

class MyModel
{
    public function getUserTable(): void
    {
        $table = Table::getInstance('User', 'JTable');
    }
}
-----
<?php

use Joomla\CMS\Table\Table;

class MyModel
{
    public function getUserTable(): void
    {
        $db = \Joomla\CMS\Factory::getDbo();
        $table = new \Joomla\CMS\Table\User($db);
    }
}
