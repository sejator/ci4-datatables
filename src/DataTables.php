<?php

namespace Sejator\DataTables;

use Config\Database;
use Config\Services;

class DataTables
{
    protected $db;
    protected $builder;
    protected $baseBuilder;
    protected $request;

    protected bool $ordered = false;
    protected bool $debug   = false;

    protected ?string $groupCountField = null;

    protected array $addColumns  = [];
    protected array $editColumns = [];
    protected array $hidden      = [];
    protected array $searchableColumns = [];
    protected array $orderableColumns = [];
    protected array $likeConditions = [];
    protected array $relations = [];

    public function __construct()
    {
        $this->request = Services::request();
    }

    public static function from(string $table): self
    {
        return (new self())->table($table);
    }

    public function table(string $table): self
    {
        $this->db = Database::connect();
        $this->builder = $this->db->table($table);

        return $this;
    }

    public function select(string $columns): self
    {
        $this->builder->select($columns);
        return $this;
    }

    public function where($column, $operator = null, $value = null): self
    {
        if (func_num_args() === 2) {
            $this->builder->where($column, $operator);
        } else {
            $this->builder->where($column, $operator, $value);
        }
        return $this;
    }

    public function whereNull(string $column): self
    {
        $this->builder->where("{$column} IS NULL", null, false);
        return $this;
    }

    public function whereIn(string $column, array $values): self
    {
        $this->builder->whereIn($column, $values);
        return $this;
    }

    public function whereNotIn(string $column, array $values): self
    {
        $this->builder->whereNotIn($column, $values);
        return $this;
    }

    public function whereLike(string $column, string $value): self
    {
        if ($value !== '') {
            $this->builder->like($column, $value);
        }
        return $this;
    }

    public function whereYear(string $column, int $year): self
    {
        $this->builder->where("YEAR($column)", $year, false);
        return $this;
    }

    public function whereRaw(string $sql): self
    {
        $this->builder->where($sql, null, false);
        return $this;
    }

    public function when($value, callable $callback): self
    {
        if (!empty($value)) {
            $callback($this, $value);
        }
        return $this;
    }

    public function like(string $column, string $value, string $position = 'both'): self
    {
        if ($value === '' || $value === null) {
            return $this;
        }

        $this->likeConditions[] = [
            'column'   => $column,
            'value'    => $value,
            'position' => $position,
        ];

        if (preg_match('/\(|\)/', $column)) {
            $likeValue = match ($position) {
                'before' => "%{$value}",
                'after'  => "{$value}%",
                default  => "%{$value}%"
            };

            $this->builder->where("{$column} LIKE '{$likeValue}'", null, false);
        } else {
            $this->builder->like($column, $value, $position);
        }

        return $this;
    }

    public function join(string $table, string $cond, string $type = ''): self
    {
        $this->builder->join($table, $cond, $type);
        return $this;
    }

    public function withRelation(
        string $foreignKey,
        string $localKey,
        string $table,
        string $columns = '*',
        array $options = []
    ): self {
        $this->relations[] = array_merge([
            'foreignKey'   => $foreignKey,
            'localKey'     => $localKey,
            'table'        => $table,
            'columns'      => $columns,
            'nested'       => [],
            'onlyIfUsedIn' => null,
        ], $options);

        return $this;
    }

    public function groupBy($fields): self
    {
        $this->builder->groupBy($fields);
        return $this;
    }

    public function orderBy(string $field, string $direction = 'ASC'): self
    {
        $this->builder->orderBy($field, $direction);
        $this->ordered = true;
        return $this;
    }

    public function orderable(array $columns): self
    {
        $this->orderableColumns = $columns;
        return $this;
    }

    public function countDistinct(string $field): self
    {
        $this->groupCountField = $field;
        return $this;
    }

    public function searchable(array $columns): self
    {
        $this->searchableColumns = $columns;
        return $this;
    }

    public function addColumn(string $name, callable $callback): self
    {
        $this->addColumns[$name] = $callback;
        return $this;
    }

    public function editColumn(string $name, callable $callback): self
    {
        $this->editColumns[$name] = $callback;
        return $this;
    }

