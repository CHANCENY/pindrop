<?php

declare(strict_types=1);

namespace Simp\Pindrop\Database;

/**
 * QueryBuilder
 *
 * Fluent, immutable-style SQL builder. Developers never write raw SQL.
 * The builder compiles to a prepared statement executed by Database.
 *
 * Flow:
 *   DatabaseService::table('wiki_pages')
 *     ->select(['id', 'title', 'status'])
 *     ->where('status', '=', 'published')
 *     ->orderBy('created_at', 'DESC')
 *     ->limit(10)
 *     ->get();
 *
 * All public methods return $this for chaining except terminal methods:
 *   ->get()        → array of rows
 *   ->first()      → single row or null
 *   ->count()      → int
 *   ->insert()     → int (last insert id)
 *   ->update()     → int (affected rows)
 *   ->delete()     → int (affected rows)
 *   ->exists()     → bool
 *   ->value(col)   → scalar
 */
class QueryBuilder
{
    // ── Query state ──────────────────────────────────────────────────────────
    private array  $columns    = ['*'];
    private array  $wheres     = [];
    private array  $orWheres   = [];
    private array  $orderBys   = [];
    private array  $joins      = [];
    private array  $groupBys   = [];
    private array  $havings    = [];
    private ?int   $limitVal   = null;
    private ?int   $offsetVal  = null;
    private array  $bindings   = [];
    private string $operation  = 'select';

    // ── Allowed operations for validation ───────────────────────────────────
    private const ALLOWED_OPERATORS = ['=', '!=', '<>', '<', '>', '<=', '>=',
        'LIKE', 'NOT LIKE', 'IN', 'NOT IN', 'IS NULL', 'IS NOT NULL',
        'BETWEEN', 'NOT BETWEEN'];

    private const ALLOWED_DIRECTIONS = ['ASC', 'DESC'];

    public function __construct(
        private readonly string   $table,
        private readonly Database $database,
        private readonly DatabasePermissionGuard $guard,
        private readonly string   $pluginContext = ''
    ) {}

    // ── Column selection ─────────────────────────────────────────────────────

    public function select(array $columns = ['*']): static
    {
        $this->columns = $columns;
        return $this;
    }

    // ── WHERE clauses ────────────────────────────────────────────────────────

    public function where(string $column, string $operator, mixed $value = null): static
    {
        $operator = $this->validateOperator($operator);

        if (in_array($operator, ['IS NULL', 'IS NOT NULL'], true)) {
            $this->wheres[] = [
                'sql'      => $this->quoteColumn($column) . ' ' . $operator,
                'bindings' => [],
                'type'     => 'AND',
            ];
            return $this;
        }

        if (in_array($operator, ['IN', 'NOT IN'], true)) {
            if (!is_array($value) || empty($value)) {
                throw new \InvalidArgumentException("IN/NOT IN requires a non-empty array value.");
            }
            $placeholders = implode(', ', array_fill(0, count($value), '?'));
            $this->wheres[] = [
                'sql'      => $this->quoteColumn($column) . " $operator ($placeholders)",
                'bindings' => array_values($value),
                'type'     => 'AND',
            ];
            return $this;
        }

        if ($operator === 'BETWEEN' || $operator === 'NOT BETWEEN') {
            if (!is_array($value) || count($value) !== 2) {
                throw new \InvalidArgumentException("BETWEEN requires an array of exactly 2 values.");
            }
            $this->wheres[] = [
                'sql'      => $this->quoteColumn($column) . " $operator ? AND ?",
                'bindings' => array_values($value),
                'type'     => 'AND',
            ];
            return $this;
        }

        $this->wheres[] = [
            'sql'      => $this->quoteColumn($column) . " $operator ?",
            'bindings' => [$value],
            'type'     => 'AND',
        ];
        return $this;
    }

    public function orWhere(string $column, string $operator, mixed $value = null): static
    {
        $operator = $this->validateOperator($operator);
        $this->wheres[] = [
            'sql'      => $this->quoteColumn($column) . " $operator ?",
            'bindings' => [$value],
            'type'     => 'OR',
        ];
        return $this;
    }

