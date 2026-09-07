/**
 * Internationalization (i18n) Build and Translation Tool (Node.js)
 *
 * Extracts strings from PHP and JS files in ai-post-scheduler, generates POT/PO/MO
 * catalogs, and compiles Jed 1.x JSON translation files for WordPress native wp.i18n.
 *
 * Usage: node tools/generate-i18n.js
 *
 * @package AI_Post_Scheduler
 */

const fs = require('fs');
const path = require('path');
const crypto = require('crypto');

const pluginDir = path.resolve(__dirname, '..');
const languagesDir = path.join(pluginDir, 'languages');

if (!fs.existsSync(languagesDir)) {
    fs.mkdirSync(languagesDir, { recursive: true });
}

console.log('=== AI Post Scheduler i18n Builder ===');

function scanFiles(dir, extensions = ['.php', '.js']) {
    let results = [];
    const list = fs.readdirSync(dir);
    for (const file of list) {
        if (file === '.' || file === '..' || file === 'vendor' || file === 'node_modules' || file === '.git') {
            continue;
        }
        const fullPath = path.join(dir, file);
        const stat = fs.statSync(fullPath);
        if (stat.isDirectory()) {
            results = results.concat(scanFiles(fullPath, extensions));
        } else {
            const ext = path.extname(fullPath);
            if (extensions.includes(ext)) {
                results.push(fullPath);
            }
        }
    }
    return results;
}

const scriptHandleMap = {
    'aips-datetime-script': 'assets/js/datetime.js',
    'aips-utilities-script': 'assets/js/utilities.js',
    'aips-templates-script': 'assets/js/templates.js',
    'aips-admin-script': 'assets/js/admin.js',
    'aips-admin-history': 'assets/js/admin-history.js',
    'aips-authors-script': 'assets/js/authors.js',
    'aips-admin-embeddings': 'assets/js/admin-embeddings.js',
    'aips-ai-assistance-script': 'assets/js/ai-assistance.js',
    'aips-admin-post-slices': 'assets/js/admin-post-slices.js',
    'aips-admin-integrations': 'assets/js/admin-integrations.js',
    'aips-admin-content-auditor': 'assets/js/admin-content-auditor.js',
    'aips-admin-planner': 'assets/js/admin-planner.js',
    'aips-admin-research': 'assets/js/admin-research.js',
    'aips-admin-view-session': 'assets/js/admin-view-session.js',
    'aips-admin-post-review': 'assets/js/admin-post-review.js',
    'aips-admin-generated-posts': 'assets/js/admin-generated-posts.js',
    'aips-admin-ai-edit': 'assets/js/admin-ai-edit.js',
    'aips-calendar-script': 'assets/js/calendar.js',
    'aips-admin-onboarding': 'assets/js/onboarding.js',
    'aips-admin-campaign-wizard': 'assets/js/campaign-wizard.js',
    'aips-admin-campaigns': 'assets/js/campaigns.js',
    'aips-admin-dev-tools': 'assets/js/admin-dev-tools.js',
    'aips-admin-db': 'assets/js/admin-db.js',
    'aips-admin-taxonomy': 'assets/js/taxonomy.js',
    'aips-admin-sources': 'assets/js/admin-sources.js',
    'aips-admin-settings': 'assets/js/admin-settings.js',
    'aips-admin-system-status': 'assets/js/admin-system-status.js',
    'aips-dashboard-script': 'assets/js/admin-dashboard.js',
    'aips-telemetry-script': 'assets/js/telemetry.js',
    'aips-admin-internal-links': 'assets/js/admin-internal-links.js',
    'aips-content-indexer-script': 'assets/js/admin-content-indexer.js',
    'aips-cache-monitor': 'assets/js/cache-monitor.js',
    'aips-admin-stress-test': 'assets/js/admin-stress-test.js',
    'aips-admin-bar': 'assets/js/admin-bar.js',
    'aips-admin-seeder': 'assets/js/admin-seeder.js',
};

