<?php

class ExampleModel
{
    public function load($id)
    {
        if ($id > 0) {
            return $this->find($id);
        } else {
            $this->setError('Invalid ID');
            return false;
        }
    }
}
-----
<?php

class ExampleModel
{
    public function load($id)
    {
        if ($id > 0) {
            return $this->find($id);
        } else {
            throw new \Exception('Invalid ID');
        }
    }
}
