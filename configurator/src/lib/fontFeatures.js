import opentype from 'opentype.js';

const CACHE_KEY = 'corsivo_theme_font_features_v1';
const CACHE_VERSION = 4;

let wawoffPromise = null;
function loadWawoff() {
	if (!wawoffPromise) {
		wawoffPromise = new Promise((resolve, reject) => {
			const decoderUrl =
				window.corsivoThemeEditor &&
				window.corsivoThemeEditor.woff2DecoderUrl;
			if (!decoderUrl) {
				reject(new Error('wawoff2 decoder URL missing'));
				return;
			}

			const wawoff = window.corsivoThemeWawoff2Module || {};
			window.corsivoThemeWawoff2Module = wawoff;

			if (typeof wawoff.decompress === 'function') {
				resolve(wawoff);
				return;
			}

			wawoff.onRuntimeInitialized = () => {
				if (typeof wawoff.decompress !== 'function') {
					reject(new Error('wawoff2 decompress missing'));
					return;
				}
				resolve(wawoff);
			};
			wawoff.onAbort = (reason) => {
				reject(new Error('wawoff2 aborted: ' + String(reason)));
			};

			const script = document.createElement('script');
			script.src = decoderUrl;
			script.async = true;
			script.onerror = () => {
				reject(new Error('wawoff2 decoder failed to load'));
			};
			script.onload = () => {
				if (typeof wawoff.decompress === 'function') {
					resolve(wawoff);
				}
			};
			document.head.appendChild(script);
		});
	}
	return wawoffPromise;
}

function withTimeout(promise, ms, label) {
	return Promise.race([
		promise,
		new Promise((_, reject) =>
			setTimeout(
				() => reject(new Error('timeout: ' + label + ' after ' + ms + 'ms')),
				ms
			)
		),
	]);
}

function readSignature(buffer) {
	const view = new DataView(buffer, 0, 4);
	return (
		String.fromCharCode(view.getUint8(0)) +
		String.fromCharCode(view.getUint8(1)) +
		String.fromCharCode(view.getUint8(2)) +
		String.fromCharCode(view.getUint8(3))
	);
}

async function decompressIfWoff2(arrayBuffer) {
	if (arrayBuffer.byteLength < 4) return arrayBuffer;
	const sig = readSignature(arrayBuffer);
	if (sig !== 'wOF2') return arrayBuffer;

	const wawoff = await loadWawoff();
	const decompress = wawoff && wawoff.decompress;
	if (typeof decompress !== 'function') {
		throw new Error('wawoff2 decompress missing');
	}

	const decompressed = await decompress(new Uint8Array(arrayBuffer));
	if (decompressed === false) {
		throw new Error('ConvertWOFF2ToTTF failed');
	}
	return decompressed.buffer.slice(
		decompressed.byteOffset,
		decompressed.byteOffset + decompressed.byteLength
	);
}

function loadCache() {
	try {
		const raw = localStorage.getItem(CACHE_KEY);
		if (!raw) return {};
		const parsed = JSON.parse(raw);
		if (parsed.version !== CACHE_VERSION) return {};
		return parsed.entries || {};
	} catch (e) {
		return {};
	}
}

function saveCache(entries) {
	try {
		localStorage.setItem(
			CACHE_KEY,
			JSON.stringify({ version: CACHE_VERSION, entries })
		);
	} catch (e) {
		// quota exceeded or unavailable, ignore
	}
}

function cacheKey(url, mtime) {
	return url + '#' + (mtime || 0);
}

const USER_SELECTABLE_NAMED = new Set([
	'salt', 'swsh', 'hist', 'smcp', 'c2sc', 'titl',
	'dlig', 'hlig', 'zero', 'unic', 'case', 'cpsp',
	'frac', 'ordn', 'sups', 'subs', 'numr', 'dnom',
	'tnum', 'pnum', 'onum', 'lnum',
]);

function isUserSelectableFeature(tag) {
	if (/^ss\d{2}$/.test(tag)) return true;
	if (/^cv\d{2}$/.test(tag)) return true;
	return USER_SELECTABLE_NAMED.has(tag);
}

function lookupName(font, nameID) {
	if (!font.names || nameID == null) return null;
	const buckets = [
		font.names[nameID],
		font.names.unicode && font.names.unicode[nameID],
		font.names.macintosh && font.names.macintosh[nameID],
		font.names.windows && font.names.windows[nameID],
	];
	for (const entry of buckets) {
		if (!entry) continue;
		if (typeof entry === 'string') return entry;
		if (entry.en) return entry.en;
		const firstLang = Object.keys(entry)[0];
		if (firstLang) return entry[firstLang];
	}
	return null;
}