function unescapeString(str) {
    return str
        .replace(/\\'/g, "'")
        .replace(/\\"/g, '"')
        .replace(/\\n/g, "\n")
        .replace(/\\r/g, "\r")
        .replace(/\\t/g, "\t")
        .replace(/\\\\/g, "\\");
}

function escapePoString(str) {
    return str
        .replace(/\\/g, '\\\\')
        .replace(/"/g, '\\"')
        .replace(/\n/g, '\\n')
        .replace(/\r/g, '\\r')
        .replace(/\t/g, '\\t');
}

function extractStrings(files) {
    const entries = {};

    const simpleRegex = /(?:__|esc_html__|esc_attr__|_e|esc_html_e|esc_attr_e|\bwp\.i18n\.__)\s*\(\s*(['"])((?:\\.|(?!\1).)*)\1\s*,\s*['"]ai-post-scheduler['"]\s*\)/g;
    const contextRegex = /(?:_x|esc_html_x|esc_attr_x|\bwp\.i18n\._x)\s*\(\s*(['"])((?:\\.|(?!\1).)*)\1\s*,\s*(['"])((?:\\.|(?!\3).)*)\3\s*,\s*['"]ai-post-scheduler['"]\s*\)/g;
    const pluralRegex = /(?:_n|\bwp\.i18n\._n)\s*\(\s*(['"])((?:\\.|(?!\1).)*)\1\s*,\s*(['"])((?:\\.|(?!\3).)*)\3\s*,\s*[^,]+,\s*['"]ai-post-scheduler['"]\s*\)/g;

    for (const filepath of files) {
        const content = fs.readFileSync(filepath, 'utf8');
        const relPath = path.relative(pluginDir, filepath).replace(/\\/g, '/');

        // Simple strings
        let m;
        while ((m = simpleRegex.exec(content)) !== null) {
            const msgid = unescapeString(m[2]);
            const key = msgid;
            if (!entries[key]) {
                entries[key] = { msgid, msgid_plural: '', msgctxt: '', references: [] };
            }
            entries[key].references.push(relPath);
        }

        // Context strings
        while ((m = contextRegex.exec(content)) !== null) {
            const msgid = unescapeString(m[2]);
            const msgctxt = unescapeString(m[4]);
            const key = msgctxt + '\x04' + msgid;
            if (!entries[key]) {
                entries[key] = { msgid, msgid_plural: '', msgctxt, references: [] };
            }
            entries[key].references.push(relPath);
        }

        // Plural strings
        while ((m = pluralRegex.exec(content)) !== null) {
            const msgid = unescapeString(m[2]);
            const msgid_plural = unescapeString(m[4]);
            const key = msgid;
            if (!entries[key]) {
                entries[key] = { msgid, msgid_plural, msgctxt: '', references: [] };
            } else {
                entries[key].msgid_plural = msgid_plural;
            }
            entries[key].references.push(relPath);
        }
    }

    return entries;
}

const allFiles = scanFiles(pluginDir);
const extracted = extractStrings(allFiles);
console.log(`Extracted ${Object.keys(extracted).length} unique i18n string entries.`);

// Build POT
function buildPot(entries) {
    const now = new Date().toISOString().replace('T', ' ').substring(0, 16) + '+0000';
    let pot = `# Copyright (C) 2026 Raymond Nunez
# This file is distributed under the GPL v2 or later.
msgid ""
msgstr ""
"Project-Id-Version: AI Post Scheduler 3.6.5\\n"
"Report-Msgid-Bugs-To: https://nunezserver.com\\n"
"POT-Creation-Date: ${now}\\n"
"MIME-Version: 1.0\\n"
"Content-Type: text/plain; charset=UTF-8\\n"
"Content-Transfer-Encoding: 8bit\\n"
"PO-Revision-Date: 2026-YEAR-MO-DA HO:MI+ZONE\\n"
"Last-Translator: FULL NAME <EMAIL@ADDRESS>\\n"
"Language-Team: LANGUAGE <LL@li.org>\\n"
"Language: \\n"
"Plural-Forms: nplurals=2; plural=(n != 1);\\n"
"X-Domain: ai-post-scheduler\\n"

`;

    for (const key of Object.keys(entries)) {
        const entry = entries[key];
        const refs = [...new Set(entry.references)];
        for (const ref of refs) {
            pot += `#: ${ref}\n`;
        }
        if (entry.msgctxt) {
            pot += `msgctxt "${escapePoString(entry.msgctxt)}"\n`;
        }
        pot += `msgid "${escapePoString(entry.msgid)}"\n`;
        if (entry.msgid_plural) {
            pot += `msgid_plural "${escapePoString(entry.msgid_plural)}"\n`;
            pot += 'msgstr[0] ""\n';
            pot += 'msgstr[1] ""\n';
        } else {
            pot += 'msgstr ""\n';
        }
        pot += '\n';
    }
    return pot;
}

const potContent = buildPot(extracted);
const potFile = path.join(languagesDir, 'ai-post-scheduler.pot');
fs.writeFileSync(potFile, potContent, 'utf8');
console.log(`Written POT file to: ${potFile}`);

// Load Spanish Translation Dictionary
const esDictFile = path.join(__dirname, 'translations-es.json');
let esTranslations = {};
if (fs.existsSync(esDictFile)) {
    esTranslations = JSON.parse(fs.readFileSync(esDictFile, 'utf8'));
} else if (fs.existsSync(path.join(__dirname, 'translations-es.php'))) {
    // Convert translations-es.php to json if needed
    const phpDictContent = fs.readFileSync(path.join(__dirname, 'translations-es.php'), 'utf8');
    const phpRegex = /'((?:\\.|(?!').)*)'\s*=>\s*'((?:\\.|(?!').)*)'/g;
    let pm;
    while ((pm = phpRegex.exec(phpDictContent)) !== null) {
        esTranslations[unescapeString(pm[1])] = unescapeString(pm[2]);
    }
    fs.writeFileSync(esDictFile, JSON.stringify(esTranslations, null, 2), 'utf8');
}

// Build PO for Spanish
function buildEsPo(entries, translations) {
    const now = new Date().toISOString().replace('T', ' ').substring(0, 16) + '+0000';
    let po = `# Spanish translation for AI Post Scheduler.
# Copyright (C) 2026 Raymond Nunez
# This file is distributed under the GPL v2 or later.
msgid ""
msgstr ""
"Project-Id-Version: AI Post Scheduler 3.6.5\\n"
"Report-Msgid-Bugs-To: https://nunezserver.com\\n"
"POT-Creation-Date: ${now}\\n"
"PO-Revision-Date: ${now}\\n"
"Last-Translator: AI Post Scheduler Translator\\n"
"Language-Team: Spanish (Spain) <es_ES@li.org>\\n"
"Language: es_ES\\n"
"MIME-Version: 1.0\\n"
"Content-Type: text/plain; charset=UTF-8\\n"
"Content-Transfer-Encoding: 8bit\\n"
"Plural-Forms: nplurals=2; plural=(n != 1);\\n"
"X-Domain: ai-post-scheduler\\n"

`;

    for (const key of Object.keys(entries)) {
        const entry = entries[key];
        const refs = [...new Set(entry.references)];
        for (const ref of refs) {
            po += `#: ${ref}\n`;
        }
        if (entry.msgctxt) {
            po += `msgctxt "${escapePoString(entry.msgctxt)}"\n`;
        }
        po += `msgid "${escapePoString(entry.msgid)}"\n`;

        const transKey = entry.msgctxt ? entry.msgctxt + '\x04' + entry.msgid : entry.msgid;
        const trans = translations[transKey] || translations[entry.msgid] || entry.msgid;

        if (entry.msgid_plural) {
            po += `msgid_plural "${escapePoString(entry.msgid_plural)}"\n`;
            const singleTrans = Array.isArray(trans) ? trans[0] : trans;
            const pluralTrans = Array.isArray(trans) && trans[1] ? trans[1] : (entry.msgid_plural || trans);
            po += `msgstr[0] "${escapePoString(singleTrans)}"\n`;
            po += `msgstr[1] "${escapePoString(pluralTrans)}"\n`;
        } else {
            const singleTrans = Array.isArray(trans) ? trans[0] : trans;
            po += `msgstr "${escapePoString(singleTrans)}"\n`;
        }
        po += '\n';
    }
    return po;
}

const esPoContent = buildEsPo(extracted, esTranslations);
const esPoFile = path.join(languagesDir, 'ai-post-scheduler-es_ES.po');
fs.writeFileSync(esPoFile, esPoContent, 'utf8');
console.log(`Written Spanish PO file to: ${esPoFile}`);

// Compile MO
function compileMo(entries, translations, outputFile) {
    const originals = [];
    const translationsData = [];

    // Header entry
    originals.push('');
    translationsData.push(
        "Project-Id-Version: AI Post Scheduler 3.6.5\n" +
        "Report-Msgid-Bugs-To: https://nunezserver.com\n" +
        "Language: es_ES\n" +
        "MIME-Version: 1.0\n" +
        "Content-Type: text/plain; charset=UTF-8\n" +
        "Content-Transfer-Encoding: 8bit\n" +
        "Plural-Forms: nplurals=2; plural=(n != 1);\n" +
        "X-Domain: ai-post-scheduler\n"
    );

    for (const key of Object.keys(entries)) {
        const entry = entries[key];
        let orig = '';
        if (entry.msgctxt) {
            orig += entry.msgctxt + '\x04';
        }
        orig += entry.msgid;
        if (entry.msgid_plural) {
            orig += '\x00' + entry.msgid_plural;
        }

        const transKey = entry.msgctxt ? entry.msgctxt + '\x04' + entry.msgid : entry.msgid;
        const trans = translations[transKey] || translations[entry.msgid] || entry.msgid;

        let transVal;
        if (entry.msgid_plural) {
            const singleTrans = Array.isArray(trans) ? trans[0] : trans;
            const pluralTrans = Array.isArray(trans) && trans[1] ? trans[1] : (entry.msgid_plural || trans);
            transVal = singleTrans + '\x00' + pluralTrans;
        } else {
            transVal = Array.isArray(trans) ? trans[0] : trans;
        }

        originals.push(orig);
        translationsData.push(transVal);
    }

    // Sort items by original
    const pairs = originals.map((orig, i) => ({ orig, trans: translationsData[i] }));
    pairs.sort((a, b) => a.orig.localeCompare(b.orig));

    const count = pairs.length;
    const headerSize = 28;
    const origTableOffset = headerSize;
    const transTableOffset = origTableOffset + (count * 8);
    let dataOffset = transTableOffset + (count * 8);

    const origTable = Buffer.alloc(count * 8);
    const transTable = Buffer.alloc(count * 8);
    const dataBuffers = [];

    let currentOffset = dataOffset;
    for (let i = 0; i < count; i++) {
        const origBuf = Buffer.from(pairs[i].orig + '\x00', 'utf8');
        const origLen = Buffer.byteLength(pairs[i].orig, 'utf8');
        origTable.writeUInt32LE(origLen, i * 8);
        origTable.writeUInt32LE(currentOffset, i * 8 + 4);
        dataBuffers.push(origBuf);
        currentOffset += origBuf.length;
    }

    for (let i = 0; i < count; i++) {
        const transBuf = Buffer.from(pairs[i].trans + '\x00', 'utf8');
        const transLen = Buffer.byteLength(pairs[i].trans, 'utf8');
        transTable.writeUInt32LE(transLen, i * 8);
        transTable.writeUInt32LE(currentOffset, i * 8 + 4);
        dataBuffers.push(transBuf);
        currentOffset += transBuf.length;
    }

    const header = Buffer.alloc(28);
    header.writeUInt32LE(0x950412de, 0); // Magic number
    header.writeUInt32LE(0, 4);          // Format revision
    header.writeUInt32LE(count, 8);      // Number of strings
    header.writeUInt32LE(origTableOffset, 12);
    header.writeUInt32LE(transTableOffset, 16);
    header.writeUInt32LE(0, 20);         // Hash table size
    header.writeUInt32LE(0, 24);         // Hash table offset

    const finalBuffer = Buffer.concat([header, origTable, transTable, ...dataBuffers]);
    fs.writeFileSync(outputFile, finalBuffer);
}

const esMoFile = path.join(languagesDir, 'ai-post-scheduler-es_ES.mo');
compileMo(extracted, esTranslations, esMoFile);
console.log(`Compiled Spanish MO binary to: ${esMoFile}`);

// Generate Jed 1.x JSON script translations
function generateJedJsonFiles(map, translations) {
    let count = 0;
    for (const handle of Object.keys(map)) {
        const relFile = map[handle];
        const filepath = path.join(pluginDir, relFile);
        if (!fs.existsSync(filepath)) {
            continue;
        }

        const fileEntries = extractStrings([filepath]);
        const localeData = {
            "": {
                "domain": "ai-post-scheduler",
                "lang": "es_ES",
                "plural-forms": "nplurals=2; plural=(n != 1);"
            }
        };

        for (const key of Object.keys(fileEntries)) {
            const entry = fileEntries[key];
            const transKey = entry.msgctxt ? entry.msgctxt + '\x04' + entry.msgid : entry.msgid;
            const trans = translations[transKey] || translations[entry.msgid] || entry.msgid;

            const mapKey = entry.msgctxt ? entry.msgctxt + '\x04' + entry.msgid : entry.msgid;

            if (entry.msgid_plural) {
                const singleTrans = Array.isArray(trans) ? trans[0] : trans;
                const pluralTrans = Array.isArray(trans) && trans[1] ? trans[1] : (entry.msgid_plural || trans);
                localeData[mapKey] = [singleTrans, pluralTrans];
            } else {
                const singleTrans = Array.isArray(trans) ? trans[0] : trans;
                localeData[mapKey] = [singleTrans];
            }
        }

        const jedStructure = {
            "translation-revision-date": new Date().toISOString().replace('T', ' ').substring(0, 19) + '+0000',
            "generator": "AI Post Scheduler i18n Tool",
            "source": relFile,
            "domain": "ai-post-scheduler",
            "locale_data": {
                "ai-post-scheduler": localeData
            }
        };

        const jsonData = JSON.stringify(jedStructure, null, 2);

        // Standard 1: By script handle
        const handleFile = path.join(languagesDir, `ai-post-scheduler-es_ES-${handle}.json`);
        fs.writeFileSync(handleFile, jsonData, 'utf8');

        // Standard 2: By MD5 of relative file path
        const md5 = crypto.createHash('md5').update(relFile).digest('hex');
        const md5File = path.join(languagesDir, `ai-post-scheduler-es_ES-${md5}.json`);
        fs.writeFileSync(md5File, jsonData, 'utf8');

        count++;
    }
    console.log(`Generated ${count} Jed 1.x JSON script translation sets (both by-handle and by-md5).`);
}

generateJedJsonFiles(scriptHandleMap, esTranslations);
console.log('=== i18n Build Complete ===');
