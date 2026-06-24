<?php

/**
 * @return CMSObject
 */
function getGlobalItem(): CMSObject
{
    return new CMSObject();
}
-----
<?php

/**
 * @return stdClass
 */
function getGlobalItem(): stdClass
{
    return new CMSObject();
}
