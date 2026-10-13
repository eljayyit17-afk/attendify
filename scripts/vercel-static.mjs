import { cp, mkdir } from 'node:fs/promises';
import { basename } from 'node:path';

// Publish only public assets, never PHP source, dotfiles or local uploads.
await mkdir('vercel-static', { recursive: true });
await cp('public', 'vercel-static', {
    recursive: true,
    filter: (path) => !basename(path).startsWith('.')
        && !/\.php$/i.test(path)
        && !['hot', 'storage'].includes(basename(path)),
});
