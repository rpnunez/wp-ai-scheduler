<?php
/**
 * Base Repository Class
 *
 * Provides common database operations for all repositories in the plugin.
 * Implements the Repository Pattern to abstract database access.
 *
 * @package AI_Post_Scheduler
 * @since 1.5.0
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class AIPS_Base_Repository
 *
 * Abstract base class for all repository classes. Provides common CRUD operations
 * and query utilities to reduce code duplication across repository implementations.
 */
abstract class AIPS_Base_Repository {
    
    /**
     * @var wpdb WordPress database object
     */
    protected $wpdb;
    
    /**
     * @var string Full table name with prefix
     */
    protected $table_name;
    
    /**
     * @var AIPS_Logger Logger instance
     */
    protected $logger;
    
    /**
     * Initialize the repository.
     *
     * @param string $table_name Table name without prefix.
     */
    public function __construct($table_name) {
        global $wpdb;
        $this->wpdb = $wpdb;
        $this->table_name = $wpdb->prefix . $table_name;
        $this->logger = new AIPS_Logger();
    }
    
    /**
     * Get a single record by ID.
     *
     * @param int $id Record ID.
     * @return object|null Database row object or null if not found.
     */
    public function find($id) {
        return $this->wpdb->get_row(
            $this->wpdb->prepare(
                "SELECT * FROM {$this->table_name} WHERE id = %d",
                $id
            )
        );
    }
    
    /**
     * Get all records from the table.
     *
     * @param array $args Optional. Query arguments (where, order_by, order, limit, offset).
     * @return array Array of database row objects.
     */
    public function find_all($args = array()) {
        $defaults = array(
            'where' => array(),
            'order_by' => 'id',
            'order' => 'DESC',
            'limit' => null,
            'offset' => null,
        );
        
        $args = wp_parse_args($args, $defaults);
        
        $sql = "SELECT * FROM {$this->table_name}";
        $where_sql = $this->build_where_clause($args['where']);
        
        if (!empty($where_sql['sql'])) {
            $sql .= " WHERE " . $where_sql['sql'];
        }
        
        $sql .= " ORDER BY " . esc_sql($args['order_by']) . " " . esc_sql($args['order']);
        
        if ($args['limit'] !== null) {
            $sql .= $this->wpdb->prepare(" LIMIT %d", $args['limit']);
            
            if ($args['offset'] !== null) {
                $sql .= $this->wpdb->prepare(" OFFSET %d", $args['offset']);
            }
        }
        
        if (!empty($where_sql['values'])) {
            return $this->wpdb->get_results($this->wpdb->prepare($sql, $where_sql['values']));
        }
        
        return $this->wpdb->get_results($sql);
    }
    
    /**
     * Count records matching criteria.
     *
     * @param array $where Optional. WHERE conditions.
     * @return int Number of matching records.
     */
    public function count($where = array()) {
        $sql = "SELECT COUNT(*) FROM {$this->table_name}";
        $where_sql = $this->build_where_clause($where);
        
        if (!empty($where_sql['sql'])) {
            $sql .= " WHERE " . $where_sql['sql'];
        }
        
        if (!empty($where_sql['values'])) {
            return (int) $this->wpdb->get_var($this->wpdb->prepare($sql, $where_sql['values']));
        }
        
        return (int) $this->wpdb->get_var($sql);
    }
    
    /**
     * Insert a new record.
     *
     * @param array  $data   Associative array of column => value pairs.
     * @param array  $format Optional. Array of formats for values (%s, %d, %f).
     * @return int|false The inserted record ID or false on failure.
     */
    public function insert($data, $format = null) {
        $result = $this->wpdb->insert($this->table_name, $data, $format);
        
        if ($result === false) {
            $this->logger->log('Insert failed: ' . $this->wpdb->last_error, 'error', array(
                'table' => $this->table_name,
                'data' => $data,
            ));
            return false;
        }
        
        return $this->wpdb->insert_id;
    }
    
