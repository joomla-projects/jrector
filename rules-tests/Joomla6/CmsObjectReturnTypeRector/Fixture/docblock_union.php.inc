<?php

class ExampleModel
{
    /**
     * @return CMSObject|false
     */
    public function loadItem()
    {
        return false;
    }
}
-----
<?php

class ExampleModel
{
    /**
     * @return stdClass|false
     */
    public function loadItem()
    {
        return false;
    }
}
