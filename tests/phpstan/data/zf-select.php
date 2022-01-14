<?php

namespace ZfSelectPHPStanInference;

use function PHPStan\Testing\assertType;

class Foo {
    function foo1()
    {
        $dbTable = new \DbTable();
        $select = $dbTable->select();

        assertType("'SELECT `ada`.* FROM `ada`'", $select->__toString());
    }

    function foo2()
    {
        $dbTable = new \DbTable();
        $select = $dbTable->select();
        $select->from('ada as a');

        assertType("'SELECT `a`.* FROM `ada` AS `a`'", $select->__toString());
    }
}
