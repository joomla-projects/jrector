<?php

namespace Acme\Module\Foo\Site\Dispatcher;

use Joomla\CMS\Dispatcher\AbstractModuleDispatcher;

// Idempotence: getLayoutData() already exists.
class Dispatcher extends AbstractModuleDispatcher
{
    protected function getLayoutData(): array
    {
        $data = parent::getLayoutData();

        return $data;
    }
}
