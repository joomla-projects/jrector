<?php

namespace Acme\Module\Foo\Site\Dispatcher;

use Acme\Component\Example\Site\Helper\ExampleHelper;
use Joomla\CMS\Dispatcher\AbstractModuleDispatcher;
use Joomla\CMS\Helper\ModuleHelper;
use Joomla\CMS\HTML\HTMLHelper;

// Core helpers and component helpers are not resolved through the module HelperFactory.
class Dispatcher extends AbstractModuleDispatcher
{
    protected function getLayoutData(): array
    {
        $data           = parent::getLayoutData();
        $data['layout'] = ModuleHelper::getLayoutPath('mod_foo', 'default');
        $data['date']   = HTMLHelper::_('date', 'now');
        $data['extra']  = ExampleHelper::getExtra();

        return $data;
    }
}
