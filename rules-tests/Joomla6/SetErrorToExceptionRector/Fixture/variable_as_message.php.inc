<?php

class ExampleModel
{
    public function delete($id)
    {
        $error = 'Cannot delete item ' . $id;
        $this->setError($error);
        return false;
    }
}
-----
<?php

class ExampleModel
{
    public function delete($id)
    {
        $error = 'Cannot delete item ' . $id;
        throw new \Exception($error);
    }
}
