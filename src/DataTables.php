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

    public function join(string $table, string $cond, string $type = ''): self
    {
        $this->builder->join($table, $cond, $type);
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
        if (!$search) {
            return;
        }

        if (!empty($this->searchableColumns)) {

            $this->builder->groupStart();

            $first = true;
            foreach ($this->searchableColumns as $field) {
                if ($first) {
                    $this->builder->like($field, $search);
                    $first = false;
                } else {
                    $this->builder->orLike($field, $search);
                }
            }

            $this->builder->groupEnd();
            return;
        }

        $columns = $this->request->getGetPost('columns');
        if (!$columns) {
            return;
        }

        $likes = [];

        foreach ($columns as $col) {
            if (($col['searchable'] ?? 'false') !== 'true') {
                continue;
            }

            $field = $col['data'];

            // skip alias
            if (!str_contains($field, '.')) {
                continue;
            }

            $likes[] = $field;
        }

        if (empty($likes)) {
            return;
        }

        $this->builder->groupStart();
        foreach ($likes as $i => $field) {
            if ($i === 0) {
                $this->builder->like($field, $search);
            } else {
                $this->builder->orLike($field, $search);
            }
        }
        $this->builder->groupEnd();
    }

    protected function applyOrdering(): void
    {
        if ($this->ordered) return;

        $order = $this->request->getGetPost('order')[0] ?? null;
        if (!$order) return;

        $columns = $this->request->getGetPost('columns');
        $field   = $columns[$order['column']]['data'];

        if (!str_contains($field, '.')) {
            return;
        }

        $this->builder->orderBy($field, $order['dir']);
    }

    protected function applyLimit(): void
    {
        $length = (int) $this->request->getGetPost('length');
        $start  = (int) $this->request->getGetPost('start');

        if ($length > 0) {
            $this->builder->limit($length, $start);
        }
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
}
