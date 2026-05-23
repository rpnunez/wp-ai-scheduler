import os
import json
from datetime import datetime, timezone
import sys

# Attempt to import tree-sitter bindings
try:
    import tree_sitter_php as tsphp
    from tree_sitter import Language, Parser
except ImportError:
    print("Error: tree-sitter or tree-sitter-php not installed.")
    print("Please run: pip install tree-sitter==0.22.3 tree-sitter-php==0.22.8")
    sys.exit(1)

# Configuration
PLUGIN_DIR = "ai-post-scheduler"
DOCS_DIR = "docs"
REPORT_FILE = os.path.join(DOCS_DIR, "feature-report.md")
JSON_FILE = "scan_results.json"
GOD_CLASS_THRESHOLD = 400 # lines

RULES = {
    "direct_db_access": "Direct $wpdb access outside of a Repository.",
    "legacy_option_access": "Usage of WP option functions. Use AIPS_Config instead.",
    "naked_wp_query": "Naked WP_Query instantiation. Use a Repository class instead.",
    "wp_die_usage": "Usage of wp_die(). Use structured exception handling or WP_Error.",
    "missing_docblocks": "Method missing docblock.",
    "god_class": f"File exceeds {GOD_CLASS_THRESHOLD} lines.",
    "legacy_date_time": "Usage of raw datetime functions (strtotime, date, time). Use AIPS_DateTime instead.",
    "direct_cron_registration": "Usage of wp_schedule_event/wp_schedule_single_event outside boot_cron().",
    "direct_ajax_registration": "Usage of add_action('wp_ajax_*') outside boot_ajax().",
    "transient_usage": "Usage of set_transient/get_transient. Prefer AIPS_Cache.",
    "raw_sql_execution": "Direct execution of query() or get_results() on $wpdb.",
    "missing_abspath_check": "Missing 'ABSPATH' check at the top of the file.",
    "direct_service_instantiation": "Direct instantiation of a Service/Repository. Resolve via AIPS_Container.",
    "legacy_json_response": "Usage of wp_send_json_*. Use AIPS_Ajax_Response.",
    "business_logic_rendering": "Echo/print in Business Logic class. Keep UI in templates/.",
    "non_compliant_class_name": "Class name does not start with AIPS_."
}

def init_parser():
    # Initialize the Tree-sitter parser strictly for PHP
    php_language = Language(tsphp.language())
    parser = Parser()

    # Support multiple tree-sitter Python APIs.
    if hasattr(parser, 'set_language'):
        parser.set_language(php_language)
    else:
        parser.language = php_language

    return parser

def check_for_abspath(source_lines):
    # Quick check for the ABSPATH constant definition
    for i in range(min(15, len(source_lines))):
        if 'ABSPATH' in source_lines[i] and 'exit' in source_lines[i]:
            return True
    return False