function extractFeaturesFromGSUB(font) {
	const gsub = font.tables && font.tables.gsub;
	if (!gsub || !gsub.features) return [];

	const seen = new Map();
	for (const f of gsub.features) {
		const tag = f && f.tag;
		if (!tag || !isUserSelectableFeature(tag)) continue;
		if (seen.has(tag)) continue;

		const params = f.feature && f.feature.params;
		const nameID = params && (params.uiNameID || params.nameID);
		const name = nameID ? lookupName(font, nameID) : null;
		seen.set(tag, { tag, name: name || null });
	}

	return Array.from(seen.values()).sort((a, b) => a.tag.localeCompare(b.tag));
}

export async function getFeaturesForFile(file) {
	if (!file || !file.url) return [];

	const cache = loadCache();
	const key = cacheKey(file.url, file.mtime);
	if (cache[key]) return cache[key];

	let buffer;
	try {
		const res = await withTimeout(fetch(file.url), 15000, 'fetch ' + file.url);
		if (!res.ok) throw new Error('HTTP ' + res.status);
		buffer = await withTimeout(res.arrayBuffer(), 15000, 'arrayBuffer ' + file.url);
	} catch (err) {
		console.warn('[corsivo-theme-font-features] fetch failed for', file.url, err);
		return [];
	}

	let decompressed;
	try {
		decompressed = await withTimeout(
			decompressIfWoff2(buffer),
			30000,
			'decompress ' + file.url
		);
	} catch (err) {
		console.warn('[corsivo-theme-font-features] WOFF2 decompression failed for', file.url, err);
		cache[key] = [];
		saveCache(cache);
		return [];
	}

	let font;
	try {
		font = opentype.parse(decompressed);
	} catch (err) {
		console.warn('[corsivo-theme-font-features] opentype.parse failed for', file.url, err);
		cache[key] = [];
		saveCache(cache);
		return [];
	}

	const features = extractFeaturesFromGSUB(font);
	cache[key] = features;
	saveCache(cache);
	return features;
}

export async function scanAllFontFiles(files, onPartial) {
	const result = {};
	for (const file of files || []) {
		const features = await getFeaturesForFile(file);
		result[file.url] = features;
		if (typeof onPartial === 'function') {
			onPartial(file.url, features);
		}
	}
	return result;
}

export function getFeaturesForFamily(family, installFonts, fileFeatures) {
	if (!family || !fileFeatures) return [];
	const normalized = String(family).toLowerCase().trim();
	const entry = (installFonts || []).find(
		(f) => String(f['font-family'] || '').toLowerCase().trim() === normalized
	);
	if (!entry) return [];

	const urls = collectFontUrls(entry);
	const merged = new Map();
	for (const url of urls) {
		const list = fileFeatures[url];
		if (!list) continue;
		for (const f of list) {
			if (!merged.has(f.tag)) merged.set(f.tag, f);
		}
	}
	return Array.from(merged.values()).sort((a, b) => a.tag.localeCompare(b.tag));
}

function collectFontUrls(entry) {
	const urls = new Set();
	const weights = entry['font-weight'] || {};
	for (const url of Object.values(weights)) {
		if (typeof url === 'string' && url) urls.add(url);
	}
	const styles = entry['font-style'] || {};
	for (const url of Object.values(styles)) {
		if (typeof url === 'string' && url) urls.add(url);
	}
	return Array.from(urls);
}

export function parseFontFamilyFromCss(css) {
	if (!css) return '';
	const match = String(css).match(/font-family\s*:\s*([^;]+)/);
	if (!match) return '';
	const first = match[1].split(',')[0].trim();
	return first.replace(/^['"]|['"]$/g, '');
}

export function parseFontFeatureSettingsFromCss(css) {
	if (!css) return [];
	const match = String(css).match(/font-feature-settings\s*:\s*([^;]+)/);
	if (!match) return [];
	const raw = match[1].trim();
	if (raw === 'normal' || raw === 'none' || raw === '') return [];
	return raw
		.split(',')
		.map((part) => part.trim().replace(/^['"]|['"]$/g, '').replace(/\s+\d+$/, '').trim())
		.filter(Boolean);
}

export function writeFontFeatureSettingsToCss(css, tags) {
	const declaration = tags && tags.length
		? 'font-feature-settings: ' + tags.map((t) => '"' + t + '"').join(', ') + ';'
		: '';
	const current = String(css || '');
	const hasRule = /font-feature-settings\s*:[^;]*;?/.test(current);

	if (!declaration) {
		if (!hasRule) return current;
		return current.replace(/\n?\s*font-feature-settings\s*:[^;]*;?/g, '').trim();
	}

	if (hasRule) {
		return current.replace(/font-feature-settings\s*:[^;]*;?/, declaration);
	}
	return current ? current.replace(/\s*$/, '') + '\n' + declaration : declaration;
}
