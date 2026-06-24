<?php

class ExampleModel
{
    public function getItem(): CMSObject
    {
        return new CMSObject();
    }
}
-----
<?php

class ExampleModel
{
    public function getItem(): stdClass
    {
        return new CMSObject();
    }
}
