<?php

use Joomla\CMS\Factory;

class ExampleModel extends BaseDatabaseModel
{
    public function getItems(): array
    {
        $db1 = $this->getDbo();
        $db2 = Factory::getDbo();
        $db3 = JFactory::getDbo();

        $query = $db1->getQuery(true);

        return $db1->loadObjectList();
    }
}
-----
<?php

use Joomla\CMS\Factory;

class ExampleModel extends BaseDatabaseModel
{
    public function getItems(): array
    {
        $db1 = $this->getDatabase();
        $db2 = $this->getDatabase();
        $db3 = $this->getDatabase();

        $query = $db1->getQuery(true);

        return $db1->loadObjectList();
    }
}
