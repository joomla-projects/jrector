<?php

namespace Joomla\Rector\Tests\Joomla7\ResultIsFalseablePropertyRector\Fixture;

use Joomla\CMS\Event\Result\ResultTypeStringAware;

class GetKeyEvent
{
    use ResultTypeStringAware;

    protected $resultIsNullable = true, $resultIsFalseable = false;
}

?>
-----
<?php

namespace Joomla\Rector\Tests\Joomla7\ResultIsFalseablePropertyRector\Fixture;

use Joomla\CMS\Event\Result\ResultTypeStringAware;

class GetKeyEvent
{
    use ResultTypeStringAware;

    protected $resultIsNullable = true;
}

?>
