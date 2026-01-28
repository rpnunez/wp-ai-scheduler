# Namespace Migration - Code Examples & Patterns

## Overview

This document provides concrete code examples for the namespace refactoring of the AI Post Scheduler plugin. Use these patterns as templates when migrating classes from the old `AIPS_` prefix structure to the new `AIPostScheduler\` namespace structure.

## Table of Contents

1. [Basic Class Migration](#basic-class-migration)
2. [Class with Dependencies](#class-with-dependencies)
3. [Interface Migration](#interface-migration)
4. [Controller Migration](#controller-migration)
5. [Repository Migration](#repository-migration)
6. [Service Migration](#service-migration)
7. [Singleton Pattern Migration](#singleton-pattern-migration)
8. [WordPress Hook Registration](#wordpress-hook-registration)
9. [Test File Updates](#test-file-updates)
10. [Common Patterns & Best Practices](#common-patterns--best-practices)

---

## Basic Class Migration

### Example: Logger Class

#### Before (Old Structure)
**File:** `includes/class-aips-logger.php`

```php
<?php
/**
 * Logger class
 *
 * @package AI_Post_Scheduler
 * @since 1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class AIPS_Logger
 * 
 * Handles logging throughout the plugin.
 */
class AIPS_Logger {
    
    /**
     * Log level constants
     */
    const LEVEL_ERROR = 'error';
    const LEVEL_WARNING = 'warning';
    const LEVEL_INFO = 'info';
    const LEVEL_DEBUG = 'debug';
    
    /**
     * Log a message
     *
     * @param string $message Log message
     * @param string $level Log level
     * @param array $context Additional context
     * @return void
     */
    public function log($message, $level = self::LEVEL_INFO, $context = array()) {
        if (!get_option('aips_enable_logging')) {
            return;
        }
        
        $log_entry = sprintf(
            '[%s] [%s] %s',
            current_time('mysql'),
            strtoupper($level),
            $message
        );
        
        if (!empty($context)) {
            $log_entry .= ' ' . wp_json_encode($context);
        }
        
        error_log($log_entry);
    }
    
    /**
     * Log an error message
     *
     * @param string $message Error message
     * @param array $context Additional context
     * @return void
     */
    public function error($message, $context = array()) {
        $this->log($message, self::LEVEL_ERROR, $context);
    }
    
    /**
     * Log a warning message
     *
     * @param string $message Warning message
     * @param array $context Additional context
     * @return void
     */
    public function warning($message, $context = array()) {
        $this->log($message, self::LEVEL_WARNING, $context);
    }
}
```

#### After (New Structure)
**File:** `src/AIPostScheduler/Core/Logger.php`

```php
<?php
/**
 * Logger class
 *
 * @package AIPostScheduler
 * @subpackage Core
 * @since 2.0.0
 */

namespace AIPostScheduler\Core;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class Logger
 * 
 * Handles logging throughout the plugin.
 */
class Logger {
    
    /**
     * Log level constants
     */
    const LEVEL_ERROR = 'error';
    const LEVEL_WARNING = 'warning';
    const LEVEL_INFO = 'info';
    const LEVEL_DEBUG = 'debug';
    
    /**
     * Log a message
     *
     * @param string $message Log message
     * @param string $level Log level
     * @param array $context Additional context
     * @return void
     */
    public function log($message, $level = self::LEVEL_INFO, $context = array()) {
        if (!get_option('aips_enable_logging')) {
            return;
        }
        
        $log_entry = sprintf(
            '[%s] [%s] %s',
            current_time('mysql'),
            strtoupper($level),
            $message
        );
        
        if (!empty($context)) {
            $log_entry .= ' ' . wp_json_encode($context);
        }
        
        error_log($log_entry);
    }
    
    /**
     * Log an error message
     *
     * @param string $message Error message
     * @param array $context Additional context
     * @return void
     */
    public function error($message, $context = array()) {
        $this->log($message, self::LEVEL_ERROR, $context);
    }
    
    /**
     * Log a warning message
     *
     * @param string $message Warning message
     * @param array $context Additional context
     * @return void
     */
    public function warning($message, $context = array()) {
        $this->log($message, self::LEVEL_WARNING, $context);
    }
}
```

#### Class Alias Entry
**File:** `includes/class-aliases.php`

```php
<?php
/**
 * Backward compatibility class aliases
 * 
 * Maps old class names to new namespaced classes for backward compatibility.
 *
 * @package AIPostScheduler
 * @since 2.0.0
 */

