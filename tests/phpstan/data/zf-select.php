<?php

namespace ZfSelectPHPStanInference;

use function PHPStan\Testing\assertType;

function selectFrom()
{
    $dbTable = new \DbTable();
    $select = $dbTable->select();
    // $select->from('artfarbe as a');

    assertType("'SELECT `ada`.* FROM `ada`'", $select->__toString());
}