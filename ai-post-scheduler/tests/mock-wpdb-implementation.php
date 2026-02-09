<?php
/**
 * Mock WPDB Implementation
 *
 * Simulates a stateful in-memory database for testing.
 */

class MockWPDB {
    public $prefix = 'wp_';
    public $insert_id = 0;
    public $last_error = '';
    public $last_query = '';
    private $data = array(); // Stores table data: $data['table_name'][] = row_object

    // Standard tables
    public $posts = 'wp_posts';
    public $users = 'wp_users';
    public $comments = 'wp_comments';
    public $links = 'wp_links';
    public $options = 'wp_options';
    public $postmeta = 'wp_postmeta';
    public $usermeta = 'wp_usermeta';
    public $terms = 'wp_terms';
    public $term_taxonomy = 'wp_term_taxonomy';
    public $term_relationships = 'wp_term_relationships';

    public function __construct() {
        // Initialize
    }

    public function esc_like($text) {
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

        foreach ($args as $arg) {
             if (is_array($arg)) {
                // Handle array args (e.g., for IN clauses)
                $arg_str = implode("','", array_map(function($a) { return addslashes((string)$a); }, $arg));
                $query = preg_replace('/%[s|d|f]/', "'$arg_str'", $query, 1);
            } else {
                $val = is_numeric($arg) ? $arg : "'" . addslashes((string)$arg) . "'";
                $query = preg_replace('/%[s|d|f]/', $val, $query, 1);
            }
        }
        return $query;
    }

    public function query($query) {
        $this->last_query = $query;

        // Handle TRUNCATE
        if (preg_match('/^TRUNCATE TABLE\s+(\S+)/i', $query, $matches)) {
            $table = str_replace('`', '', $matches[1]);
            $this->data[$table] = array();
            return true;
        }

        // Handle INSERT (Raw SQL)
        if (preg_match('/^INSERT INTO\s+(\S+)\s+\((.*?)\)\s+VALUES\s+(.*)/is', $query, $matches)) {
             return $this->parse_raw_insert($matches[1], $matches[2], $matches[3]);
        }

        // Handle DELETE (Raw SQL)
        if (preg_match('/^DELETE FROM\s+(\S+)\s+WHERE\s+(.*)/is', $query, $matches)) {
             $table = str_replace('`', '', $matches[1]);
             $where_clause = $matches[2];
             return $this->process_delete($table, $where_clause);
        }

        return true;
    }

    private function parse_raw_insert($table, $columns_str, $values_str) {
        $table = str_replace('`', '', $table);
        $columns = array_map('trim', explode(',', str_replace('`', '', $columns_str)));

        // Split by "), (" to get rows
        $rows = explode('), (', $values_str);
        $affected_rows = 0;

        foreach ($rows as $row_str) {
            $row_str = trim($row_str, '(); ');
            $values = str_getcsv($row_str, ',', "'");

            $data = array();
            foreach ($columns as $i => $col) {
                $val = isset($values[$i]) ? $values[$i] : null;
                if (is_numeric($val) && strpos($val, "'") === false && strpos($val, '"') === false) {
                    // It's a number
                }
                $data[$col] = $val;
            }

            // Add ID if not present
            if (!isset($data['id'])) {
                static $global_id = 1;
                $data['id'] = $global_id++;
                $this->insert_id = $data['id'];
            }

            if (!isset($this->data[$table])) {
                $this->data[$table] = array();
            }
            $this->data[$table][] = (object) $data;
            $affected_rows++;
        }

        return $affected_rows;
    }

    private function process_delete($table, $where_clause) {
        if (!isset($this->data[$table])) return 0;

        $initial_count = count($this->data[$table]);
        $this->data[$table] = array_values(array_filter($this->data[$table], function($row) use ($where_clause) {
            return !$this->evaluate_where($row, $where_clause);
        }));

        return $initial_count - count($this->data[$table]);
    }

    public function insert($table, $data, $format = null) {
        $table = str_replace('`', '', $table);
        if (!isset($this->data[$table])) {
            $this->data[$table] = array();
        }

        // Auto-increment ID
        static $next_ids = array();
        if (!isset($next_ids[$table])) {
            $next_ids[$table] = 1;
        }

        if (!isset($data['id'])) {
            $data['id'] = $next_ids[$table]++;
        } else {
             if ($data['id'] >= $next_ids[$table]) {
                 $next_ids[$table] = $data['id'] + 1;
             }
        }
        $this->insert_id = $data['id'];

        $this->data[$table][] = (object) $data;
        return 1;
    }

