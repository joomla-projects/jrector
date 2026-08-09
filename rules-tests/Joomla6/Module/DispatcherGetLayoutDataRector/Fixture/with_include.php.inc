<?php

namespace Acme\Module\Bar\Site\Dispatcher;

use Joomla\CMS\Dispatcher\AbstractModuleDispatcher;
use Joomla\CMS\Helper\ModuleHelper;

class Dispatcher extends AbstractModuleDispatcher
{
    public function dispatch()
    {
        include ModuleHelper::getLayoutPath('mod_bar', 'default');
    }
}

?>
-----
<?php

namespace Acme\Module\Bar\Site\Dispatcher;

use Joomla\CMS\Dispatcher\AbstractModuleDispatcher;
use Joomla\CMS\Helper\ModuleHelper;

class Dispatcher extends AbstractModuleDispatcher
{
    protected function getLayoutData(): array
    {
        $data = parent::getLayoutData();
        return $data;
    }
}

?>
