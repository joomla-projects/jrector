<?php

class ExampleModel
{
    /**
     * @return CMSObject
     */
    public function getItem()
    {
        return new CMSObject();
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
        return new CMSObject();
    }
}
