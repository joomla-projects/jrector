<?php

namespace Joomla\Rector\Tests\Joomla6\Template\FactoryGetDocumentRector\Fixture;

use Joomla\CMS\Factory;
use Joomla\CMS\Plugin\CMSPlugin;

class PlgSystemExample extends CMSPlugin
{
    public function onAfterRender(): void
    {
        Factory::getDocument()->setTitle('x');
    }
}

?>
-----
<?php

namespace Joomla\Rector\Tests\Joomla6\Template\FactoryGetDocumentRector\Fixture;

use Joomla\CMS\Factory;
use Joomla\CMS\Plugin\CMSPlugin;

class PlgSystemExample extends CMSPlugin
{
    public function onAfterRender(): void
    {
        $this->getApplication()->getDocument()->setTitle('x');
    }
}

?>
