<?php

namespace ZfSelectRectorTest;

use Clx_Model_Mapper_Abstract;

class Application_Model_Mapper_Artfarbe extends Clx_Model_Mapper_Abstract
{
    public function simpleSelect()
    {
        $dbTable = new \DbTable();
        $select = $dbTable->select(true);
        $select->join('art as e', 'a.artid = e.artid', []);

        return $this->fetchRow($select);
    }
}
-----
<?php

namespace ZfSelectRectorTest;

use Clx_Model_Mapper_Abstract;

class Application_Model_Mapper_Artfarbe extends Clx_Model_Mapper_Abstract
{
    public function simpleSelect()
    {
        $dbTable = new \DbTable();
        $select = 'SELECT `ada`.* FROM `ada`
 INNER JOIN `art` AS `e` ON a.artid = e.artid';

        return $this->fetchRowByStatement(new ClxProductNet_DbStatement($select, []));
    }
}
