<?php

class ExampleModel
{
    /**
     * @var CMSObject
     */
    public $item;

    /**
     * @var \Joomla\CMS\Object\CMSObject
     */
    public $related;
}
-----
<?php

class ExampleModel
{
    /**
     * @var stdClass
     */
    public $item;

    /**
     * @var stdClass
     */
    public $related;
}
