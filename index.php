<?php
header('Cache-Control: no-cache, no-store, must-revalidate');
header('Content-Type: text/html; charset=UTF-8');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>AI Post Scheduler - WordPress Plugin</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, sans-serif;
            line-height: 1.6;
            color: #1d2327;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            padding: 40px 20px;
        }
        .container {
            max-width: 900px;
            margin: 0 auto;
        }
        .card {
            background: white;
            border-radius: 12px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.2);
            overflow: hidden;
        }
        .header {
            background: linear-gradient(135deg, #2271b1 0%, #135e96 100%);
            color: white;
            padding: 40px;
            text-align: center;
        }
        .header h1 {
            font-size: 2.5em;
            margin-bottom: 10px;
        }
        .header p {
            opacity: 0.9;
            font-size: 1.2em;
        }
        .badge {
            display: inline-block;
            background: rgba(255,255,255,0.2);
            padding: 5px 15px;
            border-radius: 20px;
            font-size: 0.9em;
            margin-top: 15px;
        }
        .content {
            padding: 40px;
        }
        h2 {
            color: #2271b1;
            margin-bottom: 15px;
            padding-bottom: 10px;
            border-bottom: 2px solid #f0f0f1;
        }
        h3 {
            color: #1d2327;
            margin: 20px 0 10px;
        }
        .section {
            margin-bottom: 30px;
        }
        ul {
            padding-left: 25px;
        }
        li {
            margin-bottom: 8px;
        }
        .feature-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin: 20px 0;
        }
        .feature {
            background: #f6f7f7;
            padding: 20px;
            border-radius: 8px;
            border-left: 4px solid #2271b1;
        }
        .feature h4 {
            color: #2271b1;
            margin-bottom: 8px;
        }
        .code {
            background: #1d2327;
            color: #50c878;
            padding: 3px 8px;
            border-radius: 4px;
            font-family: monospace;
            font-size: 0.9em;
        }
        .code-block {
            background: #1d2327;
            color: #f0f0f1;
            padding: 20px;
            border-radius: 8px;
            overflow-x: auto;
            font-family: monospace;
            margin: 15px 0;
        }
        .code-block .comment {
            color: #6a737d;
        }
        .code-block .string {
            color: #50c878;
        }
        .install-steps {
            counter-reset: step;
        }
        .install-steps li {
            counter-increment: step;
            list-style: none;
            position: relative;
            padding-left: 40px;
            margin-bottom: 15px;
        }
        .install-steps li::before {
            content: counter(step);
            position: absolute;
            left: 0;
            width: 28px;
            height: 28px;
            background: #2271b1;
            color: white;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
            font-size: 0.9em;
        }
        .download-btn {
            display: inline-block;
            background: #2271b1;
            color: white;
            padding: 15px 30px;
            border-radius: 8px;
            text-decoration: none;
            font-weight: 600;
            font-size: 1.1em;
            transition: background 0.2s;
        }
        .download-btn:hover {
            background: #135e96;
        }
        .file-tree {
            background: #f6f7f7;
            padding: 20px;
            border-radius: 8px;
            font-family: monospace;
            font-size: 0.9em;
        }
        .file-tree .folder {
            color: #2271b1;
            font-weight: bold;
        }
        .file-tree .file {
            color: #1d2327;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin: 15px 0;
        }
        th, td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid #dcdcde;
        }
        th {
            background: #f6f7f7;
            font-weight: 600;
        }
        .notice {
            background: #fff3cd;
            border-left: 4px solid #dba617;
            padding: 15px 20px;
            border-radius: 4px;
            margin: 20px 0;
        }
        .notice-success {
            background: #d4edda;
            border-color: #00a32a;
        }
        footer {
            text-align: center;
            padding: 30px;
            color: #646970;
            background: #f6f7f7;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="card">
            <div class="header">
                <h1>AI Post Scheduler</h1>
                <p>WordPress Plugin for Automated AI Content Generation</p>
                <span class="badge">Version 1.0.0</span>
            </div>
            
            <div class="content">
                <div class="section">
                    <h2>Overview</h2>
                    <p>AI Post Scheduler integrates with <strong>Meow Apps AI Engine</strong> to provide a powerful admin interface for scheduling AI-generated blog posts. Create templates, set schedules, and automatically generate quality blog content on autopilot.</p>
                </div>
                
                <div class="section">
                    <h2>Features</h2>
                    <div class="feature-grid">
                        <div class="feature">
                            <h4>Template System</h4>
                            <p>Create reusable prompt templates with dynamic variables for diverse content generation.</p>
                        </div>
                        <div class="feature">
                            <h4>Flexible Scheduling</h4>
                            <p>Schedule posts hourly, every 6 hours, 12 hours, daily, or weekly.</p>
                        </div>
                        <div class="feature">
                            <h4>Post Configuration</h4>
                            <p>Set post status, category, tags, and author for each template.</p>
                        </div>
                        <div class="feature">
                            <h4>Generation History</h4>
                            <p>Track all generated posts with success/failure status and retry capabilities.</p>
                        </div>
                        <div class="feature">
                            <h4>Test Generation</h4>
                            <p>Preview AI output before scheduling to ensure quality.</p>
                        </div>
                        <div class="feature">
                            <h4>Error Handling</h4>
                            <p>Automatic logging and retry capabilities for failed generations.</p>
                        </div>
                    </div>
                </div>
                
                <div class="section">
                    <h2>Requirements</h2>
                    <ul>
                        <li>WordPress 5.8 or higher</li>
                        <li>PHP 8.2 or higher</li>
                        <li><strong>Meow Apps AI Engine plugin</strong> (required)</li>
                    </ul>
                    <div class="notice">
                        <strong>Important:</strong> You must have the Meow Apps AI Engine plugin installed and configured with an AI provider (OpenAI, Anthropic, etc.) for this plugin to generate content.
                    </div>
                </div>
                
                <div class="section">
                    <h2>Installation</h2>
                    <ol class="install-steps">
                        <li>Download the <code class="code">wp-ai-scheduler</code> folder</li>
                        <li>Upload it to your WordPress <code class="code">/wp-content/plugins/</code> directory</li>
                        <li>Activate the plugin through the 'Plugins' menu in WordPress</li>
                        <li>Ensure Meow Apps AI Engine is installed and configured</li>
                        <li>Navigate to <strong>AI Post Scheduler</strong> in your admin menu</li>
                    </ol>
                </div>
                
                <div class="section">
                    <h2>Template Variables</h2>
                    <p>Use these dynamic variables in your prompt templates:</p>
                    <table>
                        <thead>
                            <tr>
                                <th>Variable</th>
                                <th>Description</th>
                                <th>Example Output</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td><code class="code">{{date}}</code></td>
                                <td>Current date</td>
                                <td><?php echo date('F j, Y'); ?></td>
                            </tr>
                            <tr>
                                <td><code class="code">{{year}}</code></td>
                                <td>Current year</td>
                                <td><?php echo date('Y'); ?></td>
                            </tr>
                            <tr>
                                <td><code class="code">{{month}}</code></td>
                                <td>Current month</td>
                                <td><?php echo date('F'); ?></td>
                            </tr>
                            <tr>
                                <td><code class="code">{{day}}</code></td>
                                <td>Day of week</td>
                                <td><?php echo date('l'); ?></td>
                            </tr>
                            <tr>
                                <td><code class="code">{{time}}</code></td>
                                <td>Current time</td>
                                <td><?php echo date('H:i'); ?></td>
                            </tr>
                            <tr>
                                <td><code class="code">{{site_name}}</code></td>
                                <td>Your site's name</td>
                                <td>My WordPress Blog</td>
                            </tr>
                            <tr>
                                <td><code class="code">{{site_description}}</code></td>
                                <td>Site tagline</td>
                                <td>Just another WordPress site</td>
                            </tr>
                            <tr>
                                <td><code class="code">{{random_number}}</code></td>
                                <td>Random 1-1000</td>
                                <td><?php echo rand(1, 1000); ?></td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                
                <div class="section">
                    <h2>Example Prompt Template</h2>
                    <div class="code-block">
<span class="comment">// Content Prompt Example:</span>
Write a comprehensive blog post about the latest trends in technology for {{month}} {{year}}.

The post should:
- Be informative and engaging
- Include practical tips readers can apply
- Be optimized for SEO
- Be approximately 800-1000 words
- Include a compelling introduction and conclusion

Write in a professional but approachable tone suitable for {{site_name}}.
                    </div>
                </div>
                
                <div class="section">
                    <h2>Plugin Structure</h2>
                    <div class="file-tree">
                        <span class="folder">wp-ai-scheduler/</span><br>
                        ├── <span class="file">ai-post-scheduler.php</span> <span style="color:#646970">← Main plugin file</span><br>
                        ├── <span class="file">readme.txt</span><br>
                        ├── <span class="folder">assets/</span><br>
                        │   ├── <span class="folder">css/</span> <span class="file">admin.css</span><br>
                        │   └── <span class="folder">js/</span> <span class="file">admin.js</span><br>
                        ├── <span class="folder">includes/</span><br>
                        │   ├── <span class="file">class-aips-settings.php</span><br>
                        │   ├── <span class="file">class-aips-templates.php</span><br>
                        │   ├── <span class="file">class-aips-generator.php</span><br>
                        │   ├── <span class="file">class-aips-scheduler.php</span><br>
                        │   ├── <span class="file">class-aips-history.php</span><br>
                        │   └── <span class="file">class-aips-logger.php</span><br>
                        └── <span class="folder">templates/admin/</span><br>
                            ├── <span class="file">dashboard.php</span><br>
                            ├── <span class="file">templates.php</span><br>
                            ├── <span class="file">schedule.php</span><br>
                            ├── <span class="file">history.php</span><br>
                            └── <span class="file">settings.php</span>
                    </div>
                </div>
                
                <div class="section notice-success notice">
                    <strong>Ready to Use!</strong> Download the <code class="code">wp-ai-scheduler</code> folder and upload it to your WordPress plugins directory to get started.
                </div>
            </div>
            
            <footer>
                <p>AI Post Scheduler v1.0.0 | Requires Meow Apps AI Engine</p>
            </footer>
        </div>
    </div>
</body>
</html>
