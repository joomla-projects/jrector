<?php

class ExampleModel
{
    /**
     * Returns the item.
     *
     * @return CMSObject
     */
    public function getItem(): CMSObject
    {
        return new CMSObject();
    }
}
-----
<?php

class ExampleModel
{
    /**
     * Returns the item.
     *
     * @return stdClass
     */
    public function getItem(): stdClass
    {
        return new CMSObject();
    }
}
