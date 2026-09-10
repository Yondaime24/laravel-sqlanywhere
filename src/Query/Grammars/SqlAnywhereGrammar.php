<?php

namespace Yondaime\LaravelSqlAnywhere\Query\Grammars;

use Illuminate\Database\Query\Builder;
use Illuminate\Database\Query\Grammars\Grammar;

class SqlAnywhereGrammar extends Grammar
{
    /**
     * SQL Anywhere uses:
     *
     * TOP n
     * TOP n START AT m
     *
     * instead of Laravel's generic LIMIT/OFFSET syntax.
     */
    protected $selectComponents = [
        'aggregate',
        'columns',
        'from',
        'indexHint',
        'joins',
        'wheres',
        'groups',
        'havings',
        'orders',
        'lock',
    ];

    /**
     * SQL Anywhere operators.
     */
    protected $operators = [
        '=',
        '<',
        '>',
        '<=',
        '>=',
        '<>',
        '!=',
        '<=>',
        'like',
        'like binary',
        'not like',
        'ilike',
        '&',
        '|',
        '^',
        '<<',
        '>>',
    ];

    /**
     * Compile the columns portion of a SELECT.
     *
     * We inject TOP / START AT here because SQL Anywhere
     * requires the row limitation clause immediately after SELECT.
     */
    protected function compileColumns(Builder $query, $columns)
    {
        if (! is_null($query->aggregate)) {
            return;
        }

        $select = $query->distinct
            ? 'select distinct '
            : 'select ';

        $rowLimit = '';

        if (! is_null($query->limit)) {
            $rowLimit .= 'top '.((int) $query->limit);
        }

        if (! is_null($query->offset)) {
            if ($rowLimit === '') {
                $rowLimit .= 'top all';
            }

            $rowLimit .= ' start at '.(((int) $query->offset) + 1);
        }

        if ($rowLimit !== '') {
            $select .= $rowLimit.' ';
        }

        return $select.$this->columnize($columns);
    }

    /**
     * Laravel's limit has already been compiled into SELECT.
     */
    protected function compileLimit(Builder $query, $limit)
    {
        return '';
    }

    /**
     * Laravel's offset has already been compiled into SELECT.
     */
    protected function compileOffset(Builder $query, $offset)
    {
        return '';
    }

    /**
     * SQL Anywhere does not use MySQL's backticks.
     * Laravel's base Grammar already uses double quotes,
     * which is correct for SQL Anywhere.
     */
    protected function wrapValue($value)
    {
        if ($value !== '*') {
            return '"'.str_replace('"', '""', $value).'"';
        }

        return $value;
    }

    /**
     * SQL Anywhere timestamp format.
     */
    public function getDateFormat()
    {
        return 'Y-m-d H:i:s';
    }
}