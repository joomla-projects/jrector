<?php

namespace Acme\Module\Foo\Site\Helper;

// Inheritance means static:: may need late static binding — skip.
class ChildHelper extends BaseHelper
{
    public static function getItems($params)
    {
        return [];
    }
}
