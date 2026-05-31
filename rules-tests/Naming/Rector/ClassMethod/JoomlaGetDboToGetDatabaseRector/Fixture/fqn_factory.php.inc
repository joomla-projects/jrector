<?php

class ExampleModel extends BaseDatabaseModel
{
    public function getCount(): int
    {
        $db = \Joomla\CMS\Factory::getDbo();

        return (int) $db->loadResult();
    }
}
-----
<?php

class ExampleModel extends BaseDatabaseModel
{
    public function getCount(): int
    {
        $db = $this->getDatabase();

        return (int) $db->loadResult();
    }
}