// Core classes
class_alias('AIPostScheduler\Core\Logger', 'AIPS_Logger');
```

---

## Class with Dependencies

### Example: Generator Class

#### Before (Old Structure)
**File:** `includes/class-aips-generator.php`

```php
<?php
if (!defined('ABSPATH')) {
    exit;
}

class AIPS_Generator {
    
    private $ai_service;
    private $logger;
    private $template_processor;
    private $image_service;
    
    public function __construct(
        $logger = null,
        $ai_service = null,
        $template_processor = null,
        $image_service = null
    ) {
        $this->logger = $logger ?? new AIPS_Logger();
        $this->ai_service = $ai_service ?? new AIPS_AI_Service();
        $this->template_processor = $template_processor ?? new AIPS_Template_Processor();
        $this->image_service = $image_service ?? new AIPS_Image_Service();
    }
    
    public function generate($context) {
        $this->logger->log('Starting generation');
        
        // Use services...
        $content = $this->ai_service->generate_text($prompt);
        $processed = $this->template_processor->process($content);
        
        return $processed;
    }
}
```

#### After (New Structure)
**File:** `src/AIPostScheduler/Service/Content/Generator.php`

```php
<?php
/**
 * Content Generator
 *
 * @package AIPostScheduler
 * @subpackage Service\Content
 * @since 2.0.0
 */

namespace AIPostScheduler\Service\Content;

use AIPostScheduler\Core\Logger;
use AIPostScheduler\Service\AI\AIService;
use AIPostScheduler\Service\Image\ImageService;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class Generator
 * 
 * Orchestrates the AI content generation pipeline.
 */
class Generator {
    
    private Logger $logger;
    private AIService $ai_service;
    private TemplateProcessor $template_processor;
    private ImageService $image_service;
    
    /**
     * Constructor with dependency injection
     *
     * @param Logger|null $logger
     * @param AIService|null $ai_service
     * @param TemplateProcessor|null $template_processor
     * @param ImageService|null $image_service
     */
    public function __construct(
        ?Logger $logger = null,
        ?AIService $ai_service = null,
        ?TemplateProcessor $template_processor = null,
        ?ImageService $image_service = null
    ) {
        $this->logger = $logger ?? new Logger();
        $this->ai_service = $ai_service ?? new AIService();
        $this->template_processor = $template_processor ?? new TemplateProcessor();
        $this->image_service = $image_service ?? new ImageService();
    }
    
    /**
     * Generate content using AI
     *
     * @param object $context Generation context
     * @return string Generated content
     */
    public function generate($context) {
        $this->logger->log('Starting generation');
        
        // Use services...
        $content = $this->ai_service->generate_text($prompt);
        $processed = $this->template_processor->process($content);
        
        return $processed;
    }
}
```

**Key Changes:**
1. Added namespace declaration
2. Added `use` statements for dependencies
3. Removed `AIPS_` prefix from class name
4. Added PHP 8 type hints (optional but recommended)
5. Updated PHPDoc blocks with new namespace path

---

## Interface Migration

### Example: Generation Context Interface

#### Before (Old Structure)
**File:** `includes/interface-aips-generation-context.php`

```php
<?php
if (!defined('ABSPATH')) {
    exit;
}

interface AIPS_Generation_Context {
    
    public function get_type();
    
    public function get_id();
    
    public function get_name();
    
    public function get_content_prompt();
    
    public function get_title_prompt();
}
```

#### After (New Structure)
**File:** `src/AIPostScheduler/Generation/Context/GenerationContextInterface.php`

```php
<?php
/**
 * Generation Context Interface
 *
 * @package AIPostScheduler
 * @subpackage Generation\Context
 * @since 2.0.0
 */

namespace AIPostScheduler\Generation\Context;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Interface GenerationContextInterface
 * 
 * Defines the contract for all generation context types.
 */
interface GenerationContextInterface {
    
    /**
     * Get the context type identifier
     *
     * @return string Context type (e.g., 'template', 'topic')
     */
    public function get_type(): string;
    
    /**
     * Get the context identifier
     *
     * @return int|string|null Context identifier
     */
    public function get_id();
    
    /**
     * Get the context name for display
     *
     * @return string Context name
     */
    public function get_name(): string;
    
    /**
     * Get the content prompt for AI generation
     *
     * @return string Content generation prompt
     */
    public function get_content_prompt(): string;
    
