<?php

namespace Joomla\Rector\Tests\Joomla6\Template\FactoryGetDocumentRector\Fixture;

use Joomla\CMS\Factory;

// No DocumentAwareInterface, no getApplication(), no $app in scope.
class UnrelatedService
{
    public function run()
    {
        return Factory::getDocument();
    }

    // A call with arguments is something else.
    public function other()
    {
        return Factory::getDocument('html');
    }
}
