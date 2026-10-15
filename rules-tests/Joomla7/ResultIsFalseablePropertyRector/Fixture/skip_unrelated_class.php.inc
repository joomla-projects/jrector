<?php

namespace Joomla\Rector\Tests\Joomla7\ResultIsFalseablePropertyRector\Fixture;

use Joomla\CMS\Event\Result\ResultTypeBooleanAware;

// The property name alone proves nothing: this class uses none of the six traits.
class SomeService
{
    protected $resultIsFalseable = true;
}

// The Boolean trait never had the flag; an own property of that name is the class's business.
class ValidateEvent
{
    use ResultTypeBooleanAware;

    protected $resultIsFalseable = true;
}

// Idempotence: nothing to remove.
class AlreadyClean
{
    use \Joomla\CMS\Event\Result\ResultTypeIntegerAware;

    protected $resultIsNullable = true;
}
