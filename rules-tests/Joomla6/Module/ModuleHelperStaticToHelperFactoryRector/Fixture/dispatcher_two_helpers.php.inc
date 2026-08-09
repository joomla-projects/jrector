<?php

namespace Acme\Module\Foo\Site\Dispatcher;

use Acme\Module\Foo\Site\Helper\BarHelper;
use Acme\Module\Foo\Site\Helper\FooHelper;
use Joomla\CMS\Dispatcher\AbstractModuleDispatcher;
use Joomla\CMS\Helper\ModuleHelper;

class Dispatcher extends AbstractModuleDispatcher
{
    protected function getLayoutData(): array
    {
        $data           = parent::getLayoutData();
        $data['items']  = FooHelper::getItems($data['params']);
        $data['count']  = FooHelper::countItems($data['params']);
        $data['extra']  = BarHelper::getExtra();
        $data['layout'] = ModuleHelper::getLayoutPath('mod_foo', 'default');

        return $data;
    }
}

?>
-----
<?php

namespace Acme\Module\Foo\Site\Dispatcher;

use Acme\Module\Foo\Site\Helper\BarHelper;
use Acme\Module\Foo\Site\Helper\FooHelper;
use Joomla\CMS\Dispatcher\AbstractModuleDispatcher;
use Joomla\CMS\Helper\ModuleHelper;

class Dispatcher extends AbstractModuleDispatcher implements \Joomla\CMS\Helper\HelperFactoryAwareInterface
{
    use \Joomla\CMS\Helper\HelperFactoryAwareTrait;
    protected function getLayoutData(): array
    {
        $data           = parent::getLayoutData();
        $data['items']  = $this->getHelperFactory()->getHelper('FooHelper')->getItems($data['params']);
        $data['count']  = $this->getHelperFactory()->getHelper('FooHelper')->countItems($data['params']);
        $data['extra']  = $this->getHelperFactory()->getHelper('BarHelper')->getExtra();
        $data['layout'] = ModuleHelper::getLayoutPath('mod_foo', 'default');

        return $data;
    }
}

?>
