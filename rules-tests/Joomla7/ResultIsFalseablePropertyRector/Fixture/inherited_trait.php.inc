<?php

namespace Joomla\Rector\Tests\Joomla7\ResultIsFalseablePropertyRector\Fixture;

use Joomla\CMS\Event\Result\ResultTypeObjectAware;

abstract class AbstractObjectResultEvent
{
    use ResultTypeObjectAware;
}

class GetMethodEvent extends AbstractObjectResultEvent
{
    protected $resultIsFalseable = true;
}

?>
-----
<?php

namespace Joomla\Rector\Tests\Joomla7\ResultIsFalseablePropertyRector\Fixture;

use Joomla\CMS\Event\Result\ResultTypeObjectAware;

abstract class AbstractObjectResultEvent
{
    use ResultTypeObjectAware;
}

class GetMethodEvent extends AbstractObjectResultEvent
{
}

?>
