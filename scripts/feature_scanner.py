#!/usr/bin/env python3
"""
Feature Scanner for AI Post Scheduler WordPress Plugin

This script analyzes the plugin's PHP files to generate comprehensive documentation including:
- Feature profiles with detailed information
- Mermaid flowcharts showing interactions between components
- Missing functionality and improvement recommendations
- Codebase standards compliance report (Container DI, Config, Cache, Ajax Registry,
  Ajax Response, Logger, Correlation ID, interface contracts)
"""

import re
import sys
from pathlib import Path
from typing import Dict, List
from collections import defaultdict


class FeatureScanner:
    """Scans WordPress plugin files to extract features, relationships, and standards compliance."""

    # Classes that own AJAX response logic and should use AIPS_Ajax_Response
    CONTROLLER_IDENTIFIERS = frozenset([
        'controller', 'admin_bar', 'planner', 'history', 'post_review',
        'data_management', 'seeder_admin', 'db_manager', 'voices',
        'dev_tools', 'onboarding_wizard', 'settings_ajax',
    ])

    # Infrastructure classes that are themselves exempt from container-DI checks
    CONTAINER_EXEMPT_CLASSES = frozenset([
        'AIPS_Container', 'AIPS_Autoloader', 'AIPS_Config',
        'AIPS_Cache_Factory', 'AIPS_Ajax_Registry', 'AIPS_Ajax_Response',
        'AIPS_Utilities', 'AIPS_Correlation_ID', 'AIPS_Error_Handler',
    ])

    # Heavy service classes that should be resolved from the container
    CONTAINER_MANAGED_SERVICES = frozenset([
        'AIPS_Logger', 'AIPS_History_Service', 'AIPS_History_Container',
        'AIPS_Resilience_Service', 'AIPS_AI_Service', 'AIPS_Cache',
    ])

    # Maximum characters shown for summary text in compact tables
    MAX_SUMMARY_LENGTH = 60

    def __init__(self, plugin_dir: str):
        self.plugin_dir = Path(plugin_dir)
        self.includes_dir = self.plugin_dir / "includes"
        self.features = {}
        self.interfaces = {}
        self.class_dependencies = defaultdict(set)
        self.method_calls = defaultdict(list)
        self.standards_violations = defaultdict(list)

    def scan_all_files(self) -> Dict:
        """Scan all PHP files recursively under includes/ and its subdirectories."""
        if not self.includes_dir.exists():
            print(f"Error: Directory {self.includes_dir} does not exist")
            sys.exit(1)

        # Recursively scan class files under includes/
        for php_file in sorted(self.includes_dir.rglob("class-aips-*.php")):
            self.analyze_file(php_file)

        # Recursively scan interface files under includes/
        for iface_file in sorted(self.includes_dir.rglob("interface-aips-*.php")):
            self.analyze_interface_file(iface_file)

        # Run standards compliance checks after all files are scanned
        self.check_standards_compliance()

        return self.features

    def analyze_interface_file(self, file_path: Path):
        """Analyze an interface file to extract interface information."""
        try:
            with open(file_path, 'r', encoding='utf-8') as f:
                content = f.read()
        except Exception as e:
            print(f"Error reading {file_path}: {e}")
            return

        iface_match = re.search(r'interface\s+(AIPS_[\w_]+)', content)
        if not iface_match:
            return

        iface_name = iface_match.group(1)
        methods = re.findall(r'public\s+(?:static\s+)?function\s+(\w+)\s*\(', content)
        summary = self.extract_class_summary(content)

        rel_path = file_path.relative_to(self.includes_dir)

        self.interfaces[iface_name] = {
            'name': iface_name,
            'file': str(rel_path),
            'methods': methods,
            'summary': summary,
            'lines_of_code': len(content.split('\n'))
        }

    def analyze_file(self, file_path: Path):
        """Analyze a single PHP file to extract feature information."""
        try:
            with open(file_path, 'r', encoding='utf-8') as f:
                content = f.read()
        except Exception as e:
            print(f"Error reading {file_path}: {e}")
            return

        # Extract class name
        class_match = re.search(r'class\s+(AIPS_[\w_]+)', content)
        if not class_match:
            return

        class_name = class_match.group(1)

        # Determine feature category from class name
        feature_name = self.extract_feature_name(class_name)

        # Extract various information
        methods = self.extract_methods(content)
        hooks = self.extract_hooks(content)
        dependencies = self.extract_dependencies(content)
        database_operations = self.extract_database_operations(content)
        ajax_handlers = self.extract_ajax_handlers(content)
        wp_api_usage = self.extract_wp_api_usage(content)
        infrastructure_usage = self.extract_infrastructure_usage(content)
        implements = self.extract_implements(content)

        # Extract docblock summary
        summary = self.extract_class_summary(content)

        # Compute relative path for diagnostics subdirectory files
        rel_path = file_path.relative_to(self.includes_dir)

        # Store feature information
        self.features[class_name] = {
            'name': feature_name,
            'file': str(rel_path),
            'class': class_name,
            'summary': summary,
            'methods': methods,
            'hooks': hooks,
            'dependencies': dependencies,
            'database_operations': database_operations,
            'ajax_handlers': ajax_handlers,
            'wp_api_usage': wp_api_usage,
            'infrastructure_usage': infrastructure_usage,
            'implements': implements,
            'lines_of_code': len(content.split('\n')),
            '_raw_content': content,
        }

        # Track dependencies for flowchart generation
        for dep in dependencies:
            self.class_dependencies[class_name].add(dep)

    def extract_feature_name(self, class_name: str) -> str:
        """Convert class name to human-readable feature name."""
        # Remove AIPS_ prefix and convert to title case
        name = class_name.replace('AIPS_', '').replace('_', ' ')
        return ' '.join(word.capitalize() for word in name.split())

    def extract_class_summary(self, content: str) -> str:
        """Extract class docblock summary."""
        # Look for docblock immediately preceding the class declaration
        # Match /** ... */ followed by class AIPS_...
        # Use a simpler pattern to avoid catastrophic backtracking
        pattern = r'/\*\*\s*\n\s*\*\s*([^\n]+)\n.*?\*/\s*\n\s*class\s+AIPS_'
        match = re.search(pattern, content, re.DOTALL)
        if match:
            # Extract the first line of the docblock (the summary)
            summary_line = match.group(1).strip()
            return summary_line
        return "No description available"

    def extract_methods(self, content: str) -> List[str]:
        """Extract public method names."""
        pattern = r'public\s+(?:static\s+)?function\s+(\w+)\s*\('
        return re.findall(pattern, content)

    def extract_hooks(self, content: str) -> Dict[str, List[str]]:
        """Extract WordPress hooks (actions and filters)."""
        hooks = {'actions': [], 'filters': []}
        
        # Find do_action calls
        actions = re.findall(r'do_action\s*\(\s*[\'"]([^\'"]+)[\'"]', content)
        hooks['actions'].extend(actions)
        
        # Find apply_filters calls
        filters = re.findall(r'apply_filters\s*\(\s*[\'"]([^\'"]+)[\'"]', content)
        hooks['filters'].extend(filters)
        
        # Find add_action calls
        add_actions = re.findall(r'add_action\s*\(\s*[\'"]([^\'"]+)[\'"]', content)
        hooks['actions'].extend(add_actions)
        
        # Find add_filter calls
        add_filters = re.findall(r'add_filter\s*\(\s*[\'"]([^\'"]+)[\'"]', content)
        hooks['filters'].extend(add_filters)
        
        return hooks

    def extract_dependencies(self, content: str) -> List[str]:
        """Extract class dependencies."""
        dependencies = set()
        
        # Look for new Class_Name instantiations
        pattern = r'new\s+(AIPS_[\w_]+)\s*\('
        dependencies.update(re.findall(pattern, content))
        
        # Look for Class_Name:: static calls
        pattern = r'(AIPS_[\w_]+)::'
        dependencies.update(re.findall(pattern, content))
        
        # Look for constructor parameters with type hints
        pattern = r'function\s+__construct\s*\([^)]*?(AIPS_[\w_]+)'
        dependencies.update(re.findall(pattern, content))
        
        return sorted(list(dependencies))

    def extract_database_operations(self, content: str) -> Dict[str, bool]:
        """Detect database operations."""
        return {
            'uses_wpdb': '$wpdb' in content,
            'has_repository': 'Repository' in content or '_repository' in content.lower(),
            'creates_tables': 'dbDelta' in content or 'CREATE TABLE' in content,
            'has_migrations': 'migration' in content.lower()
        }

    def extract_ajax_handlers(self, content: str) -> List[str]:
        """Extract AJAX handler names."""
        pattern = r'wp_ajax_(\w+)'
        return re.findall(pattern, content)

    def extract_wp_api_usage(self, content: str) -> Dict[str, bool]:
        """Detect WordPress API usage patterns."""
        return {
            'uses_cron': 'wp_schedule' in content or 'wp_cron' in content,
            'uses_rest_api': 'register_rest_route' in content or 'WP_REST' in content,
            'uses_transients': 'set_transient' in content or 'get_transient' in content,
            'uses_options': 'get_option' in content or 'update_option' in content,
            'uses_post_meta': 'get_post_meta' in content or 'update_post_meta' in content,
            'uses_user_meta': 'get_user_meta' in content or 'update_user_meta' in content
        }

    def extract_infrastructure_usage(self, content: str) -> Dict[str, bool]:
        """Detect usage of plugin infrastructure patterns."""
        return {
            'uses_container': 'AIPS_Container' in content,
            'uses_config': 'AIPS_Config' in content,
            'uses_cache': bool(re.search(r'AIPS_Cache(?:_Factory)?(?:::|->)', content)),
            'uses_ajax_response': 'AIPS_Ajax_Response' in content,
            'uses_logger': 'AIPS_Logger' in content,
            'uses_telemetry': 'AIPS_Telemetry' in content,
            'uses_correlation_id': 'AIPS_Correlation_ID' in content,
            'uses_error_handler': 'AIPS_Error_Handler' in content,
            'uses_history_service': bool(re.search(r'AIPS_History_Service|history_service', content)),
            'uses_resilience': 'AIPS_Resilience_Service' in content,
            'raw_get_option': bool(re.search(r'\bget_option\s*\(', content)),
            'raw_error_log': 'error_log(' in content,
            'raw_wp_send_json': bool(re.search(r'wp_send_json(?:_success|_error)?\s*\(', content)),
        }

    def extract_implements(self, content: str) -> List[str]:
        """Extract interfaces that the class implements."""
        pattern = r'class\s+AIPS_[\w_]+\s+(?:extends\s+[\w_]+\s+)?implements\s+([\w_,\s]+)'
        match = re.search(pattern, content)
        if match:
            interfaces_str = match.group(1)
            return [i.strip() for i in interfaces_str.split(',') if i.strip()]
        return []

    # ---------------------------------------------------------------
    # Standards compliance checks
    # ---------------------------------------------------------------

    def check_standards_compliance(self):
        """Run all codebase standards compliance checks across scanned files."""
        for class_name, feature in self.features.items():
            content = feature.get('_raw_content', '')
            if not content:
                continue

            self._check_ajax_constructor_hooks(class_name, content)
            self._check_raw_get_option(class_name, content, feature)
            self._check_raw_wp_send_json(class_name, content, feature)
            self._check_container_usage(class_name, content, feature)
            self._check_raw_error_log(class_name, content)
            self._check_raw_wpdb_outside_repo(class_name, content, feature)

    def _check_ajax_constructor_hooks(self, class_name: str, content: str):
        """Flag AJAX hooks registered inside __construct instead of AIPS_Ajax_Registry."""
        # Skip the registry itself and the bootstrap file
        if class_name in ('AIPS_Ajax_Registry',):
            return
        # Find constructor body
        ctor_match = re.search(
            r'function\s+__construct\s*\([^)]*\)\s*\{',
            content
        )
        if not ctor_match:
            return
        # Get content from constructor opening brace onward, then extract the body
        ctor_start = ctor_match.end()
        brace_depth = 1
        pos = ctor_start
        while pos < len(content) and brace_depth > 0:
            if content[pos] == '{':
                brace_depth += 1
            elif content[pos] == '}':
                brace_depth -= 1
            pos += 1
        ctor_body = content[ctor_start:pos]
        ajax_in_ctor = re.findall(r"add_action\s*\(\s*['\"]wp_ajax_(?:nopriv_)?(\w+)['\"]", ctor_body)
        if ajax_in_ctor:
            self.standards_violations[class_name].append({
                'rule': 'ajax_registry',
                'severity': 'warning',
                'message': (
                    f"Registers {len(ajax_in_ctor)} AJAX hook(s) in constructor "
                    f"instead of via AIPS_Ajax_Registry: {', '.join(ajax_in_ctor[:5])}"
                ),
            })

    # Classes that legitimately use raw get_option() for plugin keys
    # (e.g. bootstrap timing, circular-dependency avoidance).
    CONFIG_EXEMPT_CLASSES = frozenset([
        'AIPS_Config', 'AIPS_Upgrades', 'AIPS_DB_Manager',
        'AIPS_Cache_Factory', 'AIPS_Telemetry',
    ])

    def _check_raw_get_option(self, class_name: str, content: str, feature: Dict):
        """Flag direct get_option() calls for plugin-owned keys outside AIPS_Config.

        Only flags calls whose option key starts with ``aips_``.  WordPress
        core options (e.g. ``admin_email``) are ignored because they do not
        belong to the plugin's configuration layer.
        """
        if class_name in self.CONFIG_EXEMPT_CLASSES:
            return
        # Match standalone get_option( 'aips_...' ) but NOT ->get_option()
        raw_calls = re.findall(
            r"""(?<!->)\bget_option\s*\(\s*['"]aips_""",
            content,
        )
        if raw_calls:
            self.standards_violations[class_name].append({
                'rule': 'config_usage',
                'severity': 'info',
                'message': (
                    f"Uses raw get_option() for plugin keys {len(raw_calls)} time(s) — "
                    f"prefer AIPS_Config::get_instance()->get_option()"
                ),
            })

    def _check_raw_wp_send_json(self, class_name: str, content: str, feature: Dict):
        """Flag direct wp_send_json calls in controllers instead of AIPS_Ajax_Response."""
        base = class_name.lower()
        is_controller = any(x in base for x in self.CONTROLLER_IDENTIFIERS)
        if not is_controller:
            return
        if class_name == 'AIPS_Ajax_Response':
            return
        raw_calls = re.findall(r'wp_send_json(?:_success|_error)?\s*\(', content)
        if raw_calls:
            self.standards_violations[class_name].append({
                'rule': 'ajax_response',
                'severity': 'info',
                'message': (
                    f"Uses raw wp_send_json*() {len(raw_calls)} time(s) — "
                    f"prefer AIPS_Ajax_Response::success()/error()"
                ),
            })

    def _check_container_usage(self, class_name: str, content: str, feature: Dict):
        """Flag classes that instantiate heavy AIPS_ dependencies without using the container."""
        if class_name in self.CONTAINER_EXEMPT_CLASSES:
            return
        # Look for `new AIPS_*` that are NOT in the container-resolved set of infra classes
        instantiations = re.findall(r'new\s+(AIPS_[\w_]+)\s*\(', content)
        direct_infra = [c for c in instantiations if c in self.CONTAINER_MANAGED_SERVICES]
        if direct_infra and 'AIPS_Container' not in content:
            unique = sorted(set(direct_infra))
            self.standards_violations[class_name].append({
                'rule': 'container_di',
                'severity': 'info',
                'message': (
                    f"Directly instantiates {', '.join(unique)} without "
                    f"using AIPS_Container — consider resolving from the container"
                ),
            })

    def _check_raw_error_log(self, class_name: str, content: str):
        """Flag raw error_log() calls instead of AIPS_Logger."""
        if class_name in ('AIPS_Logger',):
            return
        raw_calls = re.findall(r'\berror_log\s*\(', content)
        if raw_calls:
            self.standards_violations[class_name].append({
                'rule': 'logger_usage',
                'severity': 'info',
                'message': (
                    f"Uses raw error_log() {len(raw_calls)} time(s) — "
                    f"prefer AIPS_Logger for structured logging"
                ),
            })

    def _check_raw_wpdb_outside_repo(self, class_name: str, content: str, feature: Dict):
        """Flag $wpdb usage outside repository classes."""
        if 'Repository' in class_name or class_name in ('AIPS_DB_Manager', 'AIPS_Upgrades'):
            return
        if '$wpdb' in content:
            self.standards_violations[class_name].append({
                'rule': 'repository_sql',
                'severity': 'warning',
                'message': (
                    "Uses $wpdb directly — SQL should be in a Repository class"
                ),
            })

    def categorize_features(self) -> Dict[str, List[str]]:
        """Categorize features into logical groups."""
        categories = {
            'Core Generation': [],
            'Scheduling & Automation': [],
            'Content Management': [],
            'AI Integration': [],
            'Infrastructure & DI': [],
            'Caching': [],
            'Telemetry & Observability': [],
            'Notifications': [],
            'Sources & Research': [],
            'Internal Links & Embeddings': [],
            'Resilience & Reliability': [],
            'User Interface & Admin': [],
            'Data Management': [],
            'Database & Repositories': [],
            'Diagnostics': [],
            'Configuration & Settings': [],
            'Onboarding': [],
            'Utilities': [],
        }

        for class_name, feature in self.features.items():
            name_lower = class_name.lower()
            base_name = re.sub(r'^aips_', '', name_lower)

            # Order matters — more specific patterns first.
            if any(x in base_name for x in ['cache_factory', 'cache_array', 'cache_db',
                                              'cache_redis', 'cache_session',
                                              'cache_wp_object']):
                categories['Caching'].append(class_name)
            elif base_name == 'cache':
                categories['Caching'].append(class_name)
            elif any(x in base_name for x in ['container', 'autoloader', 'ajax_registry',
                                                'ajax_response', 'error_handler',
                                                'correlation_id']):
                categories['Infrastructure & DI'].append(class_name)
            elif any(x in base_name for x in ['telemetry', 'logger', 'generation_logger']):
                categories['Telemetry & Observability'].append(class_name)
            elif any(x in base_name for x in ['notification', 'partial_generation']):
                categories['Notifications'].append(class_name)
            elif any(x in base_name for x in ['source', 'research', 'trending_topic']):
                categories['Sources & Research'].append(class_name)
            elif any(x in base_name for x in ['internal_link', 'embedding']):
                categories['Internal Links & Embeddings'].append(class_name)
            elif any(x in base_name for x in ['resilience', 'token_budget']):
                categories['Resilience & Reliability'].append(class_name)
            elif any(x in base_name for x in ['system_diagnostics']):
                categories['Diagnostics'].append(class_name)
            elif any(x in base_name for x in ['onboarding']):
                categories['Onboarding'].append(class_name)
            elif any(x in base_name for x in ['config', 'settings', 'site_context']):
                categories['Configuration & Settings'].append(class_name)
            elif any(x in base_name for x in ['generator', 'generation', 'prompt_builder',
                                                'template_processor', 'template_context',
                                                'topic_context', 'generation_context',
                                                'generation_session', 'generation_result',
                                                'generation_execution', 'topic_expansion',
                                                'topic_penalty', 'content_auditor',
                                                'markdown_parser', 'post_creator',
                                                'image_service']):
                categories['Core Generation'].append(class_name)
            elif any(x in base_name for x in ['scheduler', 'cron', 'schedule_processor',
                                                'schedule_entry', 'interval_calculator',
                                                'unified_schedule']):
                categories['Scheduling & Automation'].append(class_name)
            elif any(x in base_name for x in ['template', 'voice', 'article_structure',
                                                'prompt_section', 'author', 'planner',
                                                'seeder', 'post_review', 'feedback',
                                                'calendar', 'template_type',
                                                'template_data', 'template_helper',
                                                'post_manager']):
                categories['Content Management'].append(class_name)
            elif any(x in base_name for x in ['data_management', 'export', 'import']):
                categories['Data Management'].append(class_name)
            elif any(x in base_name for x in ['controller', 'admin_asset', 'admin_bar',
                                                'admin_menu', 'dashboard', 'dev_tools']):
                categories['User Interface & Admin'].append(class_name)
            elif any(x in base_name for x in ['ai_service']):
                categories['AI Integration'].append(class_name)
            elif any(x in base_name for x in ['repository', 'db_manager', 'upgrade',
                                                'history_type', 'metrics']):
                categories['Database & Repositories'].append(class_name)
            elif any(x in base_name for x in ['system_status']):
                categories['Diagnostics'].append(class_name)
            else:
                categories['Utilities'].append(class_name)

        # Remove empty categories
        return {k: v for k, v in categories.items() if v}

    def identify_missing_functionality(self, class_name: str, feature: Dict) -> List[str]:
        """Identify potential missing functionality based on feature analysis."""
        missing = []

        # Check for common patterns
        if 'Repository' in class_name:
            if not feature['methods']:
                missing.append("No public methods defined for data access")
            if not any('get' in m.lower() for m in feature['methods']):
                missing.append("Missing getter methods for data retrieval")
            if not any('save' in m.lower() or 'update' in m.lower() or 'insert' in m.lower()
                       for m in feature['methods']):
                missing.append("Missing save/update methods for data persistence")

        if 'Controller' in class_name:
            if not feature['ajax_handlers'] and not feature['hooks']['actions']:
                missing.append("No AJAX handlers or action hooks registered")

        if 'Generator' in class_name:
            if not feature['hooks']['filters']:
                missing.append("No filter hooks for customizing generation output")
            if 'error' not in str(feature['methods']).lower():
                missing.append("No dedicated error handling methods visible")

        if 'Service' in class_name:
            if not feature['dependencies']:
                missing.append("No visible dependencies — may be tightly coupled")

        # Check for structured logging in services/generators/schedulers
        name_lower = class_name.lower()
        important_class = any(x in name_lower for x in ['generator', 'service', 'scheduler',
                                                          'processor'])
        if important_class:
            infra = feature.get('infrastructure_usage', {})
            if not infra.get('uses_logger') and not infra.get('uses_history_service'):
                missing.append("No AIPS_Logger or AIPS_History_Service usage for observability")

        # Check for interface contracts on key services
        if 'Service' in class_name or 'Repository' in class_name:
            if not feature.get('implements'):
                missing.append("Does not implement an interface — consider adding a contract")

        # Check for validation in controllers/services
        if 'Controller' in class_name or 'Service' in class_name:
            if not any('validat' in m.lower() for m in feature['methods']):
                missing.append("No input validation methods visible")

        return missing

    def suggest_improvements(self, class_name: str, feature: Dict) -> List[str]:
        """Suggest potential improvements for the feature."""
        improvements = []
        infra = feature.get('infrastructure_usage', {})
        violations = self.standards_violations.get(class_name, [])

        # Surface standards violations as improvement suggestions
        for v in violations:
            improvements.append(f"[{v['severity'].upper()}] {v['message']}")

        # Check code size
        if feature['lines_of_code'] > 500:
            improvements.append(
                f"Consider refactoring — class has {feature['lines_of_code']} lines (may violate SRP)"
            )

        # Check method count
        if len(feature['methods']) > 20:
            improvements.append(
                f"High method count ({len(feature['methods'])}+ methods) — consider splitting responsibilities"
            )

        # Check dependencies
        if len(feature['dependencies']) > 8:
            improvements.append(
                f"High coupling — depends on {len(feature['dependencies'])} classes"
            )

        # Repository pattern check
        if feature['database_operations']['uses_wpdb'] and not feature['database_operations']['has_repository']:
            if 'Repository' not in class_name and class_name not in ('AIPS_DB_Manager', 'AIPS_Upgrades'):
                improvements.append(
                    "Consider using Repository pattern for database access instead of direct $wpdb"
                )

        # Container DI check for classes with many constructor dependencies
        if not infra.get('uses_container'):
            dep_count = len(feature['dependencies'])
            if dep_count > 3 and 'Controller' in class_name:
                improvements.append(
                    "Consider resolving dependencies from AIPS_Container instead of direct instantiation"
                )

        # Config check
        wp_api = feature.get('wp_api_usage', {})
        if wp_api.get('uses_options') and not infra.get('uses_config'):
            if class_name not in ('AIPS_Config', 'AIPS_Upgrades', 'AIPS_DB_Manager'):
                improvements.append(
                    "Uses get_option()/update_option() — migrate to AIPS_Config for caching and defaults"
                )

        # Cache check for services with expensive operations
        if 'Service' in class_name and not infra.get('uses_cache'):
            if not wp_api.get('uses_transients'):
                improvements.append(
                    "Consider using AIPS_Cache for caching expensive operations"
                )

        # Hook documentation
        if feature['hooks']['actions'] or feature['hooks']['filters']:
            custom_hooks = [h for h in feature['hooks']['actions'] + feature['hooks']['filters']
                           if h.startswith('aips_')]
            if custom_hooks:
                improvements.append("Document custom hooks in HOOKS.md for third-party developers")

        # Documentation
        if not feature['summary'] or feature['summary'] == "No description available":
            improvements.append("Add comprehensive class-level PHPDoc documentation")

        return improvements[:8]  # Return top 8 suggestions

    def generate_mermaid_flowchart(self, category: str, classes: List[str]) -> str:
        """Generate a Mermaid flowchart for a feature category."""
        chart = f"```mermaid\nflowchart TD\n"
        chart += f"    %% {category} Architecture\n\n"
        
        # Track node types for styling
        repository_nodes = []
        service_nodes = []
        controller_nodes = []
        
        # Create nodes
        for class_name in classes:
            feature = self.features.get(class_name, {})
            # Keep underscores to avoid ID collisions and make nodes traceable
            node_id = class_name.replace('AIPS_', '')
            display_name = feature.get('name', class_name)
            
            # Use different shapes for different types
            if 'Repository' in class_name:
                chart += f"    {node_id}[(\"{display_name}\")]\n"
                repository_nodes.append(node_id)
            elif 'Controller' in class_name:
                chart += f"    {node_id}[\"{display_name}\"]\n"
                controller_nodes.append(node_id)
            elif 'Service' in class_name:
                chart += f"    {node_id}{{\"{display_name}\"}}\n"
                service_nodes.append(node_id)
            else:
                chart += f"    {node_id}[\"{display_name}\"]\n"
        
        chart += "\n"
        
        # Create edges based on dependencies (sorted for deterministic output)
        for class_name in classes:
            source_id = class_name.replace('AIPS_', '')
            dependencies = self.class_dependencies.get(class_name, set())

            for dep in sorted(dependencies):
                if dep in classes:  # Only show dependencies within this category
                    target_id = dep.replace('AIPS_', '')
                    chart += f"    {source_id} --> {target_id}\n"
        
        # Add styling definitions
        chart += "\n    classDef repository fill:#e1f5ff,stroke:#01579b,stroke-width:2px\n"
        chart += "    classDef service fill:#fff3e0,stroke:#e65100,stroke-width:2px\n"
        chart += "    classDef controller fill:#f3e5f5,stroke:#4a148c,stroke-width:2px\n"
        
        # Apply styles to nodes
        if repository_nodes:
            chart += f"    class {','.join(repository_nodes)} repository\n"
        if service_nodes:
            chart += f"    class {','.join(service_nodes)} service\n"
        if controller_nodes:
            chart += f"    class {','.join(controller_nodes)} controller\n"
        
        chart += "```\n"
        
        return chart

    def generate_detailed_architecture_diagram(self) -> str:
        """Generate a comprehensive architecture diagram showing all major components."""
        chart = "```mermaid\nflowchart TB\n"
        chart += "    %% Overall Plugin Architecture\n\n"

        # Define major components
        chart += "    UI[User Interface Layer]\n"
        chart += "    CTRL[Controller Layer]\n"
        chart += "    SVC[Service Layer]\n"
        chart += "    REPO[Repository Layer]\n"
        chart += "    DB[(Database)]\n"
        chart += "    AI[AI Engine API]\n"
        chart += "    WP[WordPress Core]\n\n"

        # Infrastructure components
        chart += "    CONT[AIPS_Container - DI]\n"
        chart += "    CFG[AIPS_Config]\n"
        chart += "    CACHE[AIPS_Cache]\n"
        chart += "    REG[AIPS_Ajax_Registry]\n"
        chart += "    LOG[AIPS_Logger]\n"
        chart += "    TEL[AIPS_Telemetry]\n"
        chart += "    RES[AIPS_Resilience_Service]\n\n"

        # Define connections
        chart += "    UI --> CTRL\n"
        chart += "    CTRL --> SVC\n"
        chart += "    SVC --> REPO\n"
        chart += "    SVC --> AI\n"
        chart += "    REPO --> DB\n"
        chart += "    CTRL --> WP\n"
        chart += "    SVC --> WP\n\n"

        # Infrastructure connections
        chart += "    CONT -.-> CTRL\n"
        chart += "    CONT -.-> SVC\n"
        chart += "    REG -.-> CTRL\n"
        chart += "    CFG -.-> SVC\n"
        chart += "    CACHE -.-> REPO\n"
        chart += "    LOG -.-> SVC\n"
        chart += "    TEL -.-> SVC\n"
        chart += "    RES -.-> AI\n\n"

        # Add subgraphs for each layer
        categories = self.categorize_features()

        if 'User Interface & Admin' in categories:
            chart += "    subgraph Controllers\n"
            for cls in categories['User Interface & Admin'][:4]:
                node_id = cls.replace('AIPS_', '').replace('_', '')
                chart += f"        {node_id}\n"
            chart += "    end\n\n"

        svc_classes = []
        for cat in ('AI Integration', 'Core Generation', 'Resilience & Reliability'):
            svc_classes.extend(categories.get(cat, []))
        if svc_classes:
            chart += "    subgraph Services\n"
            for cls in svc_classes[:4]:
                node_id = cls.replace('AIPS_', '').replace('_', '')
                chart += f"        {node_id}\n"
            chart += "    end\n\n"

        repo_classes = categories.get('Database & Repositories', [])
        if repo_classes:
            chart += "    subgraph Repositories\n"
            for cls in repo_classes[:4]:
                node_id = cls.replace('AIPS_', '').replace('_', '')
                chart += f"        {node_id}\n"
            chart += "    end\n\n"

        infra_classes = (categories.get('Infrastructure & DI', [])
                         + categories.get('Caching', []))
        if infra_classes:
            chart += "    subgraph Infrastructure\n"
            for cls in infra_classes[:5]:
                node_id = cls.replace('AIPS_', '').replace('_', '')
                chart += f"        {node_id}\n"
            chart += "    end\n\n"

        chart += "    Controllers --> Services\n"
        chart += "    Services --> Repositories\n\n"

        # Add styling
        chart += "    style UI fill:#e8f5e9,stroke:#2e7d32\n"
        chart += "    style CTRL fill:#fff3e0,stroke:#ef6c00\n"
        chart += "    style SVC fill:#e3f2fd,stroke:#1565c0\n"
        chart += "    style REPO fill:#fce4ec,stroke:#c2185b\n"
        chart += "    style DB fill:#f3e5f5,stroke:#7b1fa2\n"
        chart += "    style AI fill:#fff9c4,stroke:#f57f17\n"
        chart += "    style WP fill:#e0e0e0,stroke:#424242\n"
        chart += "    style CONT fill:#e8eaf6,stroke:#283593\n"
        chart += "    style CFG fill:#e8eaf6,stroke:#283593\n"
        chart += "    style CACHE fill:#e8eaf6,stroke:#283593\n"
        chart += "    style REG fill:#e8eaf6,stroke:#283593\n"
        chart += "    style LOG fill:#e8eaf6,stroke:#283593\n"
        chart += "    style TEL fill:#e8eaf6,stroke:#283593\n"
        chart += "    style RES fill:#e8eaf6,stroke:#283593\n"

        chart += "```\n"

        return chart

    def generate_profiles_summary(self, output_file: str):
        """Generate a summarized Feature Profiles document (no technical details)."""
        lines = []

        lines.append("## Feature Profiles\n\n")

        for class_name in sorted(self.features.keys()):
            feature = self.features[class_name]
            lines.append(f"### {feature['name']}\n")
            lines.append(f"* **Summary**: {feature['summary']}\n")
            lines.append(f"* **File**: `ai-post-scheduler/includes/{feature['file']}`\n")
            lines.append(f"* **Class**: `{class_name}`\n")

            # Implements interfaces
            if feature.get('implements'):
                lines.append(f"* **Implements**: {', '.join(f'`{i}`' for i in feature['implements'])}\n")

            # Missing functionality
            missing = self.identify_missing_functionality(class_name, feature)
            if not missing:
                lines.append("* **Missing Functionality**: None identified\n")
            elif len(missing) == 1:
                lines.append(f"* **Missing Functionality**: {missing[0]}\n")
            else:
                lines.append("* **Missing Functionality**: \n")
                for item in missing:
                    lines.append(f"    * {item}\n")

            # Recommended improvements
            improvements = self.suggest_improvements(class_name, feature)
            if improvements:
                lines.append("* **Recommended Improvements**: \n")
                for i, item in enumerate(improvements, 1):
                    lines.append(f"    {i}. {item}\n")

            lines.append("\n---\n\n")

        new_content = ''.join(lines)

        output_path = Path(output_file)
        should_write = True

        if output_path.exists():
            try:
                with open(output_file, 'r', encoding='utf-8') as f:
                    existing_content = f.read()
                if existing_content == new_content:
                    should_write = False
                    print(f"✓ Feature profiles summary unchanged: {output_file}")
            except Exception as e:
                print(f"Warning: Could not read existing profiles summary file: {e}")

        if should_write:
            with open(output_file, 'w', encoding='utf-8') as f:
                f.write(new_content)
            print(f"✓ Feature profiles summary updated: {output_file}")
        else:
            print(f"✓ Feature profiles summary is up to date: {output_file}")

    def generate_report(self, output_file: str):
        """Generate the complete feature report in Markdown format."""
        categories = self.categorize_features()

        # Build the report content in memory first (without timestamp)
        report_lines = []

        # Header
        report_lines.append("# AI Post Scheduler - Feature Documentation\n\n")
        report_lines.append("*Generated by the feature scanner script.*\n\n")
        report_lines.append("---\n\n")

        # Table of Contents
        report_lines.append("## Table of Contents\n\n")
        report_lines.append("1. [Overview](#overview)\n")
        report_lines.append("2. [Architecture Diagram](#architecture-diagram)\n")
        report_lines.append("3. [Feature Categories](#feature-categories)\n")
        for category in categories.keys():
            anchor = category.lower().replace(' ', '-').replace('&', 'and')
            report_lines.append(f"   - [{category}](#{anchor})\n")
        report_lines.append("4. [Interface Contracts](#interface-contracts)\n")
        report_lines.append("5. [Feature Profiles](#feature-profiles)\n")
        report_lines.append("6. [Codebase Standards Compliance](#codebase-standards-compliance)\n")
        report_lines.append("7. [Infrastructure Adoption](#infrastructure-adoption)\n")
        report_lines.append("8. [Summary Statistics](#summary-statistics)\n\n")

        # Overview
        report_lines.append("## Overview\n\n")
        report_lines.append(
            f"This document provides comprehensive documentation for the AI Post Scheduler WordPress plugin. "
        )
        report_lines.append(
            f"The plugin consists of **{len(self.features)} core classes** and "
            f"**{len(self.interfaces)} interfaces** organized into "
        )
        report_lines.append(f"**{len(categories)} functional categories**.\n\n")

        total_loc = sum(f['lines_of_code'] for f in self.features.values())
        report_lines.append(f"- **Total Lines of Code**: {total_loc:,}\n")
        report_lines.append(f"- **Total Classes**: {len(self.features)}\n")
        report_lines.append(f"- **Total Interfaces**: {len(self.interfaces)}\n")
        report_lines.append(f"- **Categories**: {', '.join(categories.keys())}\n\n")

        # Architecture Diagram
        report_lines.append("## Architecture Diagram\n\n")
        report_lines.append("### Overall Plugin Architecture\n\n")
        report_lines.append(self.generate_detailed_architecture_diagram())
        report_lines.append("\n")

        # Feature Categories with Flowcharts
        report_lines.append("## Feature Categories\n\n")

        for category, classes in categories.items():
            report_lines.append(f"### {category}\n\n")
            report_lines.append(f"This category contains {len(classes)} classes:\n\n")

            for class_name in classes:
                feature = self.features[class_name]
                report_lines.append(
                    f"- **{feature['name']}** (`{class_name}`): {feature['summary']}\n"
                )

            report_lines.append(f"\n#### {category} Architecture\n\n")
            report_lines.append(self.generate_mermaid_flowchart(category, classes))
            report_lines.append("\n")

        # Interface Contracts
        report_lines.append("## Interface Contracts\n\n")
        if self.interfaces:
            report_lines.append(
                f"The plugin defines **{len(self.interfaces)} interfaces** as formal contracts:\n\n"
            )
            report_lines.append("| Interface | File | Methods | Summary |\n")
            report_lines.append("|-----------|------|---------|---------|\n")
            for iface_name in sorted(self.interfaces.keys()):
                iface = self.interfaces[iface_name]
                method_count = len(iface['methods'])
                max_len = self.MAX_SUMMARY_LENGTH
                summary = (iface['summary'][:max_len] + "..."
                           if len(iface['summary']) > max_len
                           else iface['summary'])
                report_lines.append(
                    f"| `{iface_name}` | `{iface['file']}` | {method_count} | {summary} |\n"
                )

            # Show which classes implement each interface
            report_lines.append("\n### Interface Implementations\n\n")
            for iface_name in sorted(self.interfaces.keys()):
                implementors = [cn for cn, f in self.features.items()
                                if iface_name in f.get('implements', [])]
                if implementors:
                    impls = ", ".join(f"`{c}`" for c in implementors)
                    report_lines.append(f"- **`{iface_name}`**: {impls}\n")
                else:
                    report_lines.append(f"- **`{iface_name}`**: *(no implementors found)*\n")
        else:
            report_lines.append("No interfaces found.\n")
        report_lines.append("\n")

        # Feature Profiles
        report_lines.append("## Feature Profiles\n\n")
        report_lines.append(
            "Detailed analysis of each feature including files, functionality, and recommendations.\n\n"
        )

        for class_name in sorted(self.features.keys()):
            feature = self.features[class_name]
            report_lines.append(f"### {feature['name']}\n\n")

            # High-level Summary
            report_lines.append(f"**Summary**: {feature['summary']}\n\n")

            # Files Involved
            report_lines.append(f"**File**: `ai-post-scheduler/includes/{feature['file']}`\n\n")
            report_lines.append(f"**Class**: `{class_name}`\n\n")
            report_lines.append(f"**Lines of Code**: {feature['lines_of_code']}\n\n")

            # Implements
            if feature.get('implements'):
                report_lines.append(
                    f"**Implements**: {', '.join(f'`{i}`' for i in feature['implements'])}\n\n"
                )

            # Technical Details
            report_lines.append("**Technical Details**:\n\n")

            if feature['methods']:
                report_lines.append(f"- **Public Methods** ({len(feature['methods'])}): ")
                report_lines.append(", ".join(f"`{m}()`" for m in feature['methods'][:10]))
                if len(feature['methods']) > 10:
                    report_lines.append(f", ... and {len(feature['methods']) - 10} more")
                report_lines.append("\n")

            if feature['dependencies']:
                report_lines.append(f"- **Dependencies** ({len(feature['dependencies'])}): ")
                report_lines.append(", ".join(f"`{d}`" for d in feature['dependencies']))
                report_lines.append("\n")

            if feature['hooks']['actions']:
                report_lines.append(
                    f"- **Action Hooks** ({len(feature['hooks']['actions'])}): "
                )
                unique_actions = sorted(set(feature['hooks']['actions'][:5]))
                report_lines.append(", ".join(f"`{h}`" for h in unique_actions))
                if len(feature['hooks']['actions']) > 5:
                    report_lines.append(
                        f", ... and {len(feature['hooks']['actions']) - 5} more"
                    )
                report_lines.append("\n")

            if feature['hooks']['filters']:
                report_lines.append(
                    f"- **Filter Hooks** ({len(feature['hooks']['filters'])}): "
                )
                unique_filters = sorted(set(feature['hooks']['filters'][:5]))
                report_lines.append(", ".join(f"`{h}`" for h in unique_filters))
                if len(feature['hooks']['filters']) > 5:
                    report_lines.append(
                        f", ... and {len(feature['hooks']['filters']) - 5} more"
                    )
                report_lines.append("\n")

            if feature['ajax_handlers']:
                report_lines.append("- **AJAX Handlers**: ")
                report_lines.append(
                    ", ".join(f"`wp_ajax_{h}`" for h in feature['ajax_handlers'])
                )
                report_lines.append("\n")

            # Database operations
            db_ops = feature['database_operations']
            db_features = [k.replace('_', ' ').title() for k, v in db_ops.items() if v]
            if db_features:
                report_lines.append(f"- **Database Operations**: {', '.join(db_features)}\n")

            # WordPress API usage
            wp_api = [
                k.replace('uses_', '').replace('_', ' ').title()
                for k, v in feature['wp_api_usage'].items() if v
            ]
            if wp_api:
                report_lines.append(f"- **WordPress APIs Used**: {', '.join(wp_api)}\n")

            # Infrastructure usage
            infra = feature.get('infrastructure_usage', {})
            infra_used = [
                k.replace('uses_', '').replace('_', ' ').title()
                for k, v in infra.items()
                if v and k.startswith('uses_')
            ]
            if infra_used:
                report_lines.append(
                    f"- **Infrastructure**: {', '.join(infra_used)}\n"
                )

            report_lines.append("\n")

            # Missing Functionality
            missing = self.identify_missing_functionality(class_name, feature)
            if missing:
                report_lines.append("**Missing Functionality**:\n\n")
                for item in missing:
                    report_lines.append(f"- {item}\n")
                report_lines.append("\n")
            else:
                report_lines.append("**Missing Functionality**: None identified\n\n")

            # Recommended Improvements
            improvements = self.suggest_improvements(class_name, feature)
            if improvements:
                report_lines.append("**Recommended Improvements**:\n\n")
                for i, item in enumerate(improvements, 1):
                    report_lines.append(f"{i}. {item}\n")
                report_lines.append("\n")

            report_lines.append("---\n\n")

        # Codebase Standards Compliance
        report_lines.append("## Codebase Standards Compliance\n\n")
        report_lines.append(
            "This section reports on adherence to the project's architectural standards.\n\n"
        )

        standards_rules = {
            'ajax_registry': {
                'title': 'AJAX Registration via AIPS_Ajax_Registry',
                'description': (
                    'All AJAX hooks should be registered through AIPS_Ajax_Registry, '
                    'not directly in class constructors.'
                ),
            },
            'config_usage': {
                'title': 'Configuration via AIPS_Config',
                'description': (
                    'Plugin settings should be read through AIPS_Config::get_instance()->get_option() '
                    'instead of raw get_option() calls.'
                ),
            },
            'ajax_response': {
                'title': 'Responses via AIPS_Ajax_Response',
                'description': (
                    'AJAX endpoints should use AIPS_Ajax_Response::success()/error() '
                    'instead of raw wp_send_json*() calls.'
                ),
            },
            'container_di': {
                'title': 'Dependencies via AIPS_Container',
                'description': (
                    'Heavy service dependencies should be resolved from AIPS_Container '
                    'instead of direct instantiation.'
                ),
            },
            'logger_usage': {
                'title': 'Logging via AIPS_Logger',
                'description': (
                    'Use AIPS_Logger for structured, secure logging instead of raw error_log().'
                ),
            },
            'repository_sql': {
                'title': 'SQL in Repository Classes Only',
                'description': (
                    '$wpdb queries should only appear in Repository or DB_Manager classes.'
                ),
            },
        }

        for rule_key, rule_meta in standards_rules.items():
            violators = []
            for cn, viols in self.standards_violations.items():
                for v in viols:
                    if v['rule'] == rule_key:
                        violators.append((cn, v))

            status = "✅ PASS" if not violators else f"⚠️ {len(violators)} finding(s)"
            report_lines.append(f"### {rule_meta['title']}\n\n")
            report_lines.append(f"**Standard**: {rule_meta['description']}\n\n")
            report_lines.append(f"**Status**: {status}\n\n")

            if violators:
                report_lines.append("| Class | Severity | Details |\n")
                report_lines.append("|-------|----------|---------|\n")
                for cn, v in violators:
                    report_lines.append(
                        f"| `{cn}` | {v['severity']} | {v['message']} |\n"
                    )
                report_lines.append("\n")

        # Infrastructure Adoption
        report_lines.append("## Infrastructure Adoption\n\n")
        report_lines.append(
            "Adoption rates for key plugin infrastructure across all scanned classes.\n\n"
        )

        infra_keys = [
            ('uses_container', 'AIPS_Container (DI)'),
            ('uses_config', 'AIPS_Config'),
            ('uses_cache', 'AIPS_Cache'),
            ('uses_ajax_response', 'AIPS_Ajax_Response'),
            ('uses_logger', 'AIPS_Logger'),
            ('uses_telemetry', 'AIPS_Telemetry'),
            ('uses_correlation_id', 'AIPS_Correlation_ID'),
            ('uses_error_handler', 'AIPS_Error_Handler'),
            ('uses_history_service', 'AIPS_History_Service'),
            ('uses_resilience', 'AIPS_Resilience_Service'),
        ]

        report_lines.append("| Infrastructure Component | Classes Using It | Adoption % |\n")
        report_lines.append("|--------------------------|------------------|------------|\n")
        total_classes = len(self.features)
        for key, label in infra_keys:
            count = sum(
                1 for f in self.features.values()
                if f.get('infrastructure_usage', {}).get(key)
            )
            pct = (count / total_classes * 100) if total_classes > 0 else 0
            report_lines.append(f"| {label} | {count} | {pct:.0f}% |\n")
        report_lines.append("\n")

        # Anti-pattern prevalence
        report_lines.append("### Anti-Pattern Prevalence\n\n")
        report_lines.append("| Pattern | Classes With It | Notes |\n")
        report_lines.append("|---------|-----------------|-------|\n")
        anti_patterns = [
            ('raw_get_option', 'Raw get_option()', 'Should use AIPS_Config'),
            ('raw_error_log', 'Raw error_log()', 'Should use AIPS_Logger'),
            ('raw_wp_send_json', 'Raw wp_send_json*()', 'Should use AIPS_Ajax_Response'),
        ]
        for key, label, note in anti_patterns:
            count = sum(
                1 for f in self.features.values()
                if f.get('infrastructure_usage', {}).get(key)
            )
            report_lines.append(f"| {label} | {count} | {note} |\n")
        report_lines.append("\n")

        # Summary Statistics
        report_lines.append("## Summary Statistics\n\n")

        # Classes by category
        report_lines.append("### Classes by Category\n\n")
        report_lines.append("| Category | Count | Classes |\n")
        report_lines.append("|----------|-------|----------|\n")
        for category, classes in categories.items():
            class_list = ", ".join([c.replace('AIPS_', '') for c in classes[:3]])
            if len(classes) > 3:
                class_list += f", ... ({len(classes) - 3} more)"
            report_lines.append(f"| {category} | {len(classes)} | {class_list} |\n")

        report_lines.append("\n")

        # Top classes by lines of code
        report_lines.append("### Largest Classes (by Lines of Code)\n\n")
        report_lines.append("| Class | Lines | File |\n")
        report_lines.append("|-------|-------|------|\n")
        sorted_by_loc = sorted(
            self.features.items(),
            key=lambda x: x[1]['lines_of_code'],
            reverse=True
        )[:10]
        for class_name, feature in sorted_by_loc:
            report_lines.append(
                f"| {feature['name']} | {feature['lines_of_code']} | `{feature['file']}` |\n"
            )

        report_lines.append("\n")

        # Most connected classes
        report_lines.append("### Most Connected Classes (by Dependencies)\n\n")
        report_lines.append("| Class | Dependencies | Depends On |\n")
        report_lines.append("|-------|--------------|------------|\n")
        sorted_by_deps = sorted(
            self.features.items(),
            key=lambda x: len(x[1]['dependencies']),
            reverse=True
        )[:10]
        for class_name, feature in sorted_by_deps:
            dep_count = len(feature['dependencies'])
            if dep_count > 0:
                deps = ", ".join(
                    [d.replace('AIPS_', '') for d in feature['dependencies'][:3]]
                )
                if len(feature['dependencies']) > 3:
                    deps += f", ... ({len(feature['dependencies']) - 3} more)"
                report_lines.append(f"| {feature['name']} | {dep_count} | {deps} |\n")

        report_lines.append("\n")

        # Footer
        report_lines.append("---\n\n")
        report_lines.append(
            "*This report was automatically generated by the Feature Scanner tool.*\n"
        )

        # Join all lines into content
        new_content = ''.join(report_lines)

        # Strip _raw_content from features before writing (not needed in output)
        # (it's only used internally for standards checks)

        # Check if file exists and compare content
        output_path = Path(output_file)
        should_write = True

        if output_path.exists():
            try:
                with open(output_file, 'r', encoding='utf-8') as f:
                    existing_content = f.read()

                if existing_content == new_content:
                    should_write = False
                    print(f"✓ Feature report unchanged: {output_file}")
            except Exception as e:
                print(f"Warning: Could not read existing file: {e}")

        # Write the file only if content changed
        if should_write:
            with open(output_file, 'w', encoding='utf-8') as f:
                f.write(new_content)
            print(f"✓ Feature report updated: {output_file}")
        else:
            print(f"✓ Feature report is up to date: {output_file}")


def main():
    """Main entry point for the feature scanner."""
    # Determine plugin directory
    script_dir = Path(__file__).parent
    repo_root = script_dir.parent
    plugin_dir = repo_root / "ai-post-scheduler"

    if not plugin_dir.exists():
        print(f"Error: Plugin directory not found at {plugin_dir}")
        sys.exit(1)

    print(f"Scanning plugin at: {plugin_dir}")

    # Create scanner and scan files
    scanner = FeatureScanner(str(plugin_dir))
    scanner.scan_all_files()

    print(f"Found {len(scanner.features)} classes and {len(scanner.interfaces)} interfaces")

    violation_count = sum(len(v) for v in scanner.standards_violations.values())
    if violation_count:
        print(f"Detected {violation_count} standards finding(s) across "
              f"{len(scanner.standards_violations)} class(es)")

    # Generate report
    docs_dir = repo_root / "docs"
    docs_dir.mkdir(exist_ok=True)

    output_file = docs_dir / "feature-report.md"
    scanner.generate_report(str(output_file))

    profiles_file = docs_dir / "feature-report-feature-profiles.md"
    scanner.generate_profiles_summary(str(profiles_file))

    print("\nFeature scanning complete!")


if __name__ == "__main__":
    main()
