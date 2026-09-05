<?php
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Simple DTO for a schedule batch run row.
 *
 * @package AI_Post_Scheduler
 */
class AIPS_Batch_Run_Entry {
    public $id;
    public $batch_uuid;
    public $schedule_id;
    public $correlation_id;
    public $status;
    public $total;
    public $completed;
    public $resume_index;
    public $post_ids; // JSON string or null
    public $created_at;
    public $updated_at;

    public static function from_row($row) {
        if (!$row) return null;
        $obj = new self();
        foreach (get_object_vars($row) as $k => $v) {
            $obj->{$k} = $v;
        }
        return $obj;
    }
}