    public function whereNull(string $column): static
    {
        return $this->where($column, 'IS NULL');
    }

    public function whereNotNull(string $column): static
    {
        return $this->where($column, 'IS NOT NULL');
    }

    public function whereIn(string $column, array $values): static
    {
        return $this->where($column, 'IN', $values);
    }

    public function whereBetween(string $column, mixed $min, mixed $max): static
    {
        return $this->where($column, 'BETWEEN', [$min, $max]);
    }

    public function whereRaw(string $sql, array $bindings = []): static
    {
        $this->wheres[] = [
            'sql'      => $sql,
            'bindings' => $bindings,
            'type'     => 'AND',
        ];
        return $this;
    }

    // ── JOIN ─────────────────────────────────────────────────────────────────

    /**
     * Add a JOIN clause.
     * NOTE: joined tables must belong to the same plugin OR the join target
     * must be explicitly allowed by the permission guard.
     */
    public function join(string $table, string $first, string $operator, string $second, string $type = 'INNER'): static
    {
        $type = strtoupper($type);
        if (!in_array($type, ['INNER', 'LEFT', 'RIGHT', 'CROSS'], true)) {
            throw new \InvalidArgumentException("Invalid join type: $type");
        }
        $this->joins[] = "$type JOIN `$table` ON " .
            $this->quoteColumn($first) . " $operator " . $this->quoteColumn($second);
        return $this;
    }

    public function leftJoin(string $table, string $first, string $operator, string $second): static
    {
        return $this->join($table, $first, $operator, $second, 'LEFT');
    }

    // ── ORDER / LIMIT / OFFSET ───────────────────────────────────────────────

    public function orderBy(string $column, string $direction = 'ASC'): static
    {
        $direction = strtoupper($direction);
        if (!in_array($direction, self::ALLOWED_DIRECTIONS, true)) {
            throw new \InvalidArgumentException("Invalid ORDER BY direction: $direction");
        }
        $this->orderBys[] = $this->quoteColumn($column) . ' ' . $direction;
        return $this;
    }

    public function latest(string $column = 'created_at'): static
    {
        return $this->orderBy($column, 'DESC');
    }

    public function oldest(string $column = 'created_at'): static
    {
        return $this->orderBy($column, 'ASC');
    }

    public function limit(int $limit): static
    {
        if ($limit < 0) throw new \InvalidArgumentException("LIMIT cannot be negative.");
        $this->limitVal = $limit;
        return $this;
    }

    public function offset(int $offset): static
    {
        if ($offset < 0) throw new \InvalidArgumentException("OFFSET cannot be negative.");
        $this->offsetVal = $offset;
        return $this;
    }

    public function forPage(int $page, int $perPage = 15): static
    {
        return $this->limit($perPage)->offset(($page - 1) * $perPage);
    }

    // ── GROUP BY / HAVING ────────────────────────────────────────────────────

    public function groupBy(string ...$columns): static
    {
        foreach ($columns as $col) {
            $this->groupBys[] = $this->quoteColumn($col);
        }
        return $this;
    }

    public function having(string $column, string $operator, mixed $value): static
    {
        $operator = $this->validateOperator($operator);
        $this->havings[] = $this->quoteColumn($column) . " $operator ?";
        $this->bindings[] = $value;
        return $this;
    }

    // ── Terminal — READ ──────────────────────────────────────────────────────

    /** Execute SELECT and return all matching rows. */
    public function get(): array
    {
        $this->guard->authorize('select', $this->table);
        [$sql, $bindings] = $this->compileSelect();
        $stmt = $this->database->query($sql, ...$bindings);
        return $stmt instanceof \PDOStatement ? $stmt->fetchAll(\PDO::FETCH_ASSOC) : [];
    }

