<?php

namespace Utils;

use Reunite\Framework\Database\MysqliDb;
use Reunite\Framework\Cloud;
use Reunite\Validator\Validator;

/**
 * Wires portal-scoped and audit {@see MysqliDb} instances into {@see Cloud} and warms APCu config cache.
 */
class Database
{
    private const CONNECTION_CHARSET = 'utf8mb4';

    /** @var array<string, MysqliDb> Portal-specific DB instances */
    protected static array $instances = [];

    /** @var MysqliDb Single audit DB instance */
    protected static ?MysqliDb $auditInstance = null;

    /** @var array<string, array<string, string>> Map portal scopes to DB credentials */
    public static array $dbConfigs = [
        'admin' => [
            'host' => DB_HOST_NAME,
            'username' => ADMIN_DB_USER_NAME,
            'password' => ADMIN_DB_PASSWORD,
            'db' => DB_NAME,
            'ssl' => DB_SSL,
        ],
        'default' => [
            'host' => DB_HOST_NAME,
            'username' => DB_USER_NAME,
            'password' => DB_PASSWORD,
            'db' => DB_NAME,
            'ssl' => DB_SSL,
        ],
        'audit' => [
            'host' => AUDIT_DB_HOST_NAME,
            'username' => AUDIT_DB_USER_NAME,
            'password' => AUDIT_DB_PASSWORD,
            'db' => AUDIT_DB_NAME,
            'ssl' => DB_SSL,
        ]
    ];

    /**
     * Primary DB scopes exclude the audit replica and non-connection keys used only for routing docs.
     */
    public static function hasPrimaryScope(string $scope): bool
    {
        return $scope !== ''
            && $scope !== 'audit'
            && isset(self::$dbConfigs[$scope]);
    }

    /**
     * Merge scope credentials with connection defaults (v1 MysqliDb used utf8mb4).
     *
     * @param array<string, mixed> $config
     * @return array<string, mixed>
     */
    private static function resolveConnectionConfig(array $config): array
    {
        if (!isset($config['charset'])) {
            $config['charset'] = self::CONNECTION_CHARSET;
        }

        return $config;
    }

    /**
     * Registers the primary DB connection for the current portal scope
     * (`Cloud::get('api_scope')`) using credentials from {@see Database::$dbConfigs}.
     * SSL/TLS is controlled per-connection via the `ssl` key in {@see Database::$dbConfigs}.
     */
    public static function setDbInstance(): void
    {
        $apiScope = Cloud::get('api_scope');
        if (!\is_string($apiScope) || !self::hasPrimaryScope($apiScope)) {
            self::abortMissingDbScope(\is_string($apiScope) ? $apiScope : null);
        }

        $config = self::resolveConnectionConfig(self::$dbConfigs[$apiScope]);
        Cloud::register('db', MysqliDbExtended::class, [$config]);
    }

    /**
     * Fail fast with JSON when api_scope does not map to credentials (misconfiguration).
     *
     * @param ?string $attempted The invalid or missing scope label
     */
    private static function abortMissingDbScope(?string $attempted): void
    {
        if (!\headers_sent()) {
            header('Content-Type: application/json; charset=utf-8');
        }
        http_response_code(503);
        $payload = [
            'code' => 503,
            'status' => 'Error',
            'message' => 'Database scope is not configured for this API route.',
        ];
        if ($attempted !== null && $attempted !== '') {
            $payload['detail'] = ['api_scope' => $attempted];
        }
        if (Cloud::has('request_id')) {
            $payload['request_id'] = Cloud::get('request_id');
        }
        echo json_encode($payload);
        exit;
    }

    /**
     * Returns a lazily-cached {@see MysqliDbExtended} for a named scope in {@see Database::$dbConfigs}.
     *
     * Use when a model must query outside the request's active `api_scope` (e.g. FastTrack
     * automation on admin tables from a `default`-scoped public route). Prefer this over
     * `new MysqliDbExtended(...)` so SSL and credentials stay centralized here.
     *
     * Scope `audit` returns the shared {@see Cloud::audit_db()} handle (registered by
     * {@see setAuditDbInstance()}).
     */
    public static function connection(string $scope): MysqliDbExtended
    {
        if ($scope === 'audit') {
            return self::auditConnection();
        }
        if (!isset(self::$dbConfigs[$scope])) {
            throw new \InvalidArgumentException('Unknown database scope: ' . $scope);
        }

        if (!isset(self::$instances[$scope])) {
            self::$instances[$scope] = new MysqliDbExtended(self::$dbConfigs[$scope]);
        }

        return self::$instances[$scope];
    }

