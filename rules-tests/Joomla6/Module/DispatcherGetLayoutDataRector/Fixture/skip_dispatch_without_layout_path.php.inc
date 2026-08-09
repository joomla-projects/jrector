<?php

namespace Acme\Module\Foo\Site\Dispatcher;

use Joomla\CMS\Dispatcher\AbstractModuleDispatcher;

// dispatch() deliberately echoes directly, there is no layout to render.
class Dispatcher extends AbstractModuleDispatcher
{
    public function dispatch()
    {
        echo 'no layout here';
    }
}