    public function hidden(array $columns): self
    {
        $this->hidden = $columns;
        return $this;
    }

    public function reset(): self
    {
        $this->builder = clone $this->baseBuilder;

        $this->ordered = false;
        $this->groupCountField = null;
        $this->addColumns = [];
        $this->editColumns = [];
        $this->hidden = [];
        $this->searchableColumns = [];
        $this->orderableColumns  = [];
        $this->debug = false;

        return $this;
    }

    public function debug(bool $state = true): self
    {
        $this->debug = $state;
        return $this;
    }

    public function toSql(): string
    {
        $sql   = $this->builder->getCompiledSelect(false);
        $binds = $this->builder->getBinds();

        if (empty($binds)) {
            return $sql;
        }

        foreach ($binds as $bind) {

            if (is_array($bind)) {
                $values = array_map(function ($item) {
                    if (is_numeric($item)) {
                        return $item;
                    }
                    if ($item === null) {
                        return 'NULL';
                    }
                    return "'" . str_replace("'", "''", $item) . "'";
                }, $bind);

                $value = '(' . implode(', ', $values) . ')';
            } elseif (is_numeric($bind)) {
                $value = $bind;
            } elseif ($bind === null) {
                $value = 'NULL';
            } else {
                $value = "'" . str_replace("'", "''", $bind) . "'";
            }

            $sql = preg_replace('/\?/', $value, $sql, 1);
        }

        return $sql;
    }

    public function logSql(): self
    {
        log_message('debug', 'DataTables SQL: ' . $this->toSql());
        return $this;
    }

    /* =====================================================
     |  FINAL OUTPUT
     ===================================================== */
    public function make()
    {
        $this->baseBuilder = clone $this->builder;

        $this->applySearch();
        $this->applyOrdering();

        $dataBuilder = clone $this->builder;

        // DEBUG MODE
        if ($this->debug) {
            return Services::response()->setJSON([
                'debug' => true,
                'queries' => [
                    'data' => $this->debugSql(clone $dataBuilder),
                    'count_all' => $this->debugSql(
                        clone $this->baseBuilder
                    ),
                    'count_filtered' => $this->debugSql(
                        clone $this->builder
                    ),
                ]
            ]);
        }

        $recordsTotal    = $this->countBuilder(clone $this->baseBuilder);
        $recordsFiltered = $this->countBuilder(clone $this->builder);

        $length = (int) $this->request->getGetPost('length');
        $start  = (int) $this->request->getGetPost('start');

        if ($length > 0) {
            $dataBuilder->limit($length, $start);
        }

        $data = $dataBuilder->get()->getResultArray();

        if (!empty($this->relations)) {
            $data = $this->loadRelations($data);
        }

        $data = $this->transform($data);

        return Services::response()->setJSON([
            'draw'            => (int) $this->request->getGetPost('draw'),
            'recordsTotal'    => $recordsTotal,
            'recordsFiltered' => $recordsFiltered,
            'data'            => $data,
        ]);
    }

    public function draw()
    {
        return $this->make();
    }

    // DEBUGGING HELPERS
    public function dd()
    {
        dd($this->getDebugQuery());
    }

    public function ddSql()
    {
        dd($this->toSql());
    }

    // =====================================================
    // Helpers
    // =====================================================

    protected function applySearch(): void
    {
        $search = $this->request->getGetPost('search')['value'] ?? null;
        if (!$search) return;

        if (empty($this->searchableColumns)) return;

        $this->builder->groupStart();
        foreach ($this->searchableColumns as $i => $field) {
            if ($i === 0) $this->builder->like($field, $search);
            else $this->builder->orLike($field, $search);
        }
        $this->builder->groupEnd();
    }

    protected function applyOrdering(): void
    {
        if ($this->ordered) return;

        $order = $this->request->getGetPost('order')[0] ?? null;
        if (!$order) return;

        $columns = $this->request->getGetPost('columns');
        $field   = $columns[$order['column']]['data'] ?? null;

        if (!$field) return;

        if (!in_array($field, $this->orderableColumns, true)) {
            return;
        }

        if (!str_contains($field, '.')) {
            return;
        }

        $dir = strtolower($order['dir']) === 'desc' ? 'DESC' : 'ASC';

        $this->builder->orderBy($field, $dir);
    }

