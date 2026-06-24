<?php

class ExampleModel
{
    /**
     * The current item.
     *
     * @var CMSObject
     */
    public CMSObject $item;
}
-----
<?php

class ExampleModel
{
    /**
     * The current item.
     *
     * @var stdClass
     */
    public stdClass $item;
}