    /**
     * Update an existing record.
     *
     * @param int    $id     Record ID.
     * @param array  $data   Associative array of column => value pairs to update.
     * @param array  $format Optional. Array of formats for values (%s, %d, %f).
     * @return int|false Number of rows affected or false on error.
     */
    public function update($id, $data, $format = null) {
        $result = $this->wpdb->update(
            $this->table_name,
            $data,
            array('id' => $id),
            $format,
            array('%d')
        );
        
        if ($result === false) {
            $this->logger->log('Update failed: ' . $this->wpdb->last_error, 'error', array(
                'table' => $this->table_name,
                'id' => $id,
                'data' => $data,
            ));
        }
        
        return $result;
    }
    
    /**
     * Delete a record by ID.
     *
     * @param int $id Record ID.
     * @return int|false Number of rows deleted or false on error.
     */
    public function delete($id) {
        $result = $this->wpdb->delete(
            $this->table_name,
            array('id' => $id),
            array('%d')
        );
        
        if ($result === false) {
            $this->logger->log('Delete failed: ' . $this->wpdb->last_error, 'error', array(
                'table' => $this->table_name,
                'id' => $id,
            ));
        }
        
        return $result;
    }
    
    /**
     * Delete records matching criteria.
     *
     * @param array $where  Associative array of column => value pairs.
     * @param array $format Optional. Array of formats for WHERE values.
     * @return int|false Number of rows deleted or false on error.
     */
    public function delete_where($where, $format = null) {
        $result = $this->wpdb->delete($this->table_name, $where, $format);
        
        if ($result === false) {
            $this->logger->log('Delete where failed: ' . $this->wpdb->last_error, 'error', array(
                'table' => $this->table_name,
                'where' => $where,
            ));
        }
        
        return $result;
    }
    
    /**
     * Truncate the table (delete all records).
     *
     * @return bool True on success, false on failure.
     */
    public function truncate() {
        $result = $this->wpdb->query("TRUNCATE TABLE {$this->table_name}");
        
        if ($result === false) {
            $this->logger->log('Truncate failed: ' . $this->wpdb->last_error, 'error', array(
                'table' => $this->table_name,
            ));
            return false;
        }
        
        return true;
    }
    
    /**
     * Build a WHERE clause from conditions array.
     *
     * @param array $where Associative array of column => value pairs.
     * @return array Array with 'sql' and 'values' keys.
     */
    protected function build_where_clause($where) {
        if (empty($where)) {
            return array('sql' => '', 'values' => array());
        }
        
        $clauses = array();
        $values = array();
        
        foreach ($where as $column => $value) {
            if (is_array($value)) {
                // Handle special operators
                $operator = isset($value['operator']) ? $value['operator'] : '=';
                $val = isset($value['value']) ? $value['value'] : null;
                
                if ($operator === 'LIKE') {
                    $clauses[] = esc_sql($column) . " LIKE %s";
                    $values[] = '%' . $this->wpdb->esc_like($val) . '%';
                } elseif ($operator === 'IN') {
                    $placeholders = implode(',', array_fill(0, count($val), '%s'));
                    $clauses[] = esc_sql($column) . " IN ($placeholders)";
                    $values = array_merge($values, $val);
                } else {
                    $clauses[] = esc_sql($column) . " " . esc_sql($operator) . " %s";
                    $values[] = $val;
                }
            } else {
                $clauses[] = esc_sql($column) . " = %s";
                $values[] = $value;
            }
        }
        
        return array(
            'sql' => implode(' AND ', $clauses),
            'values' => $values,
        );
    }
    
    /**
     * Execute a custom SQL query.
     *
     * Use with caution. Prefer using the built-in methods when possible.
     *
     * @param string $query SQL query.
     * @param array  $args  Optional. Query arguments for wpdb::prepare().
     * @return mixed Query results.
     */
    protected function query($query, $args = array()) {
        if (!empty($args)) {
            $query = $this->wpdb->prepare($query, $args);
        }
        
        return $this->wpdb->get_results($query);
    }
    
    /**
     * Get the table name.
     *
     * @return string Full table name with prefix.
     */
    public function get_table_name() {
        return $this->table_name;
    }
}
