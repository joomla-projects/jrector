<?php

namespace Acme\Module\Foo\Site\Dispatcher;

use Joomla\CMS\Dispatcher\AbstractModuleDispatcher;
use Joomla\CMS\Helper\ModuleHelper;

class Dispatcher extends AbstractModuleDispatcher
{
    public function dispatch()
    {
        $params = $this->module->params;
        $items  = $this->getItems($params);

        require ModuleHelper::getLayoutPath('mod_foo', $params->get('layout', 'default'));
    }
}

?>
-----
<?php

namespace Acme\Module\Foo\Site\Dispatcher;

use Joomla\CMS\Dispatcher\AbstractModuleDispatcher;
use Joomla\CMS\Helper\ModuleHelper;

class Dispatcher extends AbstractModuleDispatcher
{
    protected function getLayoutData(): array
    {
        // TODO jrector: move every local variable the layout uses into $data, e.g. $data['items'] = $items;
        $data = parent::getLayoutData();
        $params = $this->module->params;
        $items  = $this->getItems($params);

        return $data;
    }
}

?>
