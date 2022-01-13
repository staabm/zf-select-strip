<?php

namespace ZfSelectRectorTest;

class Application_Model_Mapper_Artfarbe extends Clx_Model_Mapper_Abstract
{
    public function simpleSelect()
    {
        $dbTable = new \DbTable();
        $select = $dbTable->select();

        return $this->fetchRow($select);
    }
}
-----

namespace ZfSelectRectorTest;

class Application_Model_Mapper_Artfarbe extends Clx_Model_Mapper_Abstract
{
    public function simpleSelect()
    {
        $dbTable = new \DbTable();
        $select = 'SELECT `ada`.* FROM `ada`';

        return $this->fetchRowByStatement(new ClxProductNet_DbStatement($select, []));
    }
}
