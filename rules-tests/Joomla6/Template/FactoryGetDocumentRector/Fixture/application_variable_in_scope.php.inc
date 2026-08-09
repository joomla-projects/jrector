<?php

namespace Joomla\Rector\Tests\Joomla6\Template\FactoryGetDocumentRector\Fixture;

use Joomla\CMS\Factory;

class PlainService
{
    public function run()
    {
        $app = Factory::getApplication();

        return Factory::getDocument();
    }
}

?>
-----
<?php

namespace Joomla\Rector\Tests\Joomla6\Template\FactoryGetDocumentRector\Fixture;

use Joomla\CMS\Factory;

class PlainService
{
    public function run()
    {
        $app = Factory::getApplication();

        return $app->getDocument();
    }
}

?>
