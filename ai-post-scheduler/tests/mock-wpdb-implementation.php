<?php

class MockWPDB {
    public $prefix = 'wp_';
    public $insert_id = 0;
    public $last_error = '';
    private $data = array();

    public function esc_like($text) {
        if ($text === null) {
            return '';
        }
        return addcslashes($text, '_%\\');
    }

    public function prepare($query, ...$args) {
        if (empty($args)) {
            return $query;
        }
        if (count($args) === 1 && is_array($args[0])) {
            $args = $args[0];
        }

        $query = str_replace("'%s'", '%s', $query); // Fix double quotes if any
        $query = str_replace("'%d'", '%d', $query);
        $query = str_replace("'%f'", '%f', $query);

        foreach ($args as $arg) {
            if (is_array($arg)) {
                // For IN clauses mostly, though standard wpdb::prepare doesn't handle arrays directly like this usually
                // But for our mock simplistic replacement:
                $arg = implode("','", array_map(function($a) { return addslashes((string)$a); }, $arg));
                $query = preg_replace('/%[sdf]/', "'$arg'", $query, 1);
            } else {
                $val = is_numeric($arg) ? $arg : "'" . addslashes((string)$arg) . "'";
                $query = preg_replace('/%[sdf]/', $val, $query, 1);
            }
        }
        return $query;
    }

    public function get_results($query, $output = 'OBJECT') {
        $query = trim($query);

        // Basic parsing
        if (preg_match('/^SELECT\s+(.+?)\s+FROM\s+([^\s]+)(.*)$/is', $query, $matches)) {
            $fields_str = $matches[1];
            $table = $matches[2];
            $rest = $matches[3];

            // Clean table name (remove backticks if any)
            $table = str_replace('`', '', $table);

            if (!isset($this->data[$table])) {
                return array();
            }

            $rows = $this->data[$table];

            // Handle JOINs (simplistic: ignored, assuming data is already flattened or we don't support joins well)
            // If JOIN is present, we might be in trouble.
            // The tests use JOIN for templates.
            // "LEFT JOIN wp_aips_templates t ON h.template_id = t.id"

            if (stripos($rest, 'JOIN') !== false) {
                 // Simplistic JOIN handling: Just ignore it for now and return base table rows
                 // Or better: if we can identify the joined table, maybe we can do something?
                 // But for now let's just proceed with the base table.
            }

            // Handle WHERE
            if (preg_match('/WHERE\s+(.*?)(ORDER BY|LIMIT|$)/is', $rest, $where_matches)) {
                $where_clause = $where_matches[1];
                $rows = array_filter($rows, function($row) use ($where_clause) {
                    return $this->evaluate_where($where_clause, $row);
                });
            }

            // Handle ORDER BY
            if (preg_match('/ORDER BY\s+(.*?)(LIMIT|$)/is', $rest, $order_matches)) {
                $order_clause = $order_matches[1];
                $this->sort_rows($rows, $order_clause);
            }

            // Handle LIMIT/OFFSET
            if (preg_match('/LIMIT\s+(\d+)(?:\s+OFFSET\s+(\d+))?/is', $rest, $limit_matches)) {
                $limit = intval($limit_matches[1]);
                $offset = isset($limit_matches[2]) ? intval($limit_matches[2]) : 0;
                $rows = array_slice($rows, $offset, $limit);
            }

            // Handle formatting
            if ($output == 'ARRAY_A') {
                return array_map(function($row) { return (array)$row; }, $rows);
            }

            // Convert array to objects
            return array_map(function($row) { return (object)$row; }, $rows);
        }

        return array();
    }

    public function get_row($query, $output = 'OBJECT', $y = 0) {
        $results = $this->get_results($query, $output);

        if (!empty($results)) {
            return $results[0];
        }

        // Fallback to default object to support legacy tests
        $obj = new stdClass();
        $obj->id = 1;
        $obj->total = 0;
        $obj->completed = 0;
        $obj->failed = 0;
        $obj->processing = 0;
        $obj->count = 0;

        if ($output == 'ARRAY_A') {
            return (array) $obj;
        }

        return $obj;
    }

