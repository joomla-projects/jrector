<?php

namespace Joomla\Rector\Tests\Joomla7\ResultIsFalseablePropertyRector\Fixture;

use Joomla\CMS\Event\AbstractEvent;
use Joomla\CMS\Event\Result\ResultAware;
use Joomla\CMS\Event\Result\ResultAwareInterface;
use Joomla\CMS\Event\Result\ResultTypeArrayAware;

class GetItemsEvent extends AbstractEvent implements ResultAwareInterface
{
    use ResultAware;
    use ResultTypeArrayAware;

    protected $resultIsNullable = true;

    protected $resultIsFalseable = true;

    public function getItems(): array
    {
        return $this->arguments['result'] ?? [];
    }
}

?>
-----
<?php

namespace Joomla\Rector\Tests\Joomla7\ResultIsFalseablePropertyRector\Fixture;

use Joomla\CMS\Event\AbstractEvent;
use Joomla\CMS\Event\Result\ResultAware;
use Joomla\CMS\Event\Result\ResultAwareInterface;
use Joomla\CMS\Event\Result\ResultTypeArrayAware;

class GetItemsEvent extends AbstractEvent implements ResultAwareInterface
{
    use ResultAware;
    use ResultTypeArrayAware;

    protected $resultIsNullable = true;

    public function getItems(): array
    {
        return $this->arguments['result'] ?? [];
    }
}

?>
