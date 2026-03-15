import re

with open('ai-post-scheduler/assets/css/admin.css', 'r') as f:
    content = f.read()

new_css = """.aips-wizard-step {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 8px;
    position: relative;
    z-index: 1;
    flex: 1;
    cursor: pointer;
    transition: opacity 0.2s ease, transform 0.2s ease;
}

.aips-wizard-step:hover {
    opacity: 0.8;
}

.aips-wizard-step:active {
    transform: scale(0.98);
}"""

content = content.replace(""".aips-wizard-step {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 8px;
    position: relative;
    z-index: 1;
    flex: 1;
}""", new_css)

with open('ai-post-scheduler/assets/css/admin.css', 'w') as f:
    f.write(content)