    public function get_var($query, $x = 0, $y = 0) {
        $results = $this->get_results($query, 'ARRAY_A');
        if (!empty($results)) {
            $row = array_values($results[0]);
            return isset($row[$x]) ? $row[$x] : null;
        }

        // Handle COUNT(*) specifically if get_results failed to parse it or returned full rows
        if (preg_match('/SELECT\s+COUNT\(\*\)\s+FROM\s+([^\s]+)(.*)/is', $query, $matches)) {
             // Re-use get_results logic but with * to get count
             // Hacky way: replace COUNT(*) with * and count results
             $list_query = str_replace('COUNT(*)', '*', $query);
             $results = $this->get_results($list_query, 'ARRAY_A');
             return count($results);
        }

        return null;
    }

    public function query($query) {
        $query = trim($query);

        // Handle INSERT
        if (stripos($query, 'INSERT INTO') === 0) {
            // INSERT INTO table (cols) VALUES (vals), (vals)
            if (preg_match('/INSERT INTO\s+([^\s]+)\s*\((.*?)\)\s*VALUES\s*(.*)/is', $query, $matches)) {
                $table = $matches[1];
                $columns = array_map('trim', explode(',', $matches[2]));
                $values_str = $matches[3];

                // Parse values using simpler split logic because quoted strings are hard in regex
                // But since values are coming from prepare(), they are well-formed.
                // Rows are separated by "), ("

                // Hacky split: split by "), ("
                // This assumes no string contains "), ("
                // This is risky but likely covers 99% of cases here.

                // First, remove outer parens of the whole block if needed? No, standard is (val), (val)

                // Let's iterate to find rows
                $rows = array();
                $buffer = '';
                $in_quote = false;
                $paren_depth = 0;
                $len = strlen($values_str);

                for ($i = 0; $i < $len; $i++) {
                    $char = $values_str[$i];

                    if ($char === "'") {
                        if ($i > 0 && $values_str[$i-1] === '\\') {
                            // Escaped quote
                        } else {
                            $in_quote = !$in_quote;
                        }
                    } elseif ($char === '(' && !$in_quote) {
                        $paren_depth++;
                    } elseif ($char === ')' && !$in_quote) {
                        $paren_depth--;
                    }

                    $buffer .= $char;

                    if ($paren_depth === 0 && $char === ')' && !$in_quote) {
                        // End of row
                        // Check if next char is comma
                        $next_char = ($i + 1 < $len) ? $values_str[$i+1] : '';
                        while (($i + 1 < $len) && ctype_space($values_str[$i+1])) {
                             $i++; // skip space
                             $next_char = ($i + 1 < $len) ? $values_str[$i+1] : '';
                        }

                        if ($next_char === ',' || $i + 1 >= $len) {
                            $rows[] = trim($buffer);
                            $buffer = '';
                            if ($next_char === ',') $i++; // Skip comma
                        }
                    }
                }

                if (!empty($buffer) && trim($buffer) !== '') {
                    $rows[] = trim($buffer);
                }

                foreach ($rows as $row_str) {
                    // row_str is like (1, 'val')
                    $row_str = trim($row_str, '()');

                    // Split values by comma, respecting quotes
                    $values = str_getcsv($row_str, ',', "'");

                    $data = array();
                    foreach ($columns as $i => $col) {
                        $val = isset($values[$i]) ? $values[$i] : null;
                        // Values from str_getcsv might be strings "1" or "'val'".
                        // str_getcsv removes quotes if enclosed properly? Yes.
                        // But wait, prepare() adds quotes. So input is 'val'.
                        // str_getcsv with quote="'" will strip outer quotes.
                        // So 'val' becomes val.
                        // 1 becomes 1.
                        // 'val\'s' becomes val's.

                        $data[$col] = $val;
                    }
                    $this->insert($table, $data);
                }
                return count($rows);
            }
        }

        // Handle TRUNCATE
        if (stripos($query, 'TRUNCATE TABLE') === 0) {
            if (preg_match('/TRUNCATE TABLE\s+([^\s]+)/is', $query, $matches)) {
                $table = $matches[1];
                $this->data[$table] = array();
                return true;
            }
        }

        // Handle DELETE
        if (stripos($query, 'DELETE FROM') === 0) {
             // Use get_results logic to find rows to delete?
             // Simpler: iterate and delete
             if (preg_match('/DELETE FROM\s+([^\s]+)(.*)/is', $query, $matches)) {
                 $table = $matches[1];
                 $rest = $matches[2];

                 if (!isset($this->data[$table])) {
                     return 0;
                 }

                 if (empty(trim($rest))) {
                     // Delete all
                     $count = count($this->data[$table]);
                     $this->data[$table] = array();
                     return $count;
                 }

                 if (preg_match('/WHERE\s+(.*)/is', $rest, $where_matches)) {
                     $where_clause = $where_matches[1];
                     $initial_count = count($this->data[$table]);
                     $this->data[$table] = array_filter($this->data[$table], function($row) use ($where_clause) {
                         return !$this->evaluate_where($where_clause, $row);
                     });
                     // Re-index array
                     $this->data[$table] = array_values($this->data[$table]);
                     return $initial_count - count($this->data[$table]);
                 }
             }
        }

        return true;
    }