def analyze_ast(node, source_lines, filepath, is_repository, issues):
    node_type = node.type
    filepath_normalized = filepath.replace('\\', '/').lower()
    # Decode text safely to avoid byte parsing errors
    text = node.text.decode('utf-8', errors='ignore') if node.text else ""
    line_num = node.start_point[0] + 1 # tree-sitter is 0-indexed

    # Class Naming Check (AGENTS.md Requirement)
    if node_type == 'class_declaration':
        name_node = node.child_by_field_name('name')
        if name_node:
            class_name = name_node.text.decode('utf-8', errors='ignore')
            if not class_name.startswith('AIPS_'):
                issues.append({
                    "file": filepath,
                    "line": line_num,
                    "type": "non_compliant_class_name",
                    "message": RULES["non_compliant_class_name"]
                })

    # 1. Direct DB Access ($wpdb)
    if node_type == 'variable_name' and text == '$wpdb' and not is_repository:
        issues.append({
            "file": filepath,
            "line": line_num,
            "type": "direct_db_access",
            "message": RULES["direct_db_access"]
        })

    # 2. Function Calls (Legacy Option Access, wp_die, strtotime, transients, cron)
    elif node_type == 'function_call_expression':
        func_node = node.child_by_field_name('function')
        if func_node:
            func_name = func_node.text.decode('utf-8', errors='ignore')
            
            if func_name in ['get_option', 'update_option', 'add_option', 'delete_option'] and 'config' not in filepath_normalized:
                issues.append({
                    "file": filepath,
                    "line": line_num,
                    "type": "legacy_option_access",
                    "message": RULES["legacy_option_access"]
                })
            elif func_name == 'wp_die':
                issues.append({
                    "file": filepath,
                    "line": line_num,
                    "type": "wp_die_usage",
                    "message": RULES["wp_die_usage"]
                })
            elif func_name in ['strtotime', 'date', 'time', 'gmdate', 'current_time'] and 'datetime' not in filepath_normalized:
                issues.append({
                    "file": filepath,
                    "line": line_num,
                    "type": "legacy_date_time",
                    "message": RULES["legacy_date_time"]
                })
            elif func_name in ['set_transient', 'get_transient', 'delete_transient'] and 'cache' not in filepath_normalized:
                issues.append({
                    "file": filepath,
                    "line": line_num,
                    "type": "transient_usage",
                    "message": RULES["transient_usage"]
                })
            elif func_name in ['wp_schedule_event', 'wp_schedule_single_event'] and 'cron' not in filepath_normalized:
                issues.append({
                    "file": filepath,
                    "line": line_num,
                    "type": "direct_cron_registration",
                    "message": RULES["direct_cron_registration"]
                })
            elif func_name == 'add_action':
                args_node = node.child_by_field_name('arguments')
                if args_node:
                    args_text = args_node.text.decode('utf-8', errors='ignore')
                    if 'wp_ajax_' in args_text and not 'controller' in filepath.lower():
                        issues.append({
                            "file": filepath,
                            "line": line_num,
                            "type": "direct_ajax_registration",
                            "message": RULES["direct_ajax_registration"]
                        })
            elif func_name in ['wp_send_json', 'wp_send_json_success', 'wp_send_json_error']:
                issues.append({
                    "file": filepath,
                    "line": line_num,
                    "type": "legacy_json_response",
                    "message": RULES["legacy_json_response"]
                })

    # 3. Method Calls on $wpdb (query, get_results)
    elif node_type == 'member_call_expression':
        obj_node = node.child_by_field_name('object')
        name_node = node.child_by_field_name('name')
        
        if obj_node and name_node:
            obj_name = obj_node.text.decode('utf-8', errors='ignore')
            method_name = name_node.text.decode('utf-8', errors='ignore')
            
            if obj_name == '$wpdb' and method_name in ['query', 'get_results'] and not is_repository:
                issues.append({
                    "file": filepath,
                    "line": line_num,
                    "type": "raw_sql_execution",
                    "message": RULES["raw_sql_execution"]
                })

    # 4. Naked WP_Query (Object Instantiation)
    elif node_type == 'object_creation_expression':
        class_node = node.child_by_field_name('class')
        if class_node:
            class_name = class_node.text.decode('utf-8', errors='ignore')
            if class_name == 'WP_Query' and not is_repository:
                issues.append({
                    "file": filepath,
                    "line": line_num,
                    "type": "naked_wp_query",
                    "message": RULES["naked_wp_query"]
                })
            elif any(suffix in class_name for suffix in ['_Service', '_Repository', '_Controller']) and 'container' not in filepath_normalized and 'factory' not in filepath_normalized:
                issues.append({
                    "file": filepath,
                    "line": line_num,
                    "type": "direct_service_instantiation",
                    "message": RULES["direct_service_instantiation"]
                })

    # 5. Echo/Print in Business Logic (UI Layer Separation)
    elif node_type in ['echo_statement', 'print_intrinsic']:
        # Flag rendering in 'includes/' unless it's explicitly a UI/Admin/View class
        if 'includes/' in filepath_normalized and not any(token in filepath_normalized for token in ['ui', 'admin', 'view', 'template']):
            issues.append({
                "file": filepath,
                "line": line_num,
                "type": "business_logic_rendering",
                "message": RULES["business_logic_rendering"]
            })

    # 6. Missing Docblocks (Method Declarations)
    elif node_type == 'method_declaration':
        start_line = node.start_point[0]
        has_docblock = False
        # Check the 5 lines preceding the method declaration for comments
        for j in range(max(0, start_line - 5), start_line):
            line_str = source_lines[j].strip()
            if '*/' in line_str or '//' in line_str or '#' in line_str:
                has_docblock = True
                break
        if not has_docblock:
            issues.append({
                "file": filepath,
                "line": line_num,
                "type": "missing_docblocks",
                "message": RULES["missing_docblocks"]
            })

    # Traverse children recursively to evaluate nested code
    for child in node.children:
        analyze_ast(child, source_lines, filepath, is_repository, issues)

