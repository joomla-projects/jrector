<?php

class ExampleModel
{
    public function process($data)
    {
        if (empty($data['title'])) {
            $this->setError('Title is required');
            return false;
        }

        if (empty($data['body'])) {
            $this->setError('Body is required');
            return false;
        }

        return true;
    }
}
-----
<?php

class ExampleModel
{
    public function process($data)
    {
        if (empty($data['title'])) {
            throw new \Exception('Title is required');
        }

        if (empty($data['body'])) {
            throw new \Exception('Body is required');
        }

        return true;
    }
}