    /**
     * Returns a lazily-cached {@see MysqliDbExtended} for the given scope.
     *
     * Same cache as {@see connection()} — use whichever name reads clearer at the call site.
     */
    public static function getInstance(string $scope): MysqliDbExtended
    {
        return self::connection($scope);
    }

    /**
     * Opens a new {@see MysqliDbExtended} for the given scope (not cached).
     *
     * Use when an isolated connection is required (e.g. parallel transactions). For the
     * usual shared per-request handle, prefer {@see connection()} or {@see Cloud::db()}.
     *
     * Scope `audit` returns the shared {@see Cloud::audit_db()} handle (not a second socket).
     */
    public static function createConnection(string $scope): MysqliDbExtended
    {
        if ($scope === 'audit') {
            return self::auditConnection();
        }
        if (!isset(self::$dbConfigs[$scope])) {
            throw new \InvalidArgumentException('Unknown database scope: ' . $scope);
        }

        return new MysqliDbExtended(self::$dbConfigs[$scope]);
    }

    /**
     * Shared audit DB handle from {@see Cloud::audit_db()}, registering it when needed.
     */
    private static function auditConnection(): MysqliDbExtended
    {
        if (!Cloud::has('audit_db')) {
            self::setAuditDbInstance();
        }

        return Cloud::audit_db();
    }

    /**
     * Registers the audit database connection on {@see Cloud} under the `audit_db` service name.
     * SSL/TLS is controlled per-connection via the `ssl` key in {@see Database::$dbConfigs}.
     */
    public static function setAuditDbInstance(): void
    {
        $config = self::resolveConnectionConfig(self::$dbConfigs['audit']);
        if (!empty($config)) {
            Cloud::register('audit_db', MysqliDbExtended::class, [$config]);
        }
    }

    /**
     * Registers the app-wide uniqueness checker used by `Reunite\Validator`'s
     * `is_unique[table.field]` rule, backed by the active portal-scoped DB.
     */
    public static function wireUniqueValidator(): void
    {
        Validator::setDefaultUniqueChecker(
            static function (string $table, string $field, $value, ?array $ignore): bool {
                $db = Cloud::db();
                $db->where($field, $value);
                if ($ignore !== null) {
                    $db->where($ignore['field'], $ignore['value'], '<>');
                }
                return !($db->getValue($table, 'count(*)') > 0);
            }
        );
    }
}

/**
 * {@see MysqliDb} extension adding list/search/sort pagination helpers for JSON list endpoints.
 */
class MysqliDbExtended extends MysqliDb
{
    /** @var list<string> Columns used for optional global search */
    private array $searchFields = [];
    private int $page;
    private int $draw;
    private int $perPage;
    private ?string $sortBy;
    private string $sortOrder;
    private ?string $search;
    private array $filters;

    /**
     * Captures pagination, sorting, free-text search, and structured filter params from the request.
     *
     * @param array<string, mixed> $params Typically query or POST payload (page, per_page, draw, sort_by, sort_order, search, filters)
     * @param list<string>         $searchFields Column names combined with OR for the `search` param
     */
    public function setPagination(array $params, array $searchFields = []): self
    {
        $this->searchFields = $searchFields;
        $this->page = max(1, (int)($params['page'] ?? 1));
        $this->perPage = max(1, min(1000, (int)($params['per_page'] ?? 20)));
        $this->draw = max(1, (int)($params['draw'] ?? 1));
        $this->sortBy = $params['sort_by'] ?? null;
        $this->sortOrder = strtoupper($params['sort_order'] ?? 'ASC') === 'DESC' ? 'DESC' : 'ASC';
        $this->search = isset($params['search'])
            ? mb_substr(trim((string) $params['search']), 0, 100)
            : null;
        $this->filters = $params['filters'] ?? [];
        return $this;
    }