    /**
     * Get the title prompt for AI title generation
     *
     * @return string Title generation prompt
     */
    public function get_title_prompt(): string;
}
```

**Implementation Example:**

**File:** `src/AIPostScheduler/Generation/Context/TemplateContext.php`

```php
<?php

namespace AIPostScheduler\Generation\Context;

if (!defined('ABSPATH')) {
    exit;
}

class TemplateContext implements GenerationContextInterface {
    
    private int $template_id;
    private array $template_data;
    
    public function __construct(int $template_id, array $template_data) {
        $this->template_id = $template_id;
        $this->template_data = $template_data;
    }
    
    public function get_type(): string {
        return 'template';
    }
    
    public function get_id() {
        return $this->template_id;
    }
    
    public function get_name(): string {
        return $this->template_data['name'] ?? 'Unnamed Template';
    }
    
    public function get_content_prompt(): string {
        return $this->template_data['content_prompt'] ?? '';
    }
    
    public function get_title_prompt(): string {
        return $this->template_data['title_prompt'] ?? '';
    }
}
```

---

## Controller Migration

### Example: Templates Controller

#### Before (Old Structure)
**File:** `includes/class-aips-templates-controller.php`

```php
<?php
if (!defined('ABSPATH')) {
    exit;
}

class AIPS_Templates_Controller {
    
    private $repository;
    private $logger;
    
    public function __construct() {
        $this->repository = new AIPS_Template_Repository();
        $this->logger = new AIPS_Logger();
        
        add_action('wp_ajax_aips_save_template', array($this, 'ajax_save_template'));
        add_action('wp_ajax_aips_delete_template', array($this, 'ajax_delete_template'));
    }
    
    public function ajax_save_template() {
        check_ajax_referer('aips_admin_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'Unauthorized'));
        }
        
        $template_data = array(
            'name' => sanitize_text_field($_POST['name']),
            'content_prompt' => sanitize_textarea_field($_POST['content_prompt']),
        );
        
        $result = $this->repository->save($template_data);
        
        if ($result) {
            wp_send_json_success(array('message' => 'Template saved', 'id' => $result));
        } else {
            wp_send_json_error(array('message' => 'Failed to save template'));
        }
    }
    
    public function ajax_delete_template() {
        check_ajax_referer('aips_admin_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'Unauthorized'));
        }
        
        $id = absint($_POST['id']);
        $result = $this->repository->delete($id);
        
        if ($result) {
            wp_send_json_success(array('message' => 'Template deleted'));
        } else {
            wp_send_json_error(array('message' => 'Failed to delete template'));
        }
    }
}
```

#### After (New Structure)
**File:** `src/AIPostScheduler/Controller/TemplatesController.php`

```php
<?php
/**
 * Templates Controller
 *
 * @package AIPostScheduler
 * @subpackage Controller
 * @since 2.0.0
 */

namespace AIPostScheduler\Controller;

use AIPostScheduler\Core\Logger;
use AIPostScheduler\Repository\TemplateRepository;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class TemplatesController
 * 
 * Handles AJAX requests for template management.
 */
class TemplatesController {
    
    private TemplateRepository $repository;
    private Logger $logger;
    
    /**
     * Constructor
     *
     * @param TemplateRepository|null $repository
     * @param Logger|null $logger
     */
    public function __construct(
        ?TemplateRepository $repository = null,
        ?Logger $logger = null
    ) {
        $this->repository = $repository ?? new TemplateRepository();
        $this->logger = $logger ?? new Logger();
        
        $this->register_hooks();
    }
    
    /**
     * Register WordPress hooks
     *
     * @return void
     */
    private function register_hooks(): void {
        add_action('wp_ajax_aips_save_template', array($this, 'ajax_save_template'));
        add_action('wp_ajax_aips_delete_template', array($this, 'ajax_delete_template'));
    }
    
    /**
     * AJAX handler: Save template
     *
     * @return void
     */
    public function ajax_save_template(): void {
        check_ajax_referer('aips_admin_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'Unauthorized'));
        }
        
        $template_data = array(
            'name' => sanitize_text_field($_POST['name'] ?? ''),
            'content_prompt' => sanitize_textarea_field($_POST['content_prompt'] ?? ''),
        );
        
        $result = $this->repository->save($template_data);
        
        if ($result) {
            $this->logger->log('Template saved successfully', Logger::LEVEL_INFO, array('id' => $result));
            wp_send_json_success(array('message' => 'Template saved', 'id' => $result));
        } else {
            $this->logger->error('Failed to save template', $template_data);
            wp_send_json_error(array('message' => 'Failed to save template'));
        }
    }
    