def scan_files():
    issues = []

    if not os.path.exists(PLUGIN_DIR):
        print(f"Directory {PLUGIN_DIR} not found. Skipping scan.")
        return issues

    parser = init_parser()

    for root, _, files in os.walk(PLUGIN_DIR):
        for file in files:
            if not file.endswith('.php'):
                continue

            filepath = os.path.join(root, file)
            filepath_normalized = filepath.replace('\\', '/').lower()
            # Skip tests directory for strict architectural checks
            if '/tests/' in filepath_normalized:
                continue
                
            filename_lower = file.lower()
            root_lower = root.lower()
            is_repository = 'repository' in filename_lower or 'repositories' in root_lower or '/db' in filepath_normalized

            with open(filepath, 'rb') as f:
                source_bytes = f.read()

            source_lines = source_bytes.decode('utf-8', errors='ignore').split('\n')
            
            # Check ABSPATH
            if not check_for_abspath(source_lines):
                issues.append({
                    "file": filepath,
                    "line": 1,
                    "type": "missing_abspath_check",
                    "message": RULES["missing_abspath_check"]
                })

            # Check God Class
            if len(source_lines) > GOD_CLASS_THRESHOLD:
                issues.append({
                    "file": filepath,
                    "line": 0,
                    "type": "god_class",
                    "message": RULES["god_class"]
                })

            tree = parser.parse(source_bytes)
            analyze_ast(tree.root_node, source_lines, filepath, is_repository, issues)

    return issues

def generate_json(issues):
    with open(JSON_FILE, 'w', encoding='utf-8') as f:
        json.dump({
            "timestamp": datetime.now(timezone.utc).isoformat(),
            "total_issues": len(issues),
            "issues": issues
        }, f, indent=2)
    print(f"Generated {JSON_FILE}")

def generate_markdown(issues):
    os.makedirs(DOCS_DIR, exist_ok=True)
    generated_at = datetime.now(timezone.utc)

    with open(REPORT_FILE, 'w', encoding='utf-8') as f:
        f.write("# 🤖 AI Agent Feature & Debt Report\n\n")
        f.write(f"*Last generated: {generated_at.strftime('%Y-%m-%d %H:%M:%S')} UTC*\n\n")

        if not issues:
            f.write("🎉 **No architectural issues detected.**\n")
            return

        f.write(f"**Total Issues Detected:** {len(issues)}\n\n")
        f.write("| File | Line | Type | Message |\n")
        f.write("|---|---|---|---|\n")

        for issue in issues:
            f.write(f"| `{issue['file']}` | {issue['line']} | `{issue['type']}` | {issue['message']} |\n")

    print(f"Generated {REPORT_FILE}")

if __name__ == "__main__":
    found_issues = scan_files()
    generate_json(found_issues)
    generate_markdown(found_issues)