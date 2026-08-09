<?php

namespace Joomla\Rector\Tests\Joomla6\Template\FactoryGetDocumentRector\Fixture;

use Joomla\CMS\Document\DocumentAwareInterface;
use Joomla\CMS\Factory;

abstract class AbstractDocumentAwareView implements DocumentAwareInterface
{
    public function setDocument($document)
    {
    }
}

class InheritedView extends AbstractDocumentAwareView
{
    public function display()
    {
        return Factory::getDocument();
    }
}

?>
-----
<?php

namespace Joomla\Rector\Tests\Joomla6\Template\FactoryGetDocumentRector\Fixture;

use Joomla\CMS\Document\DocumentAwareInterface;
use Joomla\CMS\Factory;

abstract class AbstractDocumentAwareView implements DocumentAwareInterface
{
    public function setDocument($document)
    {
    }
}

class InheritedView extends AbstractDocumentAwareView
{
    public function display()
    {
        return $this->getDocument();
    }
}

?>
