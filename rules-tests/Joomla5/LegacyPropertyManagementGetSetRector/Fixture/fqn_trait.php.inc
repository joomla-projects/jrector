<?php

class ExampleModel
{
    use \Joomla\CMS\Object\LegacyPropertyManagementTrait;

    public function getTitle(): string
    {
        return $this->get('title', 'default');
    }
}
-----
<?php

class ExampleModel
{
    use \Joomla\CMS\Object\LegacyPropertyManagementTrait;

    public function getTitle(): string
    {
        return $this->title ?? 'default';
    }
}
