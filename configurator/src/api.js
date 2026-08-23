import apiFetch from '@wordpress/api-fetch';

const API_BASE = 'corsivo/v1/theme-config';

export async function getConfig() {
	return apiFetch({ path: `${API_BASE}/config` });
}

export async function saveConfig(data) {
	return apiFetch({
		path: `${API_BASE}/config`,
		method: 'POST',
		data,
	});
}

export async function triggerRebuild() {
	return apiFetch({
		path: `${API_BASE}/rebuild`,
		method: 'POST',
	});
}

export async function uploadFontFile(file) {
	const formData = new FormData();
	formData.append('font_file', file);

	return apiFetch({
		path: `${API_BASE}/upload-font`,
		method: 'POST',
		body: formData,
		headers: {},
	});
}

export async function getThemeFonts() {
	return apiFetch({ path: `${API_BASE}/theme-fonts` });
}
