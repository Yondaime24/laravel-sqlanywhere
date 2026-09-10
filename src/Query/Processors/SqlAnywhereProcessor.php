<?php

namespace Yondaime\LaravelSqlAnywhere\Query\Processors;

use Illuminate\Database\Query\Builder;
use Illuminate\Database\Query\Processors\Processor;

class SqlAnywhereProcessor extends Processor
{
    public function processInsertGetId(
        Builder $query,
        $sql,
        $values,
        $sequence = null
    ) {
        $query->getConnection()->insert($sql, $values);

        return $query->getConnection()
            ->selectOne('SELECT @@identity AS id')
            ->id;
    }
}