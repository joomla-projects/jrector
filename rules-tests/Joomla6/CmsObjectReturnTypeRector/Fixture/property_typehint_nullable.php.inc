<?php

class ExampleModel
{
    public ?CMSObject $item = null;
}
-----
<?php

class ExampleModel
{
    public ?stdClass $item = null;
}
