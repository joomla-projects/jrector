<?php

namespace Acme\Module\Foo\Site\Service;

use Acme\Module\Foo\Site\Helper\FooHelper;

// Not a dispatcher: $this would not have getHelperFactory().
class SomeService
{
    public function run()
    {
        return FooHelper::getItems([]);
    }
}
