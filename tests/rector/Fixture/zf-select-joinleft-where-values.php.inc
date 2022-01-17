<?php

namespace ZfSelectRectorTest;

use Clx_Model_Mapper_Abstract;

class Application_Model_Mapper_Artfarbe extends Clx_Model_Mapper_Abstract
{
    public function simpleSelect(int $artid, int $aktiv)
    {
        $dbTable = new \DbTable();
        $select = $dbTable->select();
        $select->from('ada as a');
        $select->join('art as e', 'a.artid = e.artid', []);
        $select->joinLeft('artgroessebestand as bestand', '(k.artgroesseid = bestand.artgroesseid)', []);
        $select->where('e.aktiv = ?', $aktiv);
        $select->where('azgroesse.menge > 0');
        $select->where('e.artid = ?', $artid);

        return $this->fetchRow($select);
    }
}
-----
<?php

namespace ZfSelectRectorTest;

use Clx_Model_Mapper_Abstract;

class Application_Model_Mapper_Artfarbe extends Clx_Model_Mapper_Abstract
{
    public function simpleSelect(int $artid, int $aktiv)
    {
        $dbTable = new \DbTable();
        $select = 'SELECT `a`.* FROM `ada` AS `a`
 INNER JOIN `art` AS `e` ON a.artid = e.artid
 LEFT JOIN `artgroessebestand` AS `bestand` ON (k.artgroesseid = bestand.artgroesseid) WHERE (e.aktiv = ?) AND (azgroesse.menge > 0) AND (e.artid = ?)';

        return $this->fetchRowByStatement(new ClxProductNet_DbStatement($select, [$aktiv, $artid]));
    }
}
