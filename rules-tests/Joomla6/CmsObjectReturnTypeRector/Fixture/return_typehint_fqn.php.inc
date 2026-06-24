<?php

class ExampleModel
{
    public function getItem(): \Joomla\CMS\Object\CMSObject
    {
        return new \Joomla\CMS\Object\CMSObject();
    }
}
-----
<?php

class ExampleModel
{
    public function getItem(): stdClass
    {
        return new \Joomla\CMS\Object\CMSObject();
    }
}