    public function insert($table, $data, $format = null) {
        if (!isset($this->data[$table])) {
            $this->data[$table] = array();
        }

        static $next_ids = array();
        if (!isset($next_ids[$table])) {
            $next_ids[$table] = 1;
        }

        if (!isset($data['id'])) {
            $data['id'] = $next_ids[$table]++;
        }

        $this->data[$table][] = $data;
        $this->insert_id = $data['id'];
        return 1;
    }

    public function update($table, $data, $where, $format = null, $where_format = null) {
        if (!isset($this->data[$table])) {
            return false;
        }

        $updated_count = 0;
        foreach ($this->data[$table] as &$row) {
            $match = true;
            foreach ($where as $key => $val) {
                if (!isset($row[$key]) || $row[$key] != $val) {
                    $match = false;
                    break;
                }
            }

            if ($match) {
                foreach ($data as $key => $val) {
                    $row[$key] = $val;
                }
                $updated_count++;
            }
        }
        return $updated_count;
    }

    public function delete($table, $where, $where_format = null) {
        if (!isset($this->data[$table])) {
            return false;
        }

        $initial_count = count($this->data[$table]);
        $this->data[$table] = array_filter($this->data[$table], function($row) use ($where) {
            foreach ($where as $key => $val) {
                if (!isset($row[$key]) || $row[$key] != $val) {
                    return true; // Keep row if it doesn't match
                }
            }
            return false; // Remove row if it matches
        });

        // Re-index
        $this->data[$table] = array_values($this->data[$table]);

        return $initial_count - count($this->data[$table]);
    }

    public function get_charset_collate() {
        return "DEFAULT CHARACTER SET utf8 COLLATE utf8_general_ci";
    }

    public function get_col($query = null, $x = 0) {
        $results = $this->get_results($query, 'ARRAY_N');
        $col = array();
        foreach ($results as $row) {
            if (isset($row[$x])) {
                $col[] = $row[$x];
            }
        }
        return $col;
    }

