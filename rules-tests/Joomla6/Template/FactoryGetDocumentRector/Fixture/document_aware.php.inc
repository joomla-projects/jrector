<?php

namespace Joomla\Rector\Tests\Joomla6\Template\FactoryGetDocumentRector\Fixture;

use Joomla\CMS\Document\DocumentAwareInterface;
use Joomla\CMS\Factory;

class ExampleView implements DocumentAwareInterface
{
    public function display($tpl = null)
    {
        $doc = Factory::getDocument();
        $doc->setTitle('Example');

        \Joomla\CMS\Factory::getDocument()->setDescription('x');
        JFactory::getDocument()->setTitle('y');
    }

    public function setDocument($document)
    {
    }
}

?>
-----
<?php

namespace Joomla\Rector\Tests\Joomla6\Template\FactoryGetDocumentRector\Fixture;

use Joomla\CMS\Document\DocumentAwareInterface;
use Joomla\CMS\Factory;

class ExampleView implements DocumentAwareInterface
{
    public function display($tpl = null)
    {
        $doc = $this->getDocument();
        $doc->setTitle('Example');

        $this->getDocument()->setDescription('x');
        $this->getDocument()->setTitle('y');
    }

    public function setDocument($document)
    {
    }
}

?>
