<?php

class ExampleModel
{
    public CMSObject $item;

    protected CMSObject $related;
}
-----
<?php

class ExampleModel
{
    public stdClass $item;

    protected stdClass $related;
}
