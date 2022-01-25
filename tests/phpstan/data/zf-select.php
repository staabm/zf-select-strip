<?php

namespace ZfSelectPHPStanInference;

use function PHPStan\Testing\assertType;

class Foo
{
    public function foo1()
    {
        $dbTable = new \DbTable();
        $select = $dbTable->select();

        assertType("'SELECT `ada`.* FROM `ada`'", $select->__toString());
    }

    public function foo2()
    {
        $dbTable = new \DbTable();
        $select = $dbTable->select();
        $select->from('ada as a');

        assertType("'SELECT `a`.* FROM `ada` AS `a`'", $select->__toString());
    }

    public function foo3()
    {
        $dbTable = new \DbTable();
        $select = $dbTable->select();
        $select->from('ada as a');
        $select->join('art as e', 'a.artid = e.artid', []);

        assertType("'SELECT `a`.* FROM `ada` AS `a`
 INNER JOIN `art` AS `e` ON a.artid = e.artid'", $select->__toString());
    }

    public function foo4()
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

    public function foo5()
    {
        $dbTable = new \DbTable();
        $select = $dbTable->select();
        $select->from('ada as a');
        $select->join('art as e', 'a.artid = e.artid', []);
        $select->joinLeft('artgroessebestand as bestand', '(k.artgroesseid = bestand.artgroesseid)', []);
        $select->where('e.aktiv = ?', 1);

        assertType("'SELECT `a`.* FROM `ada` AS `a`
 INNER JOIN `art` AS `e` ON a.artid = e.artid
 LEFT JOIN `artgroessebestand` AS `bestand` ON (k.artgroesseid = bestand.artgroesseid) WHERE (e.aktiv = ?)'", $select->__toString());
    }

    public function foo6()
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
 LEFT JOIN `artgroessebestand` AS `bestand` ON (k.artgroesseid = bestand.artgroesseid) WHERE (e.aktiv = ?) GROUP BY `b`.`artfarbeid`'", $select->__toString());
    }

    public function foo7()
    {
        $dbTable = new \DbTable();
        $select = $dbTable->select();
        $select->from('ada as a');
        $select->join('art as e', 'a.artid = e.artid', []);
        $select->joinLeft('artgroessebestand as bestand', '(k.artgroesseid = bestand.artgroesseid)', []);
        $select->where('e.aktiv = ?', 1);
        $select->group('b.artfarbeid');
        $select->order(['f.sortierungid', 'bezeichnung ASC']);

        assertType("'SELECT `a`.* FROM `ada` AS `a`
 INNER JOIN `art` AS `e` ON a.artid = e.artid
 LEFT JOIN `artgroessebestand` AS `bestand` ON (k.artgroesseid = bestand.artgroesseid) WHERE (e.aktiv = ?) GROUP BY `b`.`artfarbeid` ORDER BY `f`.`sortierungid` ASC, `bezeichnung` ASC'", $select->__toString());
    }

    public function foo8()
    {
        $dbTable = new \DbTable();
        $select = $dbTable->select();
        $columns = [
            'a.artid as artid',
        ];

        $select->from('ada as a', $columns);
        $select->join('art as e', 'a.artid = e.artid', []);
        $select->setIntegrityCheck(false);

        assertType("'SELECT `a`.`artid` FROM `ada` AS `a`
 INNER JOIN `art` AS `e` ON a.artid = e.artid'", $select->__toString());
    }

    public function foo9(int $aktiv)
    {
        $dbTable = new \DbTable();
        $select = $dbTable->select();
        $select->from('ada as a');
        $select->join('art as e', 'a.artid = e.artid', []);
        $select->joinLeft('artgroessebestand as bestand', '(k.artgroesseid = bestand.artgroesseid)', []);
        $select->where('e.aktiv = ?', $aktiv);
        $select->group('b.artfarbeid');
        $select->setIntegrityCheck(false);

        assertType("'SELECT `a`.* FROM `ada` AS `a`
 INNER JOIN `art` AS `e` ON a.artid = e.artid
 LEFT JOIN `artgroessebestand` AS `bestand` ON (k.artgroesseid = bestand.artgroesseid) WHERE (e.aktiv = ?) GROUP BY `b`.`artfarbeid`'", $select->__toString());
    }

    public function foo10(int $aktiv)
    {
        $dbTable = new \DbTable();
        $select = $dbTable->select();
        $select->from('ada as a');
        $select->join('art as e', 'a.artid = e.artid', ['ada.spracheid as spracheid']);
        $select->joinLeft('artgroessebestand as bestand', '(k.artgroesseid = bestand.artgroesseid)', []);
        $select->where('e.aktiv = ?', $aktiv);
        $select->group('b.artfarbeid');
        $select->setIntegrityCheck(false);

        assertType("'SELECT `a`.*, `ada`.`spracheid` FROM `ada` AS `a`
 INNER JOIN `art` AS `e` ON a.artid = e.artid
 LEFT JOIN `artgroessebestand` AS `bestand` ON (k.artgroesseid = bestand.artgroesseid) WHERE (e.aktiv = ?) GROUP BY `b`.`artfarbeid`'", $select->__toString());
    }

    public function foo11(int $aktiv)
    {
        $dbTable = new \DbTable();
        $select = $dbTable->select(true);
        // with from part
        $select->join('art as e', 'a.artid = e.artid', ['ada.spracheid as spracheid']);
        $select->joinLeft('artgroessebestand as bestand', '(k.artgroesseid = bestand.artgroesseid)', []);
        $select->where('e.aktiv = ?', $aktiv);
        $select->group('b.artfarbeid');
        $select->setIntegrityCheck(false);

        assertType("'SELECT `ada`.*, `ada`.`spracheid` FROM `ada`
 INNER JOIN `art` AS `e` ON a.artid = e.artid
 LEFT JOIN `artgroessebestand` AS `bestand` ON (k.artgroesseid = bestand.artgroesseid) WHERE (e.aktiv = ?) GROUP BY `b`.`artfarbeid`'", $select->__toString());
    }

    public function foo12()
    {
        $dbTable = new \DbTable();
        $select = $dbTable->select();
        $select->limit(1);

        assertType("'SELECT `ada`.* FROM `ada` LIMIT 1'", $select->__toString());
    }

    public function foo13()
    {
        $dbTable = new \DbTable();
        $select = $dbTable->select();
        $select->join('art as e', 'a.artid = e.artid', []);
        $select->limit(1, 2);

        assertType("'SELECT `ada`.* FROM `art` AS `e`
 INNER JOIN `ada` LIMIT 1 OFFSET 2'", $select->__toString());
    }

    public function joinNoCols()
    {
        $dbTable = new \DbTable();
        $select = $dbTable->select();
        $select->setIntegrityCheck(false);
        $select->from('ada as a');
        $select->join('art as e', 'a.artid = e.artid');

        assertType("'SELECT `a`.*, `e`.* FROM `ada` AS `a`
 INNER JOIN `art` AS `e` ON a.artid = e.artid'", $select->__toString());
    }
}
