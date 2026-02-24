<?php
defined('BASEPATH') OR exit('No direct script access allowed');
//taruh di path application/libraries/Datatableraw.php
class DatatableRaw
{
    protected $CI;
    protected $db;
    protected $base_sql;

    protected $columns = [];
    protected $column_map = [];
    protected $add_columns  = [];
    protected $edit_columns = [];
    protected $unset_columns = [];
    protected $raw_columns  = [];

    protected $order_sql = '';
    protected $limit_sql = '';

    protected $where_conditions  = [];
    protected $having_conditions = [];

    protected $add_index = false;
    protected $index_label = 'no';

    public function __construct($db = null)
    {
        $this->CI =& get_instance();
        $this->db = $db ?: $this->CI->db;
    }

    /* ===================== SETTER ===================== */
    public function setDB($db)
    {
        $this->db = $db;
        return $this;
    }

    public function setBaseQuery(string $sql)
    {
        $this->base_sql = trim($sql);
        return $this;
    }

    public function setColumnMap(array $map)
    {
        $this->column_map = $map;
        return $this;
    }

    public function showIndex($label = 'no')
    {
        $this->add_index = true;
        $this->index_label = $label;
        return $this;
    }

    public function add_column(string $name, callable $callback)
    {
        $this->add_columns[$name] = $callback;
        return $this;
    }

    public function edit_column(string $name, callable $callback)
    {
        $this->edit_columns[$name] = $callback;
        return $this;
    }

    public function unset_column(string $name)
    {
        $this->unset_columns[] = $name;
        return $this;
    }

    public function raw_column($columns)
    {
        foreach ((array)$columns as $col) {
            $this->raw_columns[] = $col;
        }
        return $this;
    }

    /* ===================== UTIL ===================== */
    protected function normalizeSearch($value)
    {
        $value = trim($value);

        // Numeric / rupiah
        if (preg_match('/^[\d.,]+$/', $value)) {
            return preg_replace('/[^\d]/', '', $value);
        }

        // dd/mm/yyyy OR dd-mm-yyyy
        if (preg_match('/^\d{2}[\/-]\d{2}[\/-]\d{4}$/', $value)) {
            $value = str_replace('-', '/', $value);
            [$d, $m, $y] = explode('/', $value);
            return "$y-$m-$d";
        }

        return $value;
    }

    protected function cleanColumn($col)
    {
        $col = trim($col);
        if (stripos($col, ' AS ') !== false) {
            $parts = preg_split('/\s+AS\s+/i', $col);
            $col = $parts[0];
        }
        return trim($col);
    }

    protected function isAggregate($col)
    {
        return preg_match('/\b(SUM|COUNT|AVG|MIN|MAX|ROUND)\s*\(/i', $col);
    }

    protected function wrapWhere($sql)
    {
        return stripos($sql, 'where') === false ? $sql . ' WHERE 1=1' : $sql;
    }

    protected function parseColumns()
    {
        $this->columns = [];
        $columns = $_POST['columns'] ?? [];
        foreach ($columns as $c) {
            $this->columns[] = $c['data'] ?? null;
        }
    }

