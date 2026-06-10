import fs from 'fs';
import path from 'path';
import { baseCompile } from '@intlify/message-compiler';

function walk(dir, files = []) {
    for (const ent of fs.readdirSync(dir, { withFileTypes: true })) {
        const p = path.join(dir, ent.name);
        if (ent.isDirectory()) walk(p, files);
        else if (ent.name.endsWith('.json')) files.push(p);
    }
    return files;
}

function flatten(obj, prefix = '') {
    const out = [];
    for (const [k, v] of Object.entries(obj)) {
        const p = prefix ? `${prefix}.${k}` : k;
        if (typeof v === 'string') out.push([p, v]);
        else if (v && typeof v === 'object') out.push(...flatten(v, p));
    }
    return out;
}

const root = path.resolve('resources/js/locales');
let errors = 0;

for (const file of walk(root)) {
    const raw = fs.readFileSync(file, 'utf8');
    const relative = file.replace(root + path.sep, '');

    let json;
    try {
        json = JSON.parse(raw);
    } catch (e) {
        console.error(`JSON ${relative}: ${e.message}`);
        errors++;
        continue;
    }

    for (const [key, value] of flatten(json)) {
        try {
            baseCompile(value, { onError: (e) => { throw e; } });
        } catch (e) {
            console.error(`MSG ${relative} ${key}: ${e.message}`);
            errors++;
        }
    }
}

if (errors > 0) {
    process.exit(1);
}

console.log('All locale messages are valid.');
