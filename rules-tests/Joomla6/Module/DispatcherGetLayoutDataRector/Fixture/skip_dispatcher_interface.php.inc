<?php

namespace Acme\Module\Foo\Site\Dispatcher;

use Joomla\CMS\Dispatcher\DispatcherInterface;
use Joomla\CMS\Helper\ModuleHelper;

// Implements the interface directly, so there is no parent::getLayoutData() to call.
class Dispatcher implements DispatcherInterface
{
    public function dispatch()
    {
        require ModuleHelper::getLayoutPath('mod_foo', 'default');
    }
}
