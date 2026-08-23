/**
 * Corsivo Theme style template — Gutenberg sidebar panel.
 *
 * Adds a "Style Template" dropdown in the page/post editor sidebar
 * so the user can override the context-based template on a per-page basis.
 *
 * Uses WordPress globals only — no build step required.
 */
(function () {
	const registerPlugin       = wp.plugins.registerPlugin;
	const PluginDocumentSettingPanel = ( wp.editor && wp.editor.PluginDocumentSettingPanel )
		|| ( wp.editPost && wp.editPost.PluginDocumentSettingPanel );
	const SelectControl        = wp.components.SelectControl;
	const useSelect            = wp.data.useSelect;
	const useDispatch          = wp.data.useDispatch;
	const createElement        = wp.element.createElement;
	const useEffect            = wp.element.useEffect;
	const useRef               = wp.element.useRef;

	const options = window.corsivoThemeEditorPanel ? window.corsivoThemeEditorPanel.options : [];
	const cssMap  = window.corsivoThemeEditorPanel ? window.corsivoThemeEditorPanel.cssMap  : {};
	const automaticCss = window.corsivoThemeEditorPanel ? window.corsivoThemeEditorPanel.automaticCss : '';
	const previewClass = window.corsivoThemeEditorPanel ? window.corsivoThemeEditorPanel.previewClass : '';

	/**
	 * Build <select> options array: [ { label, value } ]
	 */
	const selectOptions = [{ label: '— Automatic (context) —', value: '' }];
	options.forEach(function (opt) {
		selectOptions.push({ label: opt.name, value: opt.slug });
	});

	function getEditorDocuments() {
		const docs = [document];
		const iframeEl = document.querySelector('iframe[name="editor-canvas"]');
		if (iframeEl && iframeEl.contentDocument && iframeEl.contentDocument !== document) {
			docs.push(iframeEl.contentDocument);
		}
		return docs;
	}

	function ensurePreviewClass(doc) {
		if (!previewClass || !doc) return;

		if (doc.body && !doc.body.classList.contains(previewClass)) {
			doc.body.classList.add(previewClass);
		}

		const wrappers = doc.querySelectorAll('.editor-styles-wrapper');
		wrappers.forEach(function (wrapper) {
			if (!wrapper.classList.contains(previewClass)) {
				wrapper.classList.add(previewClass);
			}
		});
	}

	/**
	 * Inject / remove CSS inside the editor iframe.
	 */
	function applyEditorCSS(slug) {
		const STYLE_ID = 'corsivo-theme-per-page-template';
		const docs = getEditorDocuments();
		docs.forEach(function (doc) {
			ensurePreviewClass(doc);
			const existing = doc.getElementById(STYLE_ID);
			if (existing) existing.remove();
		});

		const css = slug && cssMap[slug] ? cssMap[slug] : automaticCss;
		if (!css) return;

		docs.forEach(function (doc) {
			const style = doc.createElement('style');
			style.id = STYLE_ID;
			style.textContent = css;
			doc.head.appendChild(style);
		});
	}

	/**
	 * The panel component.
	 */
	function TjsTemplatePanel() {
		const meta = useSelect(function (select) {
			return select('core/editor').getEditedPostAttribute('meta') || {};
		}, []);

		const editPost = useDispatch('core/editor').editPost;

		const current = meta._corsivo_theme_style_template || '';
		const prevSlug = useRef(current);

		useEffect(function () {
			if (current !== prevSlug.current) {
				prevSlug.current = current;
				/* small delay so editor iframe is ready */
				setTimeout(function () { applyEditorCSS(current); }, 200);
			}
		}, [current]);

		/* also apply on first mount */
		useEffect(function () {
			getEditorDocuments().forEach(function (doc) {
				ensurePreviewClass(doc);
			});

			const iframe = document.querySelector('iframe[name="editor-canvas"]');
			const onLoad = function () {
				applyEditorCSS(current);
			};

			if (iframe) {
				iframe.addEventListener('load', onLoad);
			}

			setTimeout(function () { applyEditorCSS(current); }, 500);

			return function () {
				if (iframe) {
					iframe.removeEventListener('load', onLoad);
				}
			};
		}, []);

		return createElement(
			PluginDocumentSettingPanel,
			{
				name: 'corsivo-theme-style-template',
				title: 'Style Template',
				icon: 'art',
			},
			createElement(SelectControl, {
				label: 'Override template',
				value: current,
				options: selectOptions,
				onChange: function (val) {
					editPost({ meta: { _corsivo_theme_style_template: val } });
				},
			}),
			current
				? createElement(
						'p',
						{ style: { fontSize: '12px', color: '#757575', marginTop: '4px' } },
						'This overrides the automatic context assignment.'
				  )
				: null
		);
	}

	registerPlugin('corsivo-theme-style-template', {
		render: TjsTemplatePanel,
		icon: 'art',
	});
})();
