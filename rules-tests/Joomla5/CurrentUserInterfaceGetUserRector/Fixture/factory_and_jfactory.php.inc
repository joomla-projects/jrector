<?php

use Joomla\CMS\Factory;

class ExampleModel extends \Joomla\CMS\MVC\Model\BaseDatabaseModel
{
    public function isAllowed(): bool
    {
        $user1 = Factory::getUser();
        $user2 = JFactory::getUser();

        return $user1->authorise('core.edit', 'com_example');
    }
}
-----
<?php

use Joomla\CMS\Factory;

class ExampleModel extends \Joomla\CMS\MVC\Model\BaseDatabaseModel
{
    public function isAllowed(): bool
    {
        $user1 = $this->getCurrentUser();
        $user2 = $this->getCurrentUser();

        return $user1->authorise('core.edit', 'com_example');
    }
}