    /* ===================== GLOBAL SEARCH ===================== */
    protected function buildGlobalSearch()
    {
        $search = trim($_POST['search']['value'] ?? '');
        if ($search === '') return;

        $search = $this->normalizeSearch($search);

        $where = [];
        $having = [];

        foreach ($_POST['columns'] ?? [] as $col) {

            // cek apakah kolom ini global-searchable
            $searchable = filter_var($col['searchable'] ?? true, FILTER_VALIDATE_BOOLEAN);
            if (!$searchable) continue;

            $col_name = $col['data'] ?? null;
            if (!$col_name || !isset($this->column_map[$col_name])) continue;

            $raw   = $this->column_map[$col_name];
            $dbcol = $this->cleanColumn($raw);

            if ($this->isAggregate($raw)) {
                $having[] = "$dbcol LIKE " . $this->db->escape('%'.$search.'%');
            } else {
                if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $search)) {
                    $where[] = "DATE($dbcol) = " . $this->db->escape($search);
                } else {
                    $where[] = "$dbcol LIKE " . $this->db->escape('%'.$search.'%');
                }
            }
        }

        if ($where) $this->where_conditions[] = '(' . implode(' OR ', $where) . ')';
        if ($having) $this->having_conditions[] = '(' . implode(' OR ', $having) . ')';
    }

    /* ===================== COLUMN SEARCH ===================== */
    protected function buildColumnSearch()
    {
        foreach ($_POST['columns'] ?? [] as $col) {

            $col_name = $col['data'] ?? null;
            $val = trim($col['search']['value'] ?? '');
            if (!$col_name || $val === '' || !isset($this->column_map[$col_name])) continue;

            $val = $this->normalizeSearch($val);

            $raw = $this->column_map[$col_name];
            $dbcol = $this->cleanColumn($raw);

            if ($this->isAggregate($raw)) {
                $this->having_conditions[] = "$dbcol LIKE " . $this->db->escape('%'.$val.'%');
            } else {
                if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $val)) {
                    $this->where_conditions[] = "DATE($dbcol) = " . $this->db->escape($val);
                } else {
                    $this->where_conditions[] = "$dbcol LIKE " . $this->db->escape('%'.$val.'%');
                }
            }
        }
    }

    /* ===================== ORDER ===================== */
    protected function buildOrder()
    {
        $orders = $_POST['order'] ?? [];
        $order = [];

        foreach ($orders as $o) {
            $idx = (int)($o['column'] ?? 0);
            $dir = strtoupper($o['dir'] ?? 'ASC') === 'DESC' ? 'DESC' : 'ASC';
            if (!isset($this->columns[$idx])) continue;

            $col = $this->columns[$idx];
            if ($col === $this->index_label) continue;

            $dbcol = $this->cleanColumn($this->column_map[$col] ?? $col);
            $order[] = "$dbcol $dir";
        }

        if ($order) $this->order_sql = ' ORDER BY ' . implode(', ', $order);
    }

    /* ===================== LIMIT ===================== */
    protected function buildLimit()
    {
        $length = (int)($_POST['length'] ?? -1);
        $start  = (int)($_POST['start'] ?? 0);
        if ($length > 0) $this->limit_sql = " LIMIT $length OFFSET $start";
    }

    protected function splitQuery($sql)
    {
        $groupBy = '';
        $having = '';

        if (stripos($sql, 'HAVING') !== false) {
            [$sql, $having] = preg_split('/HAVING/i', $sql, 2);
            $having = ' HAVING ' . $having;
        }

        if (stripos($sql, 'GROUP BY') !== false) {
            [$sql, $groupBy] = preg_split('/GROUP BY/i', $sql, 2);
            $groupBy = ' GROUP BY ' . $groupBy;
        }

        return [$sql, $groupBy, $having];
    }

    /* ===================== GENERATE ===================== */
    public function generate()
    {
        try {
            $this->where_conditions  = [];
            $this->having_conditions = [];

            $this->parseColumns();
            $this->buildGlobalSearch();
            $this->buildColumnSearch();
            $this->buildOrder();
            $this->buildLimit();

            $base = rtrim($this->base_sql, ';');
            $base = $this->wrapWhere($base);
            $base = preg_replace('/ORDER BY[\s\S]*$/i', '', $base);

            [$main, $groupBy, $havingBase] = $this->splitQuery($base);

            /* TOTAL */
            $total = (int)$this->db->query("SELECT COUNT(*) total FROM ($base) x")->row()->total;

            /* FILTERED SQL */
            $filtered_sql = $main;

            if (!empty($this->where_conditions)) {
                $filtered_sql .= ' AND ' . implode(' AND ', $this->where_conditions);
            }

            $filtered_sql .= $groupBy;

            if (!empty($this->having_conditions)) {
                if ($havingBase) {
                    $filtered_sql .= ' AND ' . implode(' AND ', $this->having_conditions);
                } else {
                    $filtered_sql .= ' HAVING ' . implode(' AND ', $this->having_conditions);
                }
            } else {
                $filtered_sql .= $havingBase;
            }

            /* FILTERED COUNT */
            $filtered = (int)$this->db->query("SELECT COUNT(*) total FROM ($filtered_sql) x")->row()->total;

            /* DATA */
            $rows = $this->db->query($filtered_sql . $this->order_sql . $this->limit_sql)->result_array();

            $data = [];
            $no = (int)($_POST['start'] ?? 0);

            foreach ($rows as $row) {
                $no++;
                foreach ($this->edit_columns as $c => $cb) {
                    if (isset($row[$c])) $row[$c] = $cb($row[$c], $row);
                }
                foreach ($this->add_columns as $c => $cb) {
                    $row[$c] = $cb($row);
                }
                foreach ($this->unset_columns as $c) unset($row[$c]);
                foreach ($row as $k => $v) {
                    if (!in_array($k, $this->raw_columns)) $row[$k] = html_escape($v);
                }
                if ($this->add_index) $row = [$this->index_label => $no] + $row;
                $data[] = $row;
            }

            return [
                'draw' => (int)($_POST['draw'] ?? 0),
                'recordsTotal' => $total,
                'recordsFiltered' => $filtered,
                'data' => $data
            ];

        } catch (\Throwable $e) {
            log_message('error', $e->getMessage());
            return [
                'draw' => (int)($_POST['draw'] ?? 0),
                'recordsTotal' => 0,
                'recordsFiltered' => 0,
                'data' => []
            ];
        }
    }
}
