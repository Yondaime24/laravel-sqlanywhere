<?php

namespace Yondaime\LaravelSqlAnywhere\Connections;

use Yondaime\LaravelSqlAnywhere\Query\Grammars\SqlAnywhereGrammar as QueryGrammar;
use Yondaime\LaravelSqlAnywhere\Schema\Grammars\SqlAnywhereGrammar as SchemaGrammar;
use Illuminate\Database\Connection as BaseConnection;
use Yondaime\LaravelSqlAnywhere\Query\Processors\SqlAnywhereProcessor;

class SqlAnywhereConnection extends BaseConnection
{
    public function __construct(
        $pdo,
        $database = '',
        $tablePrefix = '',
        array $config = []
    ) {
        parent::__construct(
            $pdo,
            $database,
            $tablePrefix,
            $config
        );

        // Laravel does not initialize the schema grammar
        // automatically for custom connections.
        $this->useDefaultSchemaGrammar();
    }

    protected function getDefaultQueryGrammar()
    {
        return new QueryGrammar($this);
    }

    protected function getDefaultSchemaGrammar()
    {
        return new SchemaGrammar($this);
    }

    protected function getDefaultPostProcessor()
    {
        return new SqlAnywhereProcessor;
    }
}