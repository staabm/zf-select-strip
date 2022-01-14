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

    function foo3()
    {
        $dbTable = new \DbTable();
        $select = $dbTable->select();
        $select->from('ada as a');
        $select->join('art as e', 'a.artid = e.artid', []);

        assertType("'SELECT `a`.* FROM `ada` AS `a`
 INNER JOIN `art` AS `e` ON a.artid = e.artid'", $select->__toString());
    }

    function foo4()
    {
        $dbTable = new \DbTable();
        $select = $dbTable->select();
        $select->from('ada as a');
        $select->join('art as e', 'a.artid = e.artid', []);
        $select->joinLeft('artgroessebestand as bestand', '(k.artgroesseid = bestand.artgroesseid)', []);

        assertType("'SELECT `a`.* FROM `ada` AS `a`
 INNER JOIN `art` AS `e` ON a.artid = e.artid
 LEFT JOIN `artgroessebestand` AS `bestand` ON (k.artgroesseid = bestand.artgroesseid)'", $select->__toString());
    }

    function foo5()
    {
        $dbTable = new \DbTable();
        $select = $dbTable->select();
        $select->from('ada as a');
        $select->join('art as e', 'a.artid = e.artid', []);
        $select->joinLeft('artgroessebestand as bestand', '(k.artgroesseid = bestand.artgroesseid)', []);
        $select->where('e.aktiv = ?', 1);

        assertType("'SELECT `a`.* FROM `ada` AS `a`
 INNER JOIN `art` AS `e` ON a.artid = e.artid
 LEFT JOIN `artgroessebestand` AS `bestand` ON (k.artgroesseid = bestand.artgroesseid) WHERE (e.aktiv = 1)'", $select->__toString());
    }

    function foo6()
    {
        $dbTable = new \DbTable();
        $select = $dbTable->select();
        $select->from('ada as a');
        $select->join('art as e', 'a.artid = e.artid', []);
        $select->joinLeft('artgroessebestand as bestand', '(k.artgroesseid = bestand.artgroesseid)', []);
        $select->where('e.aktiv = ?', 1);
        $select->group('b.artfarbeid');

        assertType("'SELECT `a`.* FROM `ada` AS `a`
 INNER JOIN `art` AS `e` ON a.artid = e.artid
 LEFT JOIN `artgroessebestand` AS `bestand` ON (k.artgroesseid = bestand.artgroesseid) WHERE (e.aktiv = 1) GROUP BY `b`.`artfarbeid`'", $select->__toString());
    }
}
