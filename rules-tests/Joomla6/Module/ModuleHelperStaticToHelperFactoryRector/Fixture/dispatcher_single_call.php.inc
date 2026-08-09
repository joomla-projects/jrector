<?php

namespace Acme\Module\Foo\Site\Dispatcher;

use Acme\Module\Foo\Site\Helper\FooHelper;
use Joomla\CMS\Dispatcher\AbstractModuleDispatcher;

class Dispatcher extends AbstractModuleDispatcher
{
    protected function getLayoutData(): array
    {
        $data          = parent::getLayoutData();
        $data['items'] = FooHelper::getItems($data['params'], $this->getApplication());

        return $data;
    }
}

?>
-----
<?php

namespace Acme\Module\Foo\Site\Dispatcher;

use Acme\Module\Foo\Site\Helper\FooHelper;
use Joomla\CMS\Dispatcher\AbstractModuleDispatcher;

class Dispatcher extends AbstractModuleDispatcher implements \Joomla\CMS\Helper\HelperFactoryAwareInterface
{
    use \Joomla\CMS\Helper\HelperFactoryAwareTrait;
    protected function getLayoutData(): array
    {
        $data          = parent::getLayoutData();
        $data['items'] = $this->getHelperFactory()->getHelper('FooHelper')->getItems($data['params'], $this->getApplication());

        return $data;
    }
}

?>