    /**
     * AJAX handler: Delete template
     *
     * @return void
     */
    public function ajax_delete_template(): void {
        check_ajax_referer('aips_admin_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'Unauthorized'));
        }
        
        $id = absint($_POST['id'] ?? 0);
        
        if (!$id) {
            wp_send_json_error(array('message' => 'Invalid template ID'));
        }
        
        $result = $this->repository->delete($id);
        
        if ($result) {
            $this->logger->log('Template deleted successfully', Logger::LEVEL_INFO, array('id' => $id));
            wp_send_json_success(array('message' => 'Template deleted'));
        } else {
            $this->logger->error('Failed to delete template', array('id' => $id));
            wp_send_json_error(array('message' => 'Failed to delete template'));
        }
    }
}
```

---

## Repository Migration

### Example: History Repository

#### Before (Old Structure)
**File:** `includes/class-aips-history-repository.php`

```php
<?php
if (!defined('ABSPATH')) {
    exit;
}

class AIPS_History_Repository {
    
    private $table_name;
    private $wpdb;
    
    public function __construct() {
        global $wpdb;
        $this->wpdb = $wpdb;
        $this->table_name = $wpdb->prefix . 'aips_history';
    }
    
    public function get_history($args = array()) {
        $defaults = array(
            'page' => 1,
            'per_page' => 20,
            'order_by' => 'created_at',
            'order' => 'DESC',
        );
        
        $args = wp_parse_args($args, $defaults);
        
        $offset = ($args['page'] - 1) * $args['per_page'];
        
        $query = $this->wpdb->prepare(
            "SELECT * FROM {$this->table_name} ORDER BY %s %s LIMIT %d OFFSET %d",
            $args['order_by'],
            $args['order'],
            $args['per_page'],
            $offset
        );
        
        return $this->wpdb->get_results($query, ARRAY_A);
    }
}
```

#### After (New Structure)
**File:** `src/AIPostScheduler/Repository/HistoryRepository.php`

```php
<?php
/**
 * History Repository
 *
 * @package AIPostScheduler
 * @subpackage Repository
 * @since 2.0.0
 */

namespace AIPostScheduler\Repository;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class HistoryRepository
 * 
 * Database abstraction layer for history operations.
 */
class HistoryRepository {
    
    private string $table_name;
    private \wpdb $wpdb;
    
    /**
     * Constructor
     */
    public function __construct() {
        global $wpdb;
        $this->wpdb = $wpdb;
        $this->table_name = $wpdb->prefix . 'aips_history';
    }
    
    /**
     * Get paginated history
     *
     * @param array $args Query arguments
     * @return array History records
     */
    public function get_history(array $args = array()): array {
        $defaults = array(
            'page' => 1,
            'per_page' => 20,
            'order_by' => 'created_at',
            'order' => 'DESC',
        );
        
        $args = wp_parse_args($args, $defaults);
        
        $offset = ($args['page'] - 1) * $args['per_page'];
        
        $query = $this->wpdb->prepare(
            "SELECT * FROM {$this->table_name} ORDER BY %s %s LIMIT %d OFFSET %d",
            $args['order_by'],
            $args['order'],
            $args['per_page'],
            $offset
        );
        
        $results = $this->wpdb->get_results($query, ARRAY_A);
        
        return is_array($results) ? $results : array();
    }
    
    /**
     * Get total count of history records
     *
     * @return int Total count
     */
    public function get_total_count(): int {
        $query = "SELECT COUNT(*) FROM {$this->table_name}";
        return (int) $this->wpdb->get_var($query);
    }
    
    /**
     * Insert new history record
     *
     * @param array $data History data
     * @return int|false Insert ID on success, false on failure
     */
    public function insert(array $data) {
        $result = $this->wpdb->insert(
            $this->table_name,
            $data,
            array('%s', '%s', '%d', '%s')
        );
        
        return $result ? $this->wpdb->insert_id : false;
    }
}
```

---

## Singleton Pattern Migration

### Example: Config Class

#### Before (Old Structure)
**File:** `includes/class-aips-config.php`

```php
<?php
if (!defined('ABSPATH')) {
    exit;
}

