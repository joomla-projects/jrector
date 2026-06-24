<?php

class ExampleModel
{
    public function loadItem(): CMSObject|false
    {
        return false;
    }
}
-----
<?php

class ExampleModel
{
    public function loadItem(): stdClass|false
    {
        return false;
    }
}