    public function update($table, $data, $where, $format = null, $where_format = null) {
        $table = str_replace('`', '', $table);
        if (!isset($this->data[$table])) return false;

        $updated = 0;
        foreach ($this->data[$table] as $key => $row) {
            // Check if row matches WHERE
            $match = true;
            foreach ($where as $wk => $wv) {
                if (!isset($row->$wk) || $row->$wk != $wv) {
                    $match = false;
                    break;
                }
            }

            if ($match) {
                foreach ($data as $dk => $dv) {
                    $row->$dk = $dv;
                }
                $updated++;
            }
        }
        return $updated > 0 ? $updated : false;
    }

    public function delete($table, $where, $where_format = null) {
        $table = str_replace('`', '', $table);
        if (!isset($this->data[$table])) return false;

        $initial_count = count($this->data[$table]);
        $this->data[$table] = array_values(array_filter($this->data[$table], function($row) use ($where) {
             foreach ($where as $k => $v) {
                 if (isset($row->$k) && $row->$k == $v) {
                     return false; // Remove
                 }
             }
             return true; // Keep
        }));

        return $initial_count - count($this->data[$table]);
    }

    public function get_results($query, $output = OBJECT) {
        $this->last_query = $query;
        // Parse SELECT query
        if (preg_match('/^SELECT\s+(.*?)\s+FROM\s+(\S+)(.*)/is', $query, $matches)) {
            $select_fields = $matches[1];
            $table = str_replace('`', '', $matches[2]);
            $rest = $matches[3];

            if (!isset($this->data[$table])) {
                return array();
            }

            // Filter (WHERE)
            $results = $this->data[$table];
            if (preg_match('/WHERE\s+(.*?)(GROUP BY|ORDER BY|LIMIT|$)/is', $rest, $where_match)) {
                $where_clause = $where_match[1];
                $results = array_filter($results, function($row) use ($where_clause) {
                    return $this->evaluate_where($row, $where_clause);
                });
            }

            // Group By
            if (preg_match('/GROUP BY\s+(.*?)(ORDER BY|LIMIT|$)/is', $rest, $group_match)) {
                $group_by = trim($group_match[1]);
                $groups = array();
                foreach ($results as $row) {
                    $key = isset($row->$group_by) ? $row->$group_by : '';
                    if (!isset($groups[$key])) {
                        $groups[$key] = array();
                    }
                    $groups[$key][] = $row;
                }

                $new_results = array();
                foreach ($groups as $key => $rows) {
                    $new_row = new stdClass();
                    $new_row->$group_by = $key;
                    if (strpos($select_fields, 'COUNT(*)') !== false) {
                        $new_row->count = count($rows);
                    }
                    // Handle other fields?
                    // For now, only niche list uses this.
                    $new_results[] = $new_row;
                }
                $results = $new_results;
            }

            // Order By
            if (preg_match('/ORDER BY\s+(.*?)(LIMIT|$)/is', $rest, $order_match)) {
                $order_clause = $order_match[1];
                $this->sort_results($results, $order_clause);
            }

            // Limit/Offset
            if (preg_match('/LIMIT\s+(\d+)(\s+OFFSET\s+(\d+))?/is', $rest, $limit_match)) {
                $limit = intval($limit_match[1]);
                $offset = isset($limit_match[3]) ? intval($limit_match[3]) : 0;
                $results = array_slice($results, $offset, $limit);
            }

            // Handle aggregate functions in SELECT (without GROUP BY)
            if (strpos($rest, 'GROUP BY') === false && (stripos($select_fields, 'COUNT') !== false || stripos($select_fields, 'AVG') !== false || stripos($select_fields, 'MAX') !== false)) {
                 $agg_obj = new stdClass();

                 // Topic Count
                 $agg_obj->topic_count = count($results);

                 // Avg Score
                 $scores = array_map(function($r) { return isset($r->score) ? $r->score : 0; }, $results);
                 $agg_obj->avg_score = count($scores) > 0 ? array_sum($scores) / count($scores) : 0;

                 // Max Score
                 $agg_obj->highest_score = count($scores) > 0 ? max($scores) : 0;

                 // Latest research
                 $dates = array_map(function($r) { return isset($r->researched_at) ? $r->researched_at : '0000-00-00 00:00:00'; }, $results);
                 $agg_obj->latest_research = count($dates) > 0 ? max($dates) : null;

                 return array($agg_obj);
            }

            if ($output == ARRAY_A) {
                return array_map(function($obj) { return (array)$obj; }, $results);
            }
            return array_values($results);
        }

        return array();
    }

    public function get_row($query, $output = OBJECT, $y = 0) {
        $results = $this->get_results($query, OBJECT);
        if (empty($results)) {
            return null;
        }
        $row = $results[0];
        if ($output == ARRAY_A) {
            return (array)$row;
        }
        return $row;
    }

