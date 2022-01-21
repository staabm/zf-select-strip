<?php

final class ClxProductNet_DbStatement
{
    /**
     * @var literal-string
     * @readonly
     */
    public $query;
    /**
     * @var array<int|string, scalar>
     * @readonly
     */
    public $values;

    /**
     * @param literal-string            $query
     * @param array<int|string, scalar> $values
     */
    public function __construct(string $query, array $values)
    {
        $this->query = $query;
        $this->values = $values;
    }
}