    private function evaluate_where($where_clause, $row) {
        // Very basic evaluator
        // Handles: AND, =, >=, <=, LIKE, IN

        // Remove 1=1
        $where_clause = str_replace('1=1', '1=1', $where_clause); // no-op

        $parts = explode(' AND ', $where_clause);

        foreach ($parts as $part) {
            $part = trim($part);
            if ($part == '1=1') continue;
            if (empty($part)) continue;

            // h.status = 'value'
            if (preg_match('/^([a-zA-Z0-9_.]+)\s*(=|>=|<=|>|<|!=)\s*(.*)$/', $part, $matches)) {
                $col = trim($matches[1]);
                $op = $matches[2];
                $val = trim($matches[3], "' ");

                // Remove table alias prefix if present (h.status -> status)
                if (strpos($col, '.') !== false) {
                    $col = explode('.', $col)[1];
                }

                if (!isset($row[$col])) {
                    // If column missing, assuming false unless checking for null?
                    // For now strict.
                    return false;
                }

                $row_val = $row[$col];

                switch ($op) {
                    case '=': if ($row_val != $val) return false; break;
                    case '!=': if ($row_val == $val) return false; break;
                    case '>=': if ($row_val < $val) return false; break;
                    case '<=': if ($row_val > $val) return false; break;
                    case '>': if ($row_val <= $val) return false; break;
                    case '<': if ($row_val >= $val) return false; break;
                }
            }
            // LIKE
            elseif (preg_match('/^([a-zA-Z0-9_.]+)\s+LIKE\s+(.*)$/i', $part, $matches)) {
                $col = trim($matches[1]);
                $val = trim($matches[2], "' ");

                 if (strpos($col, '.') !== false) {
                    $col = explode('.', $col)[1];
                }

                if (!isset($row[$col])) return false;

                // Convert SQL LIKE to Regex
                $pattern = '/^' . str_replace('%', '.*', preg_quote($val, '/')) . '$/i';
                // Unescape % which were quoted
                $pattern = str_replace(preg_quote('%', '/'), '.*', $pattern); // This is getting messy.
                // Simple version:
                $val_regex = str_replace('%', '.*', $val);
                if (!preg_match("/$val_regex/i", $row[$col])) return false;
            }
            // IN
            elseif (preg_match('/^([a-zA-Z0-9_.]+)\s+IN\s*\((.*)\)$/i', $part, $matches)) {
                $col = trim($matches[1]);
                $vals_str = $matches[2];

                 if (strpos($col, '.') !== false) {
                    $col = explode('.', $col)[1];
                }

                if (!isset($row[$col])) return false;

                $vals = str_getcsv($vals_str, ',', "'");
                $vals = array_map('trim', $vals); // trim quotes/spaces

                // Need to clean quotes from str_getcsv results if they persist
                $cleaned_vals = [];
                foreach($vals as $v) $cleaned_vals[] = trim($v, "'");

                if (!in_array($row[$col], $cleaned_vals)) return false;
            }
            // DATE_SUB logic (researched_at >= DATE_SUB(NOW(), INTERVAL 7 DAY))
            elseif (preg_match('/^([a-zA-Z0-9_.]+)\s*(>=|<=|>|<)\s*DATE_SUB\(NOW\(\),\s*INTERVAL\s+(\d+)\s+DAY\)/i', $part, $matches)) {
                 $col = trim($matches[1]);
                 $op = $matches[2];
                 $days = intval($matches[3]);

                 if (strpos($col, '.') !== false) {
                    $col = explode('.', $col)[1];
                }

                if (!isset($row[$col])) return false;

                $row_time = strtotime($row[$col]);
                $limit_time = strtotime("-$days days");

                switch ($op) {
                    case '>=': if ($row_time < $limit_time) return false; break;
                    case '<=': if ($row_time > $limit_time) return false; break;
                    case '>': if ($row_time <= $limit_time) return false; break;
                    case '<': if ($row_time >= $limit_time) return false; break;
                }
            }
        }

        return true;
    }

    private function sort_rows(&$rows, $order_clause) {
        // "h.created_at DESC"
        $parts = explode(',', $order_clause);
        // Only handle first order for now
        $part = trim($parts[0]);

        if (preg_match('/^([a-zA-Z0-9_.]+)(\s+(ASC|DESC))?$/i', $part, $matches)) {
            $col = $matches[1];
            $dir = isset($matches[3]) ? strtoupper($matches[3]) : 'ASC';

            if (strpos($col, '.') !== false) {
                $col = explode('.', $col)[1];
            }

            usort($rows, function($a, $b) use ($col, $dir) {
                $valA = isset($a[$col]) ? $a[$col] : null;
                $valB = isset($b[$col]) ? $b[$col] : null;

                if ($valA == $valB) return 0;

                $res = ($valA < $valB) ? -1 : 1;
                return ($dir === 'DESC') ? -$res : $res;
            });
        }
    }
}
