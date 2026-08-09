<?php

namespace Acme\Module\Foo\Site\Helper;

// The method reads a static property of its own class, so it has to stay static.
class CacheHelper
{
    protected static $cache = [];

    public static function getItems($params)
    {
        return self::$cache;
    }
}