    /** Execute SELECT and return first matching row or null. */
    public function first(): ?array
    {
        $this->guard->authorize('select', $this->table);
        $this->limitVal = 1;
        [$sql, $bindings] = $this->compileSelect();
        $stmt = $this->database->query($sql, ...$bindings);
        if (!$stmt instanceof \PDOStatement) return null;
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);
        return $row !== false ? $row : null;
    }

    /** Return a single column value from the first matching row. */
    public function value(string $column): mixed
    {
        $this->guard->authorize('select', $this->table);
        $this->columns  = [$column];
        $this->limitVal = 1;
        [$sql, $bindings] = $this->compileSelect();
        $stmt = $this->database->query($sql, ...$bindings);
        if (!$stmt instanceof \PDOStatement) return null;
        $val = $stmt->fetchColumn();
        return $val !== false ? $val : null;
    }

    /** Return a flat array of values from a single column across all rows. */
    public function pluck(string $column): array
    {
        $this->guard->authorize('select', $this->table);
        $this->columns = [$column];
        [$sql, $bindings] = $this->compileSelect();
        $stmt = $this->database->query($sql, ...$bindings);
        if (!$stmt instanceof \PDOStatement) return [];
        return $stmt->fetchAll(\PDO::FETCH_COLUMN);
    }

    /** Count matching rows. */
    public function count(string $column = '*'): int
    {
        $this->guard->authorize('select', $this->table);
        $col = $column === '*' ? '*' : $this->quoteColumn($column);
        $this->columns = ["COUNT($col) AS _count"];
        [$sql, $bindings] = $this->compileSelect();
        $stmt = $this->database->query($sql, ...$bindings);
        if (!$stmt instanceof \PDOStatement) return 0;
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);
        return (int)($row['_count'] ?? 0);
    }

    /** Check if any rows match the current WHERE conditions. */
    public function exists(): bool
    {
        return $this->count() > 0;
    }

    /**
     * Paginate results.
     * Returns ['data' => rows[], 'total' => int, 'per_page' => int,
     *          'current_page' => int, 'last_page' => int]
     */
    public function paginate(int $page = 1, int $perPage = 15): array
    {
        $total = $this->count();
        $rows  = $this->forPage($page, $perPage)->get();
        return [
            'data'         => $rows,
            'total'        => $total,
            'per_page'     => $perPage,
            'current_page' => $page,
            'last_page'    => (int)ceil($total / $perPage),
        ];
    }

    // ── Terminal — WRITE ─────────────────────────────────────────────────────

    /** Insert a row. Returns the last insert ID. */
    public function insert(array $data): int
    {
        $this->guard->authorize('insert', $this->table);
        if (empty($data)) {
            throw new \InvalidArgumentException("insert() requires at least one column.");
        }
        $columns      = array_keys($data);
        $placeholders = implode(', ', array_fill(0, count($columns), '?'));
        $colList      = implode('`, `', $columns);
        $sql          = "INSERT INTO `{$this->table}` (`$colList`) VALUES ($placeholders)";
        $this->database->query($sql, ...array_values($data));
        return (int)$this->database->getPdo()->lastInsertId();
    }

    /** Insert a row or ignore on duplicate key. Returns last insert ID. */
    public function insertIgnore(array $data): int
    {
        $this->guard->authorize('insert', $this->table);
        $columns      = array_keys($data);
        $placeholders = implode(', ', array_fill(0, count($columns), '?'));
        $colList      = implode('`, `', $columns);
        $sql          = "INSERT IGNORE INTO `{$this->table}` (`$colList`) VALUES ($placeholders)";
        $this->database->query($sql, ...array_values($data));
        return (int)$this->database->getPdo()->lastInsertId();
    }

    /** Upsert (INSERT … ON DUPLICATE KEY UPDATE). Returns last insert ID. */
    public function upsert(array $data, array $updateColumns = []): int
    {
        $this->guard->authorize('insert', $this->table);
        $this->guard->authorize('update', $this->table);
        $columns      = array_keys($data);
        $placeholders = implode(', ', array_fill(0, count($columns), '?'));
        $colList      = implode('`, `', $columns);
        $updateCols   = empty($updateColumns) ? $columns : $updateColumns;
        $updateParts  = array_map(fn($c) => "`$c` = VALUES(`$c`)", $updateCols);
        $sql          = "INSERT INTO `{$this->table}` (`$colList`) VALUES ($placeholders)"
            . " ON DUPLICATE KEY UPDATE " . implode(', ', $updateParts);
        $this->database->query($sql, ...array_values($data));
        return (int)$this->database->getPdo()->lastInsertId();
    }

    /** Update rows matching current WHERE conditions. Returns affected rows. */
    public function update(array $data): int
    {
        $this->guard->authorize('update', $this->table);
        if (empty($data)) {
            throw new \InvalidArgumentException("update() requires at least one column.");
        }
        $setParts = [];
        $setBindings = [];
        foreach ($data as $col => $val) {
            $setParts[]    = $this->quoteColumn($col) . ' = ?';
            $setBindings[] = $val;
        }
        [$whereClause, $whereBindings] = $this->compileWhere();
        $sql      = "UPDATE `{$this->table}` SET " . implode(', ', $setParts) . $whereClause;
        $bindings = array_merge($setBindings, $whereBindings);
        $stmt     = $this->database->query($sql, ...$bindings);
        return $stmt instanceof \PDOStatement ? $stmt->rowCount() : 0;
    }

    /** Delete rows matching current WHERE conditions. Returns affected rows. */
    public function delete(): int
    {
        $this->guard->authorize('delete', $this->table);
        [$whereClause, $whereBindings] = $this->compileWhere();
        $sql  = "DELETE FROM `{$this->table}`" . $whereClause;
        $stmt = $this->database->query($sql, ...$whereBindings);
        return $stmt instanceof \PDOStatement ? $stmt->rowCount() : 0;
    }

    // ── SQL Compilation ──────────────────────────────────────────────────────

    private function compileSelect(): array
    {
        $columns = implode(', ', array_map(function (string $col): string {
            return $col === '*' || str_contains($col, '(') || str_contains($col, '.')
                ? $col
                : $this->quoteColumn($col);
        }, $this->columns));

        $sql = "SELECT $columns FROM `{$this->table}`";
        $bindings = [];

        if (!empty($this->joins)) {
            $sql .= ' ' . implode(' ', $this->joins);
        }

        [$whereClause, $whereBindings] = $this->compileWhere();
        $sql      .= $whereClause;
        $bindings  = array_merge($bindings, $whereBindings);

        if (!empty($this->groupBys)) {
            $sql .= ' GROUP BY ' . implode(', ', $this->groupBys);
        }

        if (!empty($this->havings)) {
            $sql .= ' HAVING ' . implode(' AND ', $this->havings);
            $bindings = array_merge($bindings, $this->bindings);
        }

        if (!empty($this->orderBys)) {
            $sql .= ' ORDER BY ' . implode(', ', $this->orderBys);
        }

        if ($this->limitVal !== null) {
            $sql .= ' LIMIT ' . $this->limitVal;
        }

        if ($this->offsetVal !== null) {
            $sql .= ' OFFSET ' . $this->offsetVal;
        }

        return [$sql, $bindings];
    }

    private function compileWhere(): array
    {
        if (empty($this->wheres)) {
            return ['', []];
        }

        $parts    = [];
        $bindings = [];
        foreach ($this->wheres as $i => $where) {
            $parts[]  = ($i === 0 ? '' : $where['type'] . ' ') . $where['sql'];
            $bindings = array_merge($bindings, $where['bindings']);
        }

        return [' WHERE ' . implode(' ', $parts), $bindings];
    }

    // ── Helpers ──────────────────────────────────────────────────────────────

    private function quoteColumn(string $column): string
    {
        // Handle table.column notation
        if (str_contains($column, '.')) {
            [$tbl, $col] = explode('.', $column, 2);
            return "`$tbl`.`$col`";
        }
        return "`$column`";
    }

    private function validateOperator(string $operator): string
    {
        $op = strtoupper(trim($operator));
        if (!in_array($op, self::ALLOWED_OPERATORS, true)) {
            throw new \InvalidArgumentException("Invalid operator: $operator");
        }
        return $op;
    }

    /** For debugging — returns the compiled SQL without executing it. */
    public function toSql(): string
    {
        [$sql] = $this->compileSelect();
        return $sql;
    }
}
