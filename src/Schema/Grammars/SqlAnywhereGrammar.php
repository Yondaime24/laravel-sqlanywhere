<?php

namespace Yondaime\LaravelSqlAnywhere\Schema\Grammars;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Schema\Grammars\Grammar;
use Illuminate\Support\Fluent;

class SqlAnywhereGrammar extends Grammar
{
    /**
     * Laravel schema column modifiers that SQL Anywhere supports
     * or that we can safely translate.
     */
    protected $modifiers = [
        'Nullable',
        'Default',
        'Increment',
    ];

    /**
     * Determine whether a table exists.
     *
     * SQL Anywhere exposes table metadata through SYS.SYSTABLE.
     */
      public function compileTableExists($schema, $table)
      {
          $table = str_replace("'", "''", $table);

          return sprintf(
              "select count(*) as \"exists\"
              from SYS.SYSTABLE
              where table_name = '%s'",
              $table
          );
      }

      /**
       * Determine the columns for a table.
       *
       * Laravel 12 expects the result to contain:
       * name, type, type_name, nullable, default,
       * auto_increment, comment, generation.
       */
        public function compileColumns($schema, $table)
        {
          $table = str_replace("'", "''", $table);

          return sprintf(
              "
              SELECT
                  c.column_name AS \"name\",
                  d.domain_name AS \"type_name\",
                  d.domain_name AS \"type\",
                  CASE
                      WHEN c.nulls = 'Y' THEN 1
                      ELSE 0
                  END AS \"nullable\",
                  NULLIF(c.\"default\", '') AS \"default\",
                  CASE
                      WHEN LOWER(c.\"default\") = 'autoincrement' THEN 1
                      ELSE 0
                  END AS \"auto_increment\",
                  NULLIF(c.remarks, '') AS \"comment\",
                  NULL AS \"generation\"
              FROM SYS.SYSCOLUMN c
              INNER JOIN SYS.SYSDOMAIN d
                  ON d.domain_id = c.domain_id
              WHERE c.table_id = (
                  SELECT table_id
                  FROM SYS.SYSTABLE
                  WHERE table_name = '%s'
              )
              ORDER BY c.column_id
              ",
              $table
          );
        }

    /**
     * Create table.
     */
    public function compileCreate(Blueprint $blueprint, Fluent $command)
    {
        $columns = $this->getColumns($blueprint);

        // Explicit primary key:
        if ($primary = $this->getCommandByName($blueprint, 'primary')) {
            $columns[] = sprintf(
                'primary key (%s)',
                $this->columnize($primary->columns)
            );

            $primary->shouldBeSkipped = true;
        } else {
            $autoIncrementColumns = [];

            foreach ($blueprint->getColumns() as $column) {
                if ($column->autoIncrement) {
                    $autoIncrementColumns[] = $column->name;
                }
            }

            if (! empty($autoIncrementColumns)) {
                $columns[] = sprintf(
                    'primary key (%s)',
                    $this->columnize($autoIncrementColumns)
                );
            }
        }

        return sprintf(
            'create table %s (%s)',
            $this->wrapTable($blueprint),
            implode(', ', $columns)
        );
    }
    /**
     * Add a column.
     */
    public function compileAdd(Blueprint $blueprint, Fluent $command)
    {
        return sprintf(
            'alter table %s add %s',
            $this->wrapTable($blueprint),
            $this->getColumn($blueprint, $command->column)
        );
    }

    /**
     * Primary key.
     */
    public function compilePrimary(Blueprint $blueprint, Fluent $command)
    {
        return sprintf(
            'alter table %s add primary key (%s)',
            $this->wrapTable($blueprint),
            $this->columnize($command->columns)
        );
    }

    /**
     * Unique index / constraint.
     */
    public function compileUnique(Blueprint $blueprint, Fluent $command)
    {
        return sprintf(
            'create unique index %s on %s (%s)',
            $this->wrap($command->index),
            $this->wrapTable($blueprint),
            $this->columnize($command->columns)
        );
    }

    /**
     * Normal index.
     */
    public function compileIndex(Blueprint $blueprint, Fluent $command)
    {
        return sprintf(
            'create index %s on %s (%s)',
            $this->wrap($command->index),
            $this->wrapTable($blueprint),
            $this->columnize($command->columns)
        );
    }

    /**
     * Foreign key.
     */
    public function compileForeign(Blueprint $blueprint, Fluent $command)
    {
        $sql = sprintf(
            'alter table %s add constraint %s foreign key (%s) references %s (%s)',
            $this->wrapTable($blueprint),
            $this->wrap($command->index),
            $this->columnize($command->columns),
            $this->wrapTable($command->on),
            $this->columnize((array) $command->references)
        );

        if ($command->onDelete) {
            $sql .= ' on delete '.$command->onDelete;
        }

        if ($command->onUpdate) {
            $sql .= ' on update '.$command->onUpdate;
        }

        return $sql;
    }

    /**
     * Drop table.
     */
    public function compileDrop(Blueprint $blueprint, Fluent $command)
    {
        return 'drop table '.$this->wrapTable($blueprint);
    }

    public function compileDropColumn(Blueprint $blueprint, Fluent $command)
    {
        $columns = $command->columns;

        return collect($columns)->map(function ($column) use ($blueprint) {
            return sprintf(
                'alter table %s drop %s',
                $this->wrapTable($blueprint),
                $this->wrap($column)
            );
        })->implode('; ');
    }

    /**
     * Drop table if exists.
     */
    public function compileDropIfExists(Blueprint $blueprint, Fluent $command)
    {
        return 'drop table if exists '.$this->wrapTable($blueprint);
    }

    /**
     * Drop index.
     */
    public function compileDropIndex(Blueprint $blueprint, Fluent $command)
    {
        return sprintf(
            'drop index %s',
            $this->wrap($command->index)
        );
    }

    /**
     * Drop unique index.
     */
    public function compileDropUnique(Blueprint $blueprint, Fluent $command)
    {
        return $this->compileDropIndex($blueprint, $command);
    }

    /**
     * Drop foreign key.
     */
    public function compileDropForeign(Blueprint $blueprint, Fluent $command)
    {
        return sprintf(
            'alter table %s drop constraint %s',
            $this->wrapTable($blueprint),
            $this->wrap($command->index)
        );
    }

    /**
     * Rename table.
     */
    public function compileRename(Blueprint $blueprint, Fluent $command)
    {
        return sprintf(
            'alter table %s rename to %s',
            $this->wrapTable($blueprint),
            $this->wrapTable($command->to)
        );
    }

    /**
     * Rename column.
     */
    public function compileRenameColumn(Blueprint $blueprint, Fluent $command)
    {
        return sprintf(
            'alter table %s rename column %s to %s',
            $this->wrapTable($blueprint),
            $this->wrap($command->from),
            $this->wrap($command->to)
        );
    }

    /**
     * Integer.
     */
    protected function typeInteger(Fluent $column)
    {
        return 'INTEGER';
    }

    /**
     * Big integer.
     */
    protected function typeBigInteger(Fluent $column)
    {
        return 'BIGINT';
    }

    /**
     * Small integer.
     */
    protected function typeSmallInteger(Fluent $column)
    {
        return 'SMALLINT';
    }

    /**
     * Tiny integer.
     */
    protected function typeTinyInteger(Fluent $column)
    {
        return 'SMALLINT';
    }

    /**
     * Decimal.
     */
    protected function typeDecimal(Fluent $column)
    {
        $precision = $column->precision ?? 8;
        $scale = $column->scale ?? 2;

        return sprintf(
            'DECIMAL(%d, %d)',
            $precision,
            $scale
        );
    }

    /**
     * Float.
     */
    protected function typeFloat(Fluent $column)
    {
        return 'DOUBLE';
    }

    /**
     * Double.
     */
    protected function typeDouble(Fluent $column)
    {
        return 'DOUBLE';
    }

    /**
     * Boolean.
     *
     * SQL Anywhere can represent this safely as SMALLINT.
     */
    protected function typeBoolean(Fluent $column)
    {
        return 'SMALLINT';
    }

    /**
     * String.
     */
    protected function typeString(Fluent $column)
    {
        return 'VARCHAR('.$column->length.')';
    }

    /**
     * Char.
     */
    protected function typeChar(Fluent $column)
    {
        return 'CHAR('.$column->length.')';
    }

    /**
     * Text.
     */
    protected function typeText(Fluent $column)
    {
        return 'LONG VARCHAR';
    }

    /**
     * Medium text.
     */
    protected function typeMediumText(Fluent $column)
    {
        return 'LONG VARCHAR';
    }

    /**
     * Long text.
     */
    protected function typeLongText(Fluent $column)
    {
        return 'LONG VARCHAR';
    }

    /**
     * JSON.
     *
     * Your SQL Anywhere database stores JSON as LONG VARCHAR.
     */
    protected function typeJson(Fluent $column)
    {
        return 'LONG VARCHAR';
    }

    /**
     * JSONB.
     */
    protected function typeJsonb(Fluent $column)
    {
        return 'LONG VARCHAR';
    }

    /**
     * Date.
     */
    protected function typeDate(Fluent $column)
    {
        return 'DATE';
    }

    /**
     * Date time.
     */
    protected function typeDateTime(Fluent $column)
    {
        return 'TIMESTAMP';
    }

    /**
     * Date time with timezone.
     *
     * SQL Anywhere TIMESTAMP is used here because the application
     * schema does not require a separate timezone type.
     */
    protected function typeDateTimeTz(Fluent $column)
    {
        return 'TIMESTAMP';
    }

    /**
     * Timestamp.
     */
    protected function typeTimestamp(Fluent $column)
    {
        return 'TIMESTAMP';
    }

    /**
     * Timestamp with timezone.
     */
    protected function typeTimestampTz(Fluent $column)
    {
        return 'TIMESTAMP';
    }

    /**
     * Time.
     */
    protected function typeTime(Fluent $column)
    {
        return 'TIME';
    }

    /**
     * Binary.
     */
    protected function typeBinary(Fluent $column)
    {
        return 'LONG BINARY';
    }

    /**
     * UUID.
     */
    protected function typeUuid(Fluent $column)
    {
        return 'VARCHAR(36)';
    }

    /**
     * Enum.
     *
     * SQL Anywhere does not need a MySQL ENUM.
     * Store it as VARCHAR.
     */
    protected function typeEnum(Fluent $column)
    {
        return 'VARCHAR(255)';
    }

    /**
     * Identity / auto increment.
     *
     * Laravel's autoIncrement modifier is handled here.
     */
    protected function modifyIncrement(Blueprint $blueprint, Fluent $column)
    {
        if ($column->autoIncrement) {
            return ' identity';
        }

        return '';
    }

    /**
     * Nullable modifier.
     */
    protected function modifyNullable(Blueprint $blueprint, Fluent $column)
    {
        return $column->nullable ? ' null' : ' not null';
    }

    /**
     * Default modifier.
     */
    protected function modifyDefault(Blueprint $blueprint, Fluent $column)
    {
        if (is_null($column->default)) {
            return '';
        }

        $default = $column->default;

        if ($default instanceof \Illuminate\Database\Query\Expression) {
            $default = $default->getValue($this);
        } elseif (is_bool($default)) {
            $default = $default ? '1' : '0';
        } elseif (is_numeric($default)) {
            // Keep numeric defaults unquoted.
        } else {
            $default = "'".str_replace("'", "''", $default)."'";
        }

        return ' default '.$default;
    }
}