class AIPS_Config {
    
    private static $instance = null;
    
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        // Private constructor
    }
    
    public function get_ai_config() {
        return array(
            'model' => get_option('aips_ai_model', ''),
            'max_tokens' => 2000,
        );
    }
}
```

#### After (New Structure)
**File:** `src/AIPostScheduler/Core/Config.php`

```php
<?php
/**
 * Configuration Singleton
 *
 * @package AIPostScheduler
 * @subpackage Core
 * @since 2.0.0
 */

namespace AIPostScheduler\Core;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class Config
 * 
 * Centralized configuration management using singleton pattern.
 */
class Config {
    
    private static ?Config $instance = null;
    
    /**
     * Get singleton instance
     *
     * @return Config
     */
    public static function get_instance(): Config {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Private constructor to prevent direct instantiation
     */
    private function __construct() {
        // Private constructor
    }
    
    /**
     * Prevent cloning
     */
    private function __clone() {
        // Prevent cloning
    }
    
    /**
     * Prevent unserialization
     */
    public function __wakeup() {
        throw new \Exception("Cannot unserialize singleton");
    }
    
    /**
     * Get AI configuration
     *
     * @return array AI configuration settings
     */
    public function get_ai_config(): array {
        return array(
            'model' => get_option('aips_ai_model', ''),
            'max_tokens' => 2000,
            'temperature' => 0.7,
        );
    }
}
```

**Usage Example:**

```php
<?php

use AIPostScheduler\Core\Config;

// Get config instance
$config = Config::get_instance();

// Get AI settings
$ai_config = $config->get_ai_config();
```

---

## WordPress Hook Registration

### Pattern: Event Dispatching with Namespaced Classes

#### Before (Old Structure)

```php
<?php

class AIPS_Generator {
    
    public function generate($context) {
        // Dispatch start event
        do_action('aips_post_generation_started', array(
            'context_type' => $context->get_type(),
            'context_id' => $context->get_id(),
        ));
        
        // ... generation logic ...
        
        // Dispatch completion event
        do_action('aips_post_generation_completed', array(
            'post_id' => $post_id,
            'template_id' => $template_id,
        ), $context);
    }
}
```

#### After (New Structure)

```php
<?php

namespace AIPostScheduler\Service\Content;

use AIPostScheduler\Generation\Context\GenerationContextInterface;

class Generator {
    
    /**
     * Generate content from context
     *
     * @param GenerationContextInterface $context
     * @return int Post ID
     */
    public function generate(GenerationContextInterface $context): int {
        // Dispatch start event
        do_action('aips_post_generation_started', array(
            'context_type' => $context->get_type(),
            'context_id' => $context->get_id(),
        ));
        
        // ... generation logic ...
        
        // Dispatch completion event
        do_action('aips_post_generation_completed', array(
            'post_id' => $post_id,
            'template_id' => $template_id,
        ), $context);
        
        return $post_id;
    }
}
```

**Listening to Events (No change needed):**

```php
<?php
// In theme or another plugin - works exactly the same!

add_action('aips_post_generation_completed', function($data, $context) {
    $post_id = $data['post_id'];
    // Send notification, update analytics, etc.
}, 10, 2);
```

---

## Test File Updates

### Example: Generator Test

#### Before (Old Structure)
**File:** `tests/test-generator-hooks.php`

```php
<?php

class Test_AIPS_Generator_Hooks extends WP_UnitTestCase {
    
    public function test_generator_dispatches_start_event() {
        $logger = new class {
            public function log($message, $level = 'info', $context = array()) {}
        };
        
        $ai_service = new class {
            public function generate_text($prompt) {
                return 'Generated content';
            }
        };
        
        $generator = new AIPS_Generator($logger, $ai_service);
        
        // Test logic...
    }
}
```

#### After (New Structure)
**File:** `tests/test-generator-hooks.php`

**Option 1: Using Old Class Names (via aliases - no changes needed)**
```php
<?php
/**
 * Test generator hooks
 *
 * @package AIPostScheduler
 * @subpackage Tests
 */

class Test_AIPS_Generator_Hooks extends WP_UnitTestCase {
    
