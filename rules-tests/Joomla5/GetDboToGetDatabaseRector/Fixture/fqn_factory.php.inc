<?php

class ExampleModel extends \Joomla\CMS\MVC\Model\AdminModel
{
    public function getCount(): int
    {
        $db = \Joomla\CMS\Factory::getDbo();

        return (int) $db->loadResult();
    }
}
-----
<?php

class ExampleModel extends \Joomla\CMS\MVC\Model\AdminModel
{
    public function getCount(): int
    {
        $db = $this->getDatabase();

        return (int) $db->loadResult();
    }
}
