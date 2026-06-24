<?php

class ExampleModel
{
    public function findItem(int $id): ?CMSObject
    {
        return null;
    }
}
-----
<?php

class ExampleModel
{
    public function findItem(int $id): ?stdClass
    {
        return null;
    }
}
