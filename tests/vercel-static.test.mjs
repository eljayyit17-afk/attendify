import { test } from 'node:test';
import assert from 'node:assert/strict';
import { readdir, readFile } from 'node:fs/promises';

test('published assets contain the Vite manifest but no PHP source or local state', async () => {
    async function inspect(directory) {
        for (const entry of await readdir(directory, { withFileTypes: true })) {
            assert.ok(!entry.name.startsWith('.'), `Dotfile published: ${entry.name}`);
            assert.ok(!/\.php$/i.test(entry.name), `PHP source published: ${entry.name}`);
            assert.ok(!['hot', 'storage'].includes(entry.name));
            if (entry.isDirectory()) await inspect(`${directory}/${entry.name}`);
        }
    }
    await inspect('vercel-static');
    const manifest = JSON.parse(await readFile('vercel-static/build/manifest.json', 'utf8'));
    for (const entry of Object.values(manifest)) {
        await readFile(`vercel-static/build/${entry.file}`);
    }
});
