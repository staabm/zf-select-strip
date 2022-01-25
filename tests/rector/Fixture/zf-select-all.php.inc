<?php

namespace ZfSelectRectorTest;

use Clx_Model_Mapper_Abstract;

class Application_Model_Mapper_Artfarbe extends Clx_Model_Mapper_Abstract
{
    public function simpleSelectAll()
    {
        $dbTable = new \DbTable();
        $select = $dbTable->select();

        return $this->fetchAll($select);
    }
}
-----
<?php

namespace ZfSelectRectorTest;

use Clx_Model_Mapper_Abstract;

class Application_Model_Mapper_Artfarbe extends Clx_Model_Mapper_Abstract
{
    public function simpleSelectAll()
    {
        $dbTable = new \DbTable();
        $select = 'SELECT `ada`.* FROM `ada`';

        return $this->fetchAllByStatement(new ClxProductNet_DbStatement($select, []));
    }
}