    public function get_var($query, $x = 0, $y = 0) {
        // Special case for COUNT (simplified)
        if (preg_match('/SELECT\s+COUNT\((.*?)\)\s+FROM\s+(\S+)(.*)/is', $query, $matches)) {
             $table = str_replace('`', '', $matches[2]);
             $rest = $matches[3];
             if (!isset($this->data[$table])) return 0;

             $results = $this->data[$table];
             if (preg_match('/WHERE\s+(.*?)(GROUP BY|ORDER BY|LIMIT|$)/is', $rest, $where_match)) {
                $where_clause = $where_match[1];
                $results = array_filter($results, function($row) use ($where_clause) {
                    return $this->evaluate_where($row, $where_clause);
                });
             }
             return count($results);
        }

         // Special case for AVG
        if (preg_match('/SELECT\s+AVG\((.*?)\)\s+FROM\s+(\S+)(.*)/is', $query, $matches)) {
             $field = $matches[1];
             $table = str_replace('`', '', $matches[2]);
             if (!isset($this->data[$table])) return 0;
             $sum = 0;
             $count = 0;
             foreach ($this->data[$table] as $row) {
                 if (isset($row->$field)) {
                     $sum += $row->$field;
                     $count++;
                 }
             }
             return $count > 0 ? $sum / $count : 0;
        }

        $results = $this->get_results($query, ARRAY_N);
        return isset($results[0][0]) ? $results[0][0] : null;
    }

    public function get_col($query = null, $x = 0) {
        $results = $this->get_results($query, OBJECT);
        if (preg_match('/SELECT\s+(\w+)\s+FROM/is', $query, $matches)) {
            $col = $matches[1];
            return array_map(function($row) use ($col) {
                return isset($row->$col) ? $row->$col : null;
            }, $results);
        }
        return array();
    }

    public function get_charset_collate() {
        return "DEFAULT CHARACTER SET utf8 COLLATE utf8_general_ci";
    }

    private function evaluate_where($row, $clause) {
        $clause = trim($clause);
        if ($clause == '' || $clause == '1=1') return true;

        $or_parts = explode(' OR ', $clause);
        foreach ($or_parts as $or_part) {
             $and_parts = explode(' AND ', $or_part);
             $and_match = true;
             foreach ($and_parts as $and_part) {
                 if (!$this->evaluate_condition($row, $and_part)) {
                     $and_match = false;
                     break;
                 }
             }
             if ($and_match) return true;
        }
        return false;
    }

    private function evaluate_condition($row, $cond) {
        $cond = trim($cond);
        if ($cond == '' || $cond == '1=1') return true;

        if (preg_match('/(\w+)\s+IN\s+\((.*?)\)/i', $cond, $matches)) {
             $field = $matches[1];
             $values_str = $matches[2];
             $values = array_map('trim', explode(',', str_replace("'", "", $values_str)));

             if (!isset($row->$field)) return false;
             return in_array($row->$field, $values);
        }

        if (preg_match('/(\w+)\s*(=|>=|<=|>|<|LIKE)\s*(.*)/', $cond, $matches)) {
                $field = $matches[1];
                $op = $matches[2];
                $val = trim($matches[3], "' ");

                if (!isset($row->$field)) return false;

                $row_val = $row->$field;

                if ($op == '=') {
                    if ($row_val != $val) return false;
                } elseif ($op == '>=') {
                     if (strpos($val, 'DATE_SUB') !== false) {
                         if (preg_match('/DATE_SUB\(NOW\(\), INTERVAL (\d+) DAY\)/', $val, $d_match)) {
                             $days = $d_match[1];
                             if (strtotime($row_val) < strtotime("-$days days")) return false;
                         } else {
                             if (strtotime($row_val) < strtotime('-7 days')) return false;
                         }
                     }
                     else {
                         if ($row_val < $val) return false;
                     }
                } elseif ($op == 'LIKE') {
                    $pattern = str_replace('%', '.*', preg_quote(str_replace('%', '', $val), '/'));
                    if (!preg_match("/$pattern/i", $row_val)) return false;
                }
                return true;
        }
        return false;
    }

    private function sort_results(&$results, $order_clause) {
        $parts = explode(',', $order_clause);

        usort($results, function($a, $b) use ($parts) {
            foreach ($parts as $part) {
                $part = trim($part);
                if (preg_match('/(\w+)\s+(ASC|DESC)/i', $part, $matches)) {
                    $field = $matches[1];
                    $dir = strtoupper($matches[2]);

                    $valA = isset($a->$field) ? $a->$field : 0;
                    $valB = isset($b->$field) ? $b->$field : 0;

                    if ($valA == $valB) continue;

                    if ($dir == 'ASC') {
                        return $valA < $valB ? -1 : 1;
                    } else {
                        return $valA > $valB ? -1 : 1;
                    }
                }
            }
            return 0;
        });
    }
}
