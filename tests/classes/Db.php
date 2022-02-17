<?php

abstract class Clx_Model_Mapper_Abstract
{
    /**
     * @see Zend_Db_Table_Abstract::fetchAll
     *
     * @param string|array<string|int, scalar|array<int|string>>|Zend_Db_Table_Select $where  OPTIONAL An SQL WHERE clause or Zend_Db_Table_Select object
     * @param string|array                                                            $order  OPTIONAL An SQL ORDER clause
     * @param int                                                                     $count  OPTIONAL An SQL LIMIT count
     * @param int                                                                     $offset OPTIONAL An SQL LIMIT offset
     *
     * @return Clx_Model_Iterator<T>
     */
    final public function fetchAll($where = null, $order = null, $count = null, $offset = null)
    {
    }

    /**
     * @see Zend_Db_Table_Abstract::fetchRow
     *
     * @param string|array<string|int, scalar|array<int|string>>|Zend_Db_Table_Select|null $where OPTIONAL An SQL WHERE clause or Zend_Db_Table_Select object
     * @param string|array|null                                                            $order OPTIONAL An SQL ORDER clause
     *
     * @return T|null
     */
    final public function fetchRow($where = null, $order = null)
    {
    }

    /**
     * @return Clx_Model_Iterator<T>
     */
    final public function fetchAllByStatement(ClxProductNet_DbStatement $stmt)
    {
    }

    /**
     * @return int|string
     *
     * @throws Exception
     */
    public function t_getSpracheId()
    {
        return 456;
    }
}
