<?php

namespace Acme\Module\Foo\Site\Dispatcher;

use Joomla\CMS\Dispatcher\AbstractModuleDispatcher;
use Joomla\CMS\Helper\HelperFactoryAwareInterface;
use Joomla\CMS\Helper\HelperFactoryAwareTrait;

// Idempotence: already using the HelperFactory.
class Dispatcher extends AbstractModuleDispatcher implements HelperFactoryAwareInterface
{
    use HelperFactoryAwareTrait;

    protected function getLayoutData(): array
    {
        $data          = parent::getLayoutData();
        $data['items'] = $this->getHelperFactory()->getHelper('FooHelper')->getItems($data['params']);

        return $data;
    }
}
