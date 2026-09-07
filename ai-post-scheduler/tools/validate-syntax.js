const fs = require('fs');
const path = require('path');
const { execSync } = require('child_process');

const jsDir = path.resolve(__dirname, '..', 'assets', 'js');
const files = fs.readdirSync(jsDir).filter(f => f.endsWith('.js'));

let errors = 0;
for (const file of files) {
    const fullPath = path.join(jsDir, file);
    try {
        execSync(`node -c "${fullPath}"`);
        console.log(`✓ ${file}`);
    } catch (e) {
        console.error(`✗ Syntax error in ${file}:`, e.message);
        errors++;
    }
}
if (errors > 0) {
    process.exit(1);
}
console.log(`\nAll ${files.length} JS files passed syntax validation!`);
