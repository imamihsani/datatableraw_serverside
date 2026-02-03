<?php
//taruh di path application/libraries/
defined('BASEPATH') OR exit('No direct script access allowed');

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
    protected $search_sql = '';

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

    /* ===================== NORMALIZER ===================== */

    protected function normalizeSearch($value)
    {
        $value = trim($value);

        // Nominal: 1.250.000 / 1,250,000 → 1250000
        if (preg_match('/^[\d.,]+$/', $value)) {
            return preg_replace('/[^\d]/', '', $value);
        }

        // Tanggal: d/m/Y atau d-m-Y → Y-m-d
        if (preg_match('/^\d{2}[\/-]\d{2}[\/-]\d{4}$/', $value)) {
            $value = str_replace('-', '/', $value);
            [$d, $m, $y] = explode('/', $value);
            return "$y-$m-$d";
        }

        return $value;
    }

    /* ===================== INTERNAL ===================== */

    protected function parseColumns()
    {
        $this->columns = [];
        if (!empty($_POST['columns'])) {
            foreach ($_POST['columns'] as $c) {
                $this->columns[] = $c['data'] ?? null;
            }
        }
    }

    protected function buildSearch()
    {
        $search = trim($_POST['search']['value'] ?? '');
        if ($search === '' || empty($this->columns)) return;

        $search = $this->normalizeSearch($search);

        $likes = [];
        foreach ($this->columns as $col) {
            if (!$col || $col === $this->index_label) continue;

            $dbcol = $this->column_map[$col] ?? $col;
            $likes[] = "CAST($dbcol AS CHAR) LIKE " .
                       $this->db->escape('%' . $search . '%');
        }

        if ($likes) {
            $this->search_sql = ' AND (' . implode(' OR ', $likes) . ')';
        }
    }

    protected function buildColumnSearch()
    {
        if (empty($_POST['columns'])) return;

        $likes = [];
        foreach ($_POST['columns'] as $col) {
            $col_name = $col['data'] ?? null;
            $search_value = trim($col['search']['value'] ?? '');

            if ($search_value === '' || $col_name === $this->index_label) continue;

            $search_value = $this->normalizeSearch($search_value);
            $dbcol = $this->column_map[$col_name] ?? $col_name;

            $likes[] = "CAST($dbcol AS CHAR) LIKE " .
                       $this->db->escape('%' . $search_value . '%');
        }

        if ($likes) {
            $this->search_sql .= ' AND (' . implode(' AND ', $likes) . ')';
        }
    }

    protected function buildOrder()
    {
        if (empty($_POST['order']) || empty($this->columns)) return;

        $order = [];
        foreach ($_POST['order'] as $o) {
            $idx = (int)($o['column'] ?? 0);
            $dir = strtoupper($o['dir'] ?? 'ASC');

            if (!isset($this->columns[$idx])) continue;

            $col = $this->columns[$idx];
            if ($col === $this->index_label) continue;

            $dbcol = $this->column_map[$col] ?? $col;
            $order[] = "$dbcol $dir";
        }

        if ($order) {
            $this->order_sql = ' ORDER BY ' . implode(', ', $order);
        }
    }

    protected function buildLimit()
    {
        $length = (int)($_POST['length'] ?? 10);
        $start  = (int)($_POST['start'] ?? 0);

        if ($length > 0) {
            $this->limit_sql = " LIMIT $length OFFSET $start";
        }
    }

    protected function wrapWhere($sql)
    {
        return stripos($sql, 'where') === false
            ? $sql . ' WHERE 1=1'
            : $sql;
    }

    /* ===================== EXECUTE ===================== */

    public function generate()
    {
        try {
            $this->parseColumns();
            $this->buildSearch();
            $this->buildColumnSearch();
            $this->buildOrder();
            $this->buildLimit();

            $base = rtrim($this->base_sql, ';');
            $base = $this->wrapWhere($base);

            $base_clean = preg_replace('/ORDER BY[\s\S]*$/i', '', $base);
            $base_clean = preg_replace('/\s+LIMIT\s+\d+(\s*,\s*\d+)?(\s+OFFSET\s+\d+)?/i', '', $base_clean);

            $total = $this->db->query(
                "SELECT COUNT(*) total FROM ($base_clean) x"
            )->row()->total ?? 0;

            $filtered_sql = $base_clean . $this->search_sql;

            $filtered = $this->db->query(
                "SELECT COUNT(*) total FROM ($filtered_sql) x"
            )->row()->total ?? 0;

            $rows = $this->db
                ->query($filtered_sql . $this->order_sql . $this->limit_sql)
                ->result_array();

            $data = [];
            $no = (int)($_POST['start'] ?? 0);

            foreach ($rows as $row) {
                $no++;

                foreach ($this->edit_columns as $c => $cb) {
                    if (isset($row[$c])) {
                        $row[$c] = $cb($row[$c], $row);
                    }
                }

                foreach ($this->add_columns as $c => $cb) {
                    $row[$c] = $cb($row);
                }

                foreach ($this->unset_columns as $c) {
                    unset($row[$c]);
                }

                foreach ($row as $k => $v) {
                    if (!in_array($k, $this->raw_columns)) {
                        $row[$k] = html_escape($v);
                    }
                }

                if ($this->add_index) {
                    $row = [$this->index_label => $no] + $row;
                }

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
