<?php

class ExampleModel implements \Joomla\CMS\User\CurrentUserInterface
{
    public function getAuthoredBy(): int
    {
        return (int) \Joomla\CMS\Factory::getUser()->id;
    }
}
-----
<?php

class ExampleModel implements \Joomla\CMS\User\CurrentUserInterface
{
    public function getAuthoredBy(): int
    {
        return (int) $this->getCurrentUser()->id;
    }
}