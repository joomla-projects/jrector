<?php

class ExampleModel extends BaseModel
{
    public function getAuthoredBy(): int
    {
        return (int) \Joomla\CMS\Factory::getUser()->id;
    }
}
-----
<?php

class ExampleModel extends BaseModel
{
    public function getAuthoredBy(): int
    {
        return (int) $this->getCurrentUser()->id;
    }
}