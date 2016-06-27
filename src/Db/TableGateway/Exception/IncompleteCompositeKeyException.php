<?php
/**
 * User: Allan Sun (allan.sun@bricre.com)
 * Date: 27/12/2015
 * Time: 15:19
 */

namespace BS\Db\TableGateway\Exception;

use BS\Exception as Base;

class IncompleteCompositeKeyException extends Base
{
    /**
     * @inheritDoc
     */
    public function __toString()
    {
        return 'Values given do not fulfill all composite keys';
    }
}
