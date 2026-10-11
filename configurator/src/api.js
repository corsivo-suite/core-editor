import apiFetch from "@wordpress/api-fetch";

const API_BASE = "corsivo/v1/theme-config";

export async function getConfig() {
	return apiFetch({ path: `${API_BASE}/config` });
}

export async function saveConfig(data) {
	return apiFetch({
		path: `${API_BASE}/config`,
		method: "POST",
		data,
	});
}

export async function triggerRebuild() {
	return apiFetch({
		path: `${API_BASE}/rebuild`,
		method: "POST",
	});
}

export async function uploadFontFile(file) {
	const formData = new FormData();
	formData.append("font_file", file);

	return apiFetch({
		path: `${API_BASE}/upload-font`,
		method: "POST",
		body: formData,
		headers: {},
	});
}

export async function getThemeFonts() {
	return apiFetch({ path: `${API_BASE}/theme-fonts` });
}

const createPageOption = (page) => ({
	value: Number(page.id),
	label: page.title,
});

const createPagesPath = (parameters) => {
	const query = new URLSearchParams();
	Object.entries(parameters).forEach(([key, value]) => {
		if (value !== undefined && value !== null && value !== "") {
			query.set(key, String(value));
		}
	});
	return `${API_BASE}/pages?${query.toString()}`;
};

export async function searchPages(search, { language, signal } = {}) {
	const response = await apiFetch({
		path: createPagesPath({ search, language, page: 1, per_page: 20 }),
		signal,
	});

	return Array.isArray(response.items)
		? response.items.map(createPageOption)
		: [];
}

export async function resolvePages(pageIds, { language, signal } = {}) {
	const include = pageIds
		.map(Number)
		.filter(Number.isInteger)
		.filter((pageId) => pageId > 0)
		.join(",");
	if (!include) {
		return [];
	}

	const response = await apiFetch({
		path: createPagesPath({ include, language }),
		signal,
	});

	return Array.isArray(response.items)
		? response.items.map(createPageOption)
		: [];
}