    /**
     * Applies structured `filters` from the last {@see setPagination} call as WHERE clauses.
     */
    private function applyFilters(): self
    {
        if (!empty($this->filters)) {
            $firstField = true;
            foreach ($this->filters as $field => $value) {
                if (!empty($value)) {
                    if (is_array($value)) {
                        if (isset($value['operator']) && isset($value['value'])) {
                            $operator = strtoupper($value['operator']);
                            if (in_array($operator, ['=', '!=', '>', '<', '>=', '<=', 'LIKE', 'IN', 'NOT IN'])) {
                                if ($operator === 'IN' || $operator === 'NOT IN') {
                                    if (is_array($value['value'])) {
                                        $this->where($field, $value['value'], $operator);
                                    }
                                } else {
                                    $this->where($field, $value['value'], $operator);
                                }
                            }
                        } else {
                            $this->where($field, $value, 'IN');
                        }
                    } else {
                        if ($firstField) {
                            $this->where($field, "%{$value}%", 'LIKE');
                            $firstField = false;
                        } else {
                            $this->orWhere($field, "%{$value}%", 'LIKE');
                        }
                    }
                }
            }
        }
        return $this;
    }

    /**
     * Applies OR-of-LIKE search across `searchFields` when `search` is non-empty.
     */
    private function applySearch(): self
    {
        if (!empty($this->search) && !empty($this->searchFields)) {
            $searchTerm = '%' . $this->search . '%';
            $clauses = [];
            $params = [];
            foreach ($this->searchFields as $field) {
                $clauses[] = "{$field} LIKE ?";
                $params[] = $searchTerm;
            }
            if (!empty($clauses)) {
                // Keep search ORs grouped so existing WHERE filters (e.g. active=no) always apply.
                $this->where('(' . implode(' OR ', $clauses) . ')', $params);
            }
        }
        return $this;
    }

    /**
     * Applies `sort_by` / `sort_order` when set.
     */
    private function applySorting(): self
    {
        if (!empty($this->sortBy)) {
            $this->orderBy($this->sortBy, $this->sortOrder);
        }
        return $this;
    }

    /** Runs filter, search, and sort builders in order. */
    private function applyConditions(): self
    {
        $this->applyFilters();
        $this->applySearch();
        $this->applySorting();
        return $this;
    }

    /**
     * Ensures a failed prepare never leaves WHERE/JOIN/ORDER BY clauses on the builder.
     *
     * Parent {@see MysqliDb::_prepareQuery()} resets only when `prepare()` returns false.
     * Under MYSQLI_REPORT_STRICT, `prepare()` throws `\mysqli_sql_exception` first, so the
     * parent never reaches its reset and the next query on this connection inherits the
     * failed query's clauses (e.g. `rx_number` leaking onto an `rrx_drug` lookup). Catch
     * here, clear, and rethrow so callers do not need a manual `resetQuery()` in every catch.
     *
     * @return \mysqli_stmt
     * @throws \Exception|\mysqli_sql_exception
     */
    protected function _prepareQuery()
    {
        try {
            return parent::_prepareQuery();
        } catch (\Throwable $e) {
            $this->reset();
            throw $e;
        }
    }

    /**
     * Public escape hatch to clear the query builder (where/join/orderBy/bind params).
     *
     * Prefer relying on {@see _prepareQuery()} — prepare failures already reset automatically.
     * Use this only for unusual cases that leave builder state without going through prepare.
     */
    public function resetQuery(): self
    {
        $this->reset();
        return $this;
    }

    /**
     * Returns one page of rows plus DataTables-style pagination metadata.
     *
     * @param string       $table  SQL table name
     * @param string|array $fields Columns or * as accepted by parent {@see MysqliDb::paginate}
     *
     * @return array{data: array<int, mixed>, pagination: array{draw: int, page: int, per_page: int, total: int}}
     */
    public function getPagination(string $table, $fields = '*'): array
    {
        $this->applyConditions();
        $this->pageLimit = $this->perPage;
        $data = $this->paginate($table, $this->page, $fields);

        return [
            'data' => $data ?: [],
            'pagination' => [
                'draw' => $this->draw,
                'page' => $this->page,
                'per_page' => $this->perPage,
                'total' => intval($this->totalCount ?? 0)
            ]
        ];
    }
}