    public function test_generator_dispatches_start_event() {
        // Can still use old class names - they're aliased!
        $logger = new class {
            public function log($message, $level = 'info', $context = array()) {}
        };
        
        $ai_service = new class {
            public function generate_text($prompt) {
                return 'Generated content';
            }
        };
        
        // Old class name still works via alias
        $generator = new AIPS_Generator($logger, $ai_service);
        
        // Test logic...
    }
}
```

**Option 2: Using New Namespaced Classes (preferred for new code)**
```php
<?php
/**
 * Test generator hooks
 *
 * @package AIPostScheduler
 * @subpackage Tests
 */

use AIPostScheduler\Service\Content\Generator;
use AIPostScheduler\Core\Logger;
use AIPostScheduler\Service\AI\AIService;

class Test_AIPS_Generator_Hooks extends WP_UnitTestCase {
    
    public function test_generator_dispatches_start_event() {
        // Mock logger
        $logger = new class extends Logger {
            public function log($message, $level = 'info', $context = array()) {
                // Mock implementation
            }
        };
        
        // Mock AI service
        $ai_service = new class extends AIService {
            public function generate_text($prompt) {
                return 'Generated content';
            }
        };
        
        // Use new namespaced class
        $generator = new Generator($logger, $ai_service);
        
        // Test logic...
    }
}
```

---

## Common Patterns & Best Practices

### 1. File Header Template

```php
<?php
/**
 * [Class Description]
 *
 * @package AIPostScheduler
 * @subpackage [Namespace Path]
 * @since 2.0.0
 */

namespace AIPostScheduler\[NamespacePath];

use AIPostScheduler\[Dependency1];
use AIPostScheduler\[Dependency2];

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class [ClassName]
 * 
 * [Detailed description]
 */
class [ClassName] {
    // Class implementation
}
```

### 2. Import Statements Organization

```php
<?php

namespace AIPostScheduler\Service\Content;

// Group imports by namespace
// 1. Core classes
use AIPostScheduler\Core\Config;
use AIPostScheduler\Core\Logger;

// 2. Repository classes
use AIPostScheduler\Repository\TemplateRepository;
use AIPostScheduler\Repository\HistoryRepository;

// 3. Service classes
use AIPostScheduler\Service\AI\AIService;
use AIPostScheduler\Service\Image\ImageService;

// 4. Other namespaces
use AIPostScheduler\Generation\Context\GenerationContextInterface;

if (!defined('ABSPATH')) {
    exit;
}

class Generator {
    // Implementation
}
```

### 3. Dependency Injection Pattern

```php
<?php

namespace AIPostScheduler\Service\Content;

use AIPostScheduler\Core\Logger;
use AIPostScheduler\Repository\TemplateRepository;

class TemplateProcessor {
    
    private Logger $logger;
    private TemplateRepository $repository;
    
    /**
     * Constructor with optional dependency injection
     * Falls back to creating instances if not provided
     *
     * @param Logger|null $logger Optional logger instance
     * @param TemplateRepository|null $repository Optional repository instance
     */
    public function __construct(
        ?Logger $logger = null,
        ?TemplateRepository $repository = null
    ) {
        $this->logger = $logger ?? new Logger();
        $this->repository = $repository ?? new TemplateRepository();
    }
}
```

### 4. Type Hints Usage

```php
<?php

namespace AIPostScheduler\Repository;

class TemplateRepository {
    
    /**
     * Get template by ID
     *
     * @param int $id Template ID
     * @return array|null Template data or null if not found
     */
    public function get_by_id(int $id): ?array {
        global $wpdb;
        
        $query = $wpdb->prepare(
            "SELECT * FROM {$this->table_name} WHERE id = %d",
            $id
        );
        
        $result = $wpdb->get_row($query, ARRAY_A);
        
        return $result ?: null;
    }
    
    /**
     * Get all templates
     *
     * @return array Array of template records
     */
    public function get_all(): array {
        global $wpdb;
        
        $results = $wpdb->get_results(
            "SELECT * FROM {$this->table_name} ORDER BY created_at DESC",
            ARRAY_A
        );
        
        return is_array($results) ? $results : array();
    }
}
```

### 5. Static Factory Methods

```php
<?php

namespace AIPostScheduler\Generation\Context;

class TemplateContext implements GenerationContextInterface {
    
    private int $template_id;
    private array $template_data;
    
    /**
     * Private constructor - use factory method instead
     *
     * @param int $template_id
     * @param array $template_data
     */
    private function __construct(int $template_id, array $template_data) {
        $this->template_id = $template_id;
        $this->template_data = $template_data;
    }
    
