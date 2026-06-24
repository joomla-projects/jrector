<?php

class ExampleModel
{
    public function save($data)
    {
        $this->setError('Save failed');
        return false;
    }
}
-----
<?php

class ExampleModel
{
    public function save($data)
    {
        throw new \Exception('Save failed');
    }
}
