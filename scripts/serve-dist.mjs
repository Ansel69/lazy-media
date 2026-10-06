/*
 * Preview server for the static export.
 *
 * Serves dist/ the way Vercel will, so the build can be checked in a browser
 * before it is deployed:
 *
 *   - filesystem first, then rewrites
 *   - cleanUrls: /about and /about.html both resolve to the same file
 *   - trailingSlash: a directory URL without its slash redirects
 *   - rewrites from vercel.json, so /search/<term>/ reaches the search page
 *   - 404.html with a 404 status for anything else
 *
 * Usage:
 *   node scripts/serve-dist.mjs [port]
 *
 * This is a development tool. Vercel is the deployment target.
 */

import { createServer } from 'node:http';
import { createReadStream } from 'node:fs';
import { readFile, stat } from 'node:fs/promises';
import { extname, join, normalize, resolve, sep } from 'node:path';
import { fileURLToPath } from 'node:url';

const root = resolve(fileURLToPath(new URL('../dist', import.meta.url)));
const port = Number(process.argv[2]) || 8099;

const MIME = {
	'.html': 'text/html; charset=utf-8',
	'.css': 'text/css; charset=utf-8',
	'.js': 'text/javascript; charset=utf-8',
	'.json': 'application/json; charset=utf-8',
	'.xml': 'application/xml; charset=utf-8',
	'.txt': 'text/plain; charset=utf-8',
	'.svg': 'image/svg+xml',
	'.jpg': 'image/jpeg',
	'.jpeg': 'image/jpeg',
	'.png': 'image/png',
	'.webp': 'image/webp',
	'.woff2': 'font/woff2',
	'.ico': 'image/x-icon',
};

/*
 * Kept in step with vercel.json. Vercel applies rewrites only after the
 * filesystem has been checked, which is the order used below.
 */
const REWRITES = [[/^\/search\/[^/]+\/?$/, '/search/index.html']];

async function isFile(path) {
	try {
		return (await stat(path)).isFile();
	} catch {
		return false;
	}
}

async function isDirectory(path) {
	try {
		return (await stat(path)).isDirectory();
	} catch {
		return false;
	}
}

/**
 * Every existing file a request could resolve to, in priority order.
 *
 * Each candidate is confirmed to exist. A speculative path that does not exist
 * must never reach the response: piping it raises an unhandled stream error and
 * takes the server down.
 */
async function candidates(pathname) {
	const target = join(root, normalize(decodeURIComponent(pathname)));
	const found = [];

	if (await isFile(target)) {
		found.push(target);
	}

	if (await isDirectory(target)) {
		found.push(join(target, 'index.html'));
	}

	// cleanUrls: a slashless URL may name the file directly or its directory.
	if (!pathname.endsWith('/')) {
		found.push(target + '.html');
		found.push(join(target, 'index.html'));
	}

	const existing = [];

	for (const file of found) {
		if (await isFile(file)) {
			existing.push(file);
		}
	}

	return existing;
}

function send(res, status, file) {
	res.writeHead(status, {
		'content-type': MIME[extname(file).toLowerCase()] || 'application/octet-stream',
		'cache-control': 'no-cache',
	});

	const stream = createReadStream(file);

	// A client that hangs up, or a file that disappears mid-read, is not a
	// reason to exit — it is a reason to drop that one response.
	stream.on('error', () => res.destroy());
	res.on('error', () => {});
	stream.pipe(res);
}

createServer(async (req, res) => {
	const pathname = (req.url || '/').split('?')[0];

	// Refuse anything that escapes dist/.
	if (pathname.includes('..')) {
		res.writeHead(400).end('bad request');
		return;
	}

	for (const file of await candidates(pathname)) {
		// trailingSlash: keep directory URLs canonical.
		if (!pathname.endsWith('/') && !extname(file) && !pathname.endsWith('.html')) {
			res.writeHead(308, { location: pathname + '/' }).end();
			return;
		}

		send(res, 200, file);
		return;
	}

	for (const [pattern, destination] of REWRITES) {
		if (pattern.test(pathname)) {
			const file = join(root, destination);

			if (await isFile(file)) {
				send(res, 200, file);
				return;
			}
		}
	}

	const notFound = join(root, '404.html');

	if (await isFile(notFound)) {
		send(res, 404, notFound);
		return;
	}

	res.writeHead(404).end('not found');
}).listen(port, () => {
	console.log(`serving ${root} at http://localhost:${port}`);
});

export { root, port };