    /**
     * Create context from template ID
     *
     * @param int $template_id Template ID
     * @return self|null Context instance or null if template not found
     */
    public static function from_template_id(int $template_id): ?self {
        $repository = new \AIPostScheduler\Repository\TemplateRepository();
        $template_data = $repository->get_by_id($template_id);
        
        if (!$template_data) {
            return null;
        }
        
        return new self($template_id, $template_data);
    }
    
    /**
     * Create context from template data array
     *
     * @param array $template_data Template data
     * @return self Context instance
     */
    public static function from_array(array $template_data): self {
        $template_id = (int) ($template_data['id'] ?? 0);
        return new self($template_id, $template_data);
    }
    
    // Interface implementation...
    public function get_type(): string {
        return 'template';
    }
}
```

### 6. WordPress Integration Patterns

```php
<?php

namespace AIPostScheduler\Admin;

use AIPostScheduler\Core\Logger;

class Settings {
    
    private Logger $logger;
    
    public function __construct(?Logger $logger = null) {
        $this->logger = $logger ?? new Logger();
        $this->register_hooks();
    }
    
    /**
     * Register WordPress hooks
     */
    private function register_hooks(): void {
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('admin_init', array($this, 'register_settings'));
    }
    
    /**
     * Add admin menu page
     */
    public function add_admin_menu(): void {
        add_options_page(
            __('AI Post Scheduler Settings', 'ai-post-scheduler'),
            __('AI Post Scheduler', 'ai-post-scheduler'),
            'manage_options',
            'aips-settings',
            array($this, 'render_settings_page')
        );
    }
    
    /**
     * Render settings page
     */
    public function render_settings_page(): void {
        if (!current_user_can('manage_options')) {
            return;
        }
        
        // Include template file
        include AIPS_PLUGIN_DIR . 'templates/admin/settings.php';
    }
}
```

---

## Migration Checklist for Each Class

When migrating a class, follow this checklist:

- [ ] Create new file in appropriate `src/AIPostScheduler/[Namespace]/` directory
- [ ] Add namespace declaration at top of file
- [ ] Add `use` statements for all dependencies
- [ ] Remove `AIPS_` prefix from class name
- [ ] Update `@package` and `@subpackage` PHPDoc tags
- [ ] Add PHP 8 type hints where appropriate
- [ ] Update internal class references to use new names
- [ ] Add class alias in `includes/class-aliases.php`
- [ ] Test that both old and new class names work
- [ ] Run relevant unit tests
- [ ] Update any related documentation

---

## Quick Reference: Common Transformations

| Pattern | Old Style | New Style |
|---------|-----------|-----------|
| **Class declaration** | `class AIPS_Logger {` | `namespace AIPostScheduler\Core;`<br>`class Logger {` |
| **Instantiation (old way)** | `new AIPS_Logger()` | Still works via alias |
| **Instantiation (new way)** | - | `use AIPostScheduler\Core\Logger;`<br>`new Logger()` |
| **Static call (old)** | `AIPS_Config::get_instance()` | Still works via alias |
| **Static call (new)** | - | `use AIPostScheduler\Core\Config;`<br>`Config::get_instance()` |
| **Type hint (old)** | `function log($message)` | `function log(string $message): void` |
| **Nullable type** | `$logger = null` | `?Logger $logger = null` |
| **Return type** | No return type | `: array`, `: int`, `: ?string` |
| **Interface implementation** | `implements AIPS_Generation_Context` | `use AIPostScheduler\Generation\Context\GenerationContextInterface;`<br>`implements GenerationContextInterface` |

---

## Troubleshooting

### Issue: Class not found after migration

**Cause:** Composer autoloader not regenerated  
**Solution:** Run `composer dump-autoload`

### Issue: Old class name not working

**Cause:** Class alias not added  
**Solution:** Add to `includes/class-aliases.php`:
```php
class_alias('AIPostScheduler\[Namespace]\[ClassName]', 'AIPS_[ClassName]');
```

### Issue: Test failures after migration

**Cause:** Mock objects using old class structure  
**Solution:** Update test to use new namespace or keep using old name (alias)

### Issue: Type errors in PHP 8+

**Cause:** Strict type checking with type hints  
**Solution:** Ensure passed values match type declarations, or make types nullable with `?`

---

**Document Version:** 1.0  
**Last Updated:** 2026-01-28  
**Related:** NAMESPACE_REFACTORING_PLAN.md
