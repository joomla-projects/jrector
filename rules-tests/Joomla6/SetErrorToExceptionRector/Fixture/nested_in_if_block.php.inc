<?php

class ExampleModel
{
    public function save($data)
    {
        if (!$this->validate($data)) {
            $this->setError('Validation failed');
            return false;
        }

        return true;
    }
}
-----
<?php

class ExampleModel
{
    public function save($data)
    {
        if (!$this->validate($data)) {
            throw new \Exception('Validation failed');
        }

        return true;
    }
}
