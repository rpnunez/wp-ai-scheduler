#!/usr/bin/env python3
"""
Feature Scanner for AI Post Scheduler WordPress Plugin

This script analyzes the plugin's PHP files to generate comprehensive documentation including:
- Feature profiles with detailed information
- Mermaid flowcharts showing interactions between components
- Missing functionality and improvement recommendations
"""

import re
import sys
from pathlib import Path
from typing import Dict, List
from datetime import datetime
from collections import defaultdict


class FeatureScanner:
    """Scans WordPress plugin files to extract features and relationships."""

    def __init__(self, plugin_dir: str):
        self.plugin_dir = Path(plugin_dir)
        self.includes_dir = self.plugin_dir / "includes"
        self.features = {}
        self.class_dependencies = defaultdict(set)
        self.method_calls = defaultdict(list)

    def scan_all_files(self) -> Dict:
        """Scan all PHP files in the includes directory."""
        if not self.includes_dir.exists():
            print(f"Error: Directory {self.includes_dir} does not exist")
            sys.exit(1)

        php_files = sorted(self.includes_dir.glob("class-aips-*.php"))
        
        for php_file in php_files:
            self.analyze_file(php_file)

        return self.features

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
        
        # Extract docblock summary
        summary = self.extract_class_summary(content)
        
        # Store feature information
        self.features[class_name] = {
            'name': feature_name,
            'file': file_path.name,
            'class': class_name,
            'summary': summary,
            'methods': methods,
            'hooks': hooks,
            'dependencies': dependencies,
            'database_operations': database_operations,
            'ajax_handlers': ajax_handlers,
            'wp_api_usage': wp_api_usage,
            'lines_of_code': len(content.split('\n'))
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

    def categorize_features(self) -> Dict[str, List[str]]:
        """Categorize features into logical groups."""
        categories = {
            'Core Generation': [],
            'Scheduling & Automation': [],
            'Content Management': [],
            'Data Management': [],
            'User Interface': [],
            'AI Integration': [],
            'Database': [],
            'Configuration': [],
            'Utilities': []
        }
        
        for class_name, feature in self.features.items():
            # Normalize class name for matching: lowercase and strip common prefix.
            name_lower = class_name.lower()
            base_name = re.sub(r'^aips_', '', name_lower)
            
            if any(x in base_name for x in ['generator', 'generation']):
                categories['Core Generation'].append(class_name)
            elif any(x in base_name for x in ['scheduler', 'cron', 'schedule']):
                categories['Scheduling & Automation'].append(class_name)
            elif any(x in base_name for x in ['template', 'post', 'article', 'content']):
                categories['Content Management'].append(class_name)
            elif any(x in base_name for x in ['data-management', 'export', 'import']):
                categories['Data Management'].append(class_name)
            elif any(x in base_name for x in ['controller', 'admin', 'settings', 'ui']):
                categories['User Interface'].append(class_name)
            elif 'config' in base_name:
                categories['Configuration'].append(class_name)
            elif (
                any(x in base_name for x in ['ai_service', 'ai-engine', 'openai', 'embeddings'])
                or any(p in base_name for p in ['_ai_', '-ai-', ' ai '])
            ):
                categories['AI Integration'].append(class_name)
            elif any(x in base_name for x in ['repository', 'db', 'database', 'migration']):
                categories['Database'].append(class_name)
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
            if not any('save' in m.lower() or 'update' in m.lower() for m in feature['methods']):
                missing.append("Missing save/update methods for data persistence")
        
        if 'Controller' in class_name:
            if not feature['ajax_handlers']:
                missing.append("No AJAX handlers defined for user interactions")
            if not feature['hooks']['actions']:
                missing.append("No WordPress action hooks registered")
        
        if 'Generator' in class_name:
            if not feature['hooks']['filters']:
                missing.append("No filter hooks for customizing generation output")
            if 'error' not in str(feature['methods']).lower():
                missing.append("No dedicated error handling methods visible")
        
        if 'Service' in class_name:
            if not feature['dependencies']:
                missing.append("No visible dependencies - may be tightly coupled")
        
        # Check for logging
        name_lower = class_name.lower()
        if any(x in name_lower for x in ['generator', 'service', 'scheduler']):
            if not any('log' in m.lower() for m in feature['methods']):
                missing.append("No logging methods for debugging and monitoring")
        
        # Check for validation
        if 'Controller' in class_name or 'Service' in class_name:
            if not any('validat' in m.lower() for m in feature['methods']):
                missing.append("No input validation methods visible")
        
        return missing

    def suggest_improvements(self, class_name: str, feature: Dict) -> List[str]:
        """Suggest potential improvements for the feature."""
        improvements = []
        
        # Check code size
        if feature['lines_of_code'] > 500:
            improvements.append(f"Consider refactoring - class has {feature['lines_of_code']} lines (may violate SRP)")
        
        # Check method count
        if len(feature['methods']) > 20:
            improvements.append(f"High method count ({len(feature['methods'])}+ methods) - consider splitting responsibilities")
        
        # Check dependencies
        if len(feature['dependencies']) > 5:
            improvements.append(f"High coupling - depends on {len(feature['dependencies'])} classes")
        
        # Repository pattern
        if feature['database_operations']['uses_wpdb'] and not feature['database_operations']['has_repository']:
            improvements.append("Consider using Repository pattern for database access instead of direct $wpdb")
        
        # Hook documentation
        if feature['hooks']['actions'] or feature['hooks']['filters']:
            improvements.append("Document all custom hooks in HOOKS.md for third-party developers")
        
        # Error handling
        if 'Service' in class_name or 'Generator' in class_name:
            improvements.append("Add comprehensive error handling with specific exception types")
        
        # Testing
        improvements.append("Ensure unit tests cover all public methods and edge cases")
        
        # Documentation
        if not feature['summary'] or feature['summary'] == "No description available":
            improvements.append("Add comprehensive class-level PHPDoc documentation")
        
        # Caching
        if 'Service' in class_name and not feature['wp_api_usage']['uses_transients']:
            improvements.append("Consider using WordPress transients API for caching expensive operations")
        
        return improvements[:5]  # Return top 5 suggestions

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
        
        # Create edges based on dependencies
        for class_name in classes:
            source_id = class_name.replace('AIPS_', '')
            dependencies = self.class_dependencies.get(class_name, set())
            
            for dep in dependencies:
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
        
        # Define connections
        chart += "    UI --> CTRL\n"
        chart += "    CTRL --> SVC\n"
        chart += "    SVC --> REPO\n"
        chart += "    SVC --> AI\n"
        chart += "    REPO --> DB\n"
        chart += "    CTRL --> WP\n"
        chart += "    SVC --> WP\n\n"
        
        # Add subgraphs for each layer
        categories = self.categorize_features()
        
        if 'User Interface' in categories:
            chart += "    subgraph Controllers\n"
            for cls in categories['User Interface'][:3]:
                node_id = cls.replace('AIPS_', '').replace('_', '')
                chart += f"        {node_id}\n"
            chart += "    end\n\n"
        
        if 'AI Integration' in categories or 'Core Generation' in categories:
            chart += "    subgraph Services\n"
            for cls in (categories.get('AI Integration', []) + categories.get('Core Generation', []))[:3]:
                node_id = cls.replace('AIPS_', '').replace('_', '')
                chart += f"        {node_id}\n"
            chart += "    end\n\n"
        
        if 'Database' in categories:
            chart += "    subgraph Repositories\n"
            for cls in categories['Database'][:3]:
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
        
        chart += "```\n"
        
        return chart

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
        report_lines.append("4. [Feature Profiles](#feature-profiles)\n")
        report_lines.append("5. [Summary Statistics](#summary-statistics)\n\n")
        
        # Overview
        report_lines.append("## Overview\n\n")
        report_lines.append(f"This document provides comprehensive documentation for the AI Post Scheduler WordPress plugin. ")
        report_lines.append(f"The plugin consists of **{len(self.features)} core classes** organized into ")
        report_lines.append(f"**{len(categories)} functional categories**.\n\n")
        
        total_loc = sum(f['lines_of_code'] for f in self.features.values())
        report_lines.append(f"- **Total Lines of Code**: {total_loc:,}\n")
        report_lines.append(f"- **Total Features**: {len(self.features)}\n")
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
                report_lines.append(f"- **{feature['name']}** (`{class_name}`): {feature['summary']}\n")
            
            report_lines.append(f"\n#### {category} Architecture\n\n")
            report_lines.append(self.generate_mermaid_flowchart(category, classes))
            report_lines.append("\n")
        
        # Feature Profiles
        report_lines.append("## Feature Profiles\n\n")
        report_lines.append("Detailed analysis of each feature including files, functionality, and recommendations.\n\n")
        
        for class_name in sorted(self.features.keys()):
            feature = self.features[class_name]
            report_lines.append(f"### {feature['name']}\n\n")
            
            # 1. Feature Name (already in heading)
            
            # 2. High-level Summary
            report_lines.append(f"**Summary**: {feature['summary']}\n\n")
            
            # 3. Files Involved
            report_lines.append(f"**File**: `ai-post-scheduler/includes/{feature['file']}`\n\n")
            report_lines.append(f"**Class**: `{class_name}`\n\n")
            report_lines.append(f"**Lines of Code**: {feature['lines_of_code']}\n\n")
            
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
                report_lines.append(f"- **Action Hooks** ({len(feature['hooks']['actions'])}): ")
                # Sort to ensure consistent ordering
                unique_actions = sorted(set(feature['hooks']['actions'][:5]))
                report_lines.append(", ".join(f"`{h}`" for h in unique_actions))
                if len(feature['hooks']['actions']) > 5:
                    report_lines.append(f", ... and {len(feature['hooks']['actions']) - 5} more")
                report_lines.append("\n")
            
            if feature['hooks']['filters']:
                report_lines.append(f"- **Filter Hooks** ({len(feature['hooks']['filters'])}): ")
                # Sort to ensure consistent ordering
                unique_filters = sorted(set(feature['hooks']['filters'][:5]))
                report_lines.append(", ".join(f"`{h}`" for h in unique_filters))
                if len(feature['hooks']['filters']) > 5:
                    report_lines.append(f", ... and {len(feature['hooks']['filters']) - 5} more")
                report_lines.append("\n")
            
            if feature['ajax_handlers']:
                report_lines.append(f"- **AJAX Handlers**: ")
                report_lines.append(", ".join(f"`wp_ajax_{h}`" for h in feature['ajax_handlers']))
                report_lines.append("\n")
            
            # Database operations
            db_ops = feature['database_operations']
            db_features = [k.replace('_', ' ').title() for k, v in db_ops.items() if v]
            if db_features:
                report_lines.append(f"- **Database Operations**: {', '.join(db_features)}\n")
            
            # WordPress API usage
            wp_api = [k.replace('uses_', '').replace('_', ' ').title() 
                     for k, v in feature['wp_api_usage'].items() if v]
            if wp_api:
                report_lines.append(f"- **WordPress APIs Used**: {', '.join(wp_api)}\n")
            
            report_lines.append("\n")
            
            # 4. Functionality that is Missing
            missing = self.identify_missing_functionality(class_name, feature)
            if missing:
                report_lines.append("**Missing Functionality**:\n\n")
                for item in missing:
                    report_lines.append(f"- {item}\n")
                report_lines.append("\n")
            else:
                report_lines.append("**Missing Functionality**: None identified\n\n")
            
            # 5. Recommended Improvements
            improvements = self.suggest_improvements(class_name, feature)
            if improvements:
                report_lines.append("**Recommended Improvements**:\n\n")
                for i, item in enumerate(improvements, 1):
                    report_lines.append(f"{i}. {item}\n")
                report_lines.append("\n")
            
            report_lines.append("---\n\n")
        
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
        sorted_by_loc = sorted(self.features.items(), 
                              key=lambda x: x[1]['lines_of_code'], 
                              reverse=True)[:10]
        for class_name, feature in sorted_by_loc:
            report_lines.append(f"| {feature['name']} | {feature['lines_of_code']} | `{feature['file']}` |\n")
        
        report_lines.append("\n")
        
        # Most connected classes
        report_lines.append("### Most Connected Classes (by Dependencies)\n\n")
        report_lines.append("| Class | Dependencies | Depends On |\n")
        report_lines.append("|-------|--------------|------------|\n")
        sorted_by_deps = sorted(self.features.items(), 
                               key=lambda x: len(x[1]['dependencies']), 
                               reverse=True)[:10]
        for class_name, feature in sorted_by_deps:
            dep_count = len(feature['dependencies'])
            if dep_count > 0:
                deps = ", ".join([d.replace('AIPS_', '') for d in feature['dependencies'][:3]])
                if len(feature['dependencies']) > 3:
                    deps += f", ... ({len(feature['dependencies']) - 3} more)"
                report_lines.append(f"| {feature['name']} | {dep_count} | {deps} |\n")
        
        report_lines.append("\n")
        
        # Footer
        report_lines.append("---\n\n")
        report_lines.append("*This report was automatically generated by the Feature Scanner tool.*\n")
        
        # Join all lines into content
        new_content = ''.join(report_lines)
        
        # Check if file exists and compare content
        output_path = Path(output_file)
        should_write = True
        
        if output_path.exists():
            try:
                with open(output_file, 'r', encoding='utf-8') as f:
                    existing_content = f.read()
                
                # Compare content (existing content already has no timestamp)
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
    
    print(f"Found {len(scanner.features)} classes")
    
    # Generate report
    docs_dir = repo_root / "docs"
    docs_dir.mkdir(exist_ok=True)
    
    output_file = docs_dir / "feature-report.md"
    scanner.generate_report(str(output_file))
    
    print("\nFeature scanning complete!")


if __name__ == "__main__":
    main()
