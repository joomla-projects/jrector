<?php

class ExampleModel
{
    /**
     * @return \Joomla\CMS\Object\CMSObject
     */
    public function getItem()
    {
        return new \Joomla\CMS\Object\CMSObject();
    }
}
-----
<?php

class ExampleModel
{
    /**
     * @return stdClass
     */
    public function getItem()
    {
        return new \Joomla\CMS\Object\CMSObject();
    }
}