    protected function transform(array $rows): array
    {
        foreach ($rows as &$row) {
            $obj = (object) $row;

            foreach ($this->editColumns as $col => $cb) {
                $row[$col] = $cb($obj);
            }

            foreach ($this->addColumns as $col => $cb) {
                $row[$col] = $cb($obj);
            }

            foreach ($this->hidden as $hide) {
                unset($row[$hide]);
            }
        }
        return $rows;
    }

    protected function cloneBuilder()
    {
        return clone $this->builder;
    }

    protected function countAll(): int
    {
        return $this->countBuilder(clone $this->baseBuilder);
    }

    protected function countFiltered(): int
    {
        return $this->countBuilder(clone $this->builder);
    }

    protected function countBuilder($builder): int
    {
        $countBuilder = clone $builder;

        $sql = $countBuilder->getCompiledSelect(false);

        if (
            $this->groupCountField ||
            stripos($sql, 'GROUP BY') !== false
        ) {
            $query = $this->db->query(
                "SELECT COUNT(*) AS total FROM ({$sql}) AS t"
            );

            return (int) $query->getRow()->total;
        }

        return (int) $countBuilder->countAllResults(false);
    }

    protected function getDebugQuery(): array
    {
        return [
            'sql_raw' => $this->builder->getCompiledSelect(false),
            'binds'   => $this->builder->getBinds(),
            'sql'     => $this->toSql(),
        ];
    }

    protected function debugSql($builder): string
    {
        $sql   = $builder->getCompiledSelect(false);
        $binds = $builder->getBinds();

        foreach ($binds as $bind) {

            if (is_array($bind)) {
                $values = array_map(function ($item) {
                    if (is_numeric($item)) {
                        return $item;
                    }
                    if ($item === null) {
                        return 'NULL';
                    }
                    return "'" . str_replace("'", "''", $item) . "'";
                }, $bind);

                $value = '(' . implode(', ', $values) . ')';
            } elseif (is_numeric($bind)) {
                $value = $bind;
            } elseif ($bind === null) {
                $value = 'NULL';
            } else {
                $value = "'" . str_replace("'", "''", $bind) . "'";
            }

            $sql = preg_replace('/\?/', $value, $sql, 1);
        }

        return $sql;
    }

    protected function isRelationNeeded(array $relation): bool
    {
        if (empty($relation['onlyIfUsedIn'])) {
            return true;
        }

        $usedColumns = array_merge(
            array_keys($this->addColumns),
            array_keys($this->editColumns)
        );

        foreach ($relation['onlyIfUsedIn'] as $col) {
            if (in_array($col, $usedColumns, true)) {
                return true;
            }
        }

        return false;
    }

    protected function loadRelations(array $rows): array
    {
        if (empty($rows)) return $rows;

        foreach ($this->relations as $relation) {

            if (!$this->isRelationNeeded($relation)) {
                continue;
            }

            $rows = $this->loadSingleRelation($rows, $relation);
        }

        return $rows;
    }

    protected function loadSingleRelation(array $rows, array $relation): array
    {
        $localKey   = $relation['localKey'];
        $foreignKey = $relation['foreignKey'];
        $table      = $relation['table'];
        $columns    = $relation['columns'];
        $nested     = $relation['nested'];

        $ids = array_unique(array_filter(array_column($rows, $localKey)));
        if (empty($ids)) return $rows;

        $related = $this->db->table($table)
            ->select($columns)
            ->whereIn($foreignKey, $ids)
            ->get()
            ->getResultArray();

        if (!empty($nested)) {
            foreach ($nested as $nestedRelation) {
                $related = $this->loadSingleRelation(
                    $related,
                    $nestedRelation
                );
            }
        }

        $grouped = [];
        foreach ($related as $rel) {
            $grouped[$rel[$foreignKey]][] = $rel;
        }

        foreach ($rows as &$row) {
            $row[$table] = $grouped[$row[$localKey]] ?? [];
        }

        return $rows;
    }
}
