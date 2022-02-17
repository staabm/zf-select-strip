<?php

namespace ZfSelectRectorTest;

use Clx_Model_Mapper_Abstract;

class Application_Model_Mapper_Artfarbe extends Clx_Model_Mapper_Abstract
{
    public function simpleSelect(int $spracheid)
    {
        $dbTable = new \DbTable();
        $select = $dbTable->select();
        $select->setIntegrityCheck(false);
        $select->from('ada as a');
        $select->join('art as e', '(a.artid = e.artid AND e.artid='.$spracheid.')');

        return $this->fetchRow($select);
    }
}
-----
<?php

namespace ZfSelectRectorTest;

use Clx_Model_Mapper_Abstract;

class Application_Model_Mapper_Artfarbe extends Clx_Model_Mapper_Abstract
{
    public function simpleSelect(int $spracheid)
    {
        $dbTable = new \DbTable();
        $select = 'SELECT `a`.*, `e`.* FROM `ada` AS `a`
 INNER JOIN `art` AS `e` ON (a.artid = e.artid AND e.artid=?)';

        return $this->fetchRowByStatement(new ClxProductNet_DbStatement($select, [$spracheid]));
    }
}
