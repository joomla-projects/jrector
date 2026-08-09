<?php

namespace Acme\Module\Foo\Site\Helper;

class FooHelper
{
    public static function getItems($params)
    {
        return self::filter([]);
    }

    public static function filter(array $items)
    {
        return $items;
    }
}

?>
-----
<?php

namespace Acme\Module\Foo\Site\Helper;

class FooHelper
{
    public function getItems($params)
    {
        return $this->filter([]);
    }

    public function filter(array $items)
    {
        return $items;
    }
}

?>
