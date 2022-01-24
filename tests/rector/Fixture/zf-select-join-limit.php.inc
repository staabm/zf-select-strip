<?php

namespace ZfSelectRectorTest;

use Clx_Model_Mapper_Abstract;

class Application_Model_Mapper_Artfarbe extends Clx_Model_Mapper_Abstract
{
    public function simpleSelect()
    {
        $dbTable = new \DbTable();
        $select = $dbTable->select();
        $select->from('ada as a');
        $select->limit(1, 2);

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
        $select = 'SELECT `a`.* FROM `ada` AS `a` LIMIT 1 OFFSET 2';

        return $this->fetchRowByStatement(new ClxProductNet_DbStatement($select, []));
    }
}
