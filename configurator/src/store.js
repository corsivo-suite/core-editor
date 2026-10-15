import { createContext, useContext, useReducer, useCallback, useEffect } from '@wordpress/element';
import { saveConfig } from './api';
import {
	acknowledgeSavedTemplates,
	createConfigSavePayload,
	synchronizeSettingsRevision,
} from './configAdapter';
import {
	reconcilePageAssignments,
	reconcileTemplateReferences,
	reconcileStylePresetChange,
	retainPageSlugReference,
	retainTemplateSlugReference,
} from './collectionAdapters';
import { updateInstalledFontWeightUrl } from './installedFontAdapter';

const ConfigContext = createContext();

const initialState = {
	fonts: [],
	colors: [],
	breakpoints: {},
	customFonts: [],
	installFonts: [],
	functionalPages: [],
	pageAssignments: {},
	globalStyles: {},
	styleTemplates: [],
	contextStyles: [],
	coreComponents: {},
	spacing: {},
	themeFontFiles: [],
	themeFontFeatures: {},
	themeFontsScanning: false,
	serverRevision: '',
	revision: 0,
	isDirty: false,
	isSaving: false,
	saveStatus: null,
	saveMessage: '',
	saveWarning: false,
	error: null,
};

export function configReducer(state, action) {
	switch (action.type) {
		case 'SET_FONTS':
		case 'SET_COLORS': {
			const collection = action.type === 'SET_FONTS' ? 'fonts' : 'colors';
			const updates = reconcileStylePresetChange( state, collection, action.payload, action.change || { type: 'reconcile' } );
			return { ...state, ...updates, revision: state.revision + 1, isDirty: action.silent ? state.isDirty : true, saveStatus: action.silent ? state.saveStatus : null };
		}
		case 'SET_BREAKPOINTS':
			return { ...state, breakpoints: action.payload, revision: state.revision + 1, isDirty: true, saveStatus: null };
		case 'SET_CUSTOM_FONTS':
			return { ...state, customFonts: action.payload, revision: state.revision + 1, isDirty: true, saveStatus: null };
		case 'SET_INSTALL_FONTS':
			return { ...state, installFonts: action.payload, revision: state.revision + 1, isDirty: true, saveStatus: null };
		case 'SET_INSTALLED_FONT_WEIGHT_URL': {
			const installFonts = updateInstalledFontWeightUrl(
				state.installFonts,
				action.fontIdentity,
				action.weightIdentity,
				action.url
			);
			return installFonts === state.installFonts
				? state
				: { ...state, installFonts, revision: state.revision + 1, isDirty: true, saveStatus: null };
		}
		case 'SET_FUNCTIONAL_PAGES': {
			const change = action.change || { type: 'reconcile' };
			const functionalPages = retainPageSlugReference(action.payload, change, state.pageAssignments);
			return { ...state, functionalPages, pageAssignments: reconcilePageAssignments(state.pageAssignments, change, action.payload), revision: state.revision + 1, isDirty: true, saveStatus: null };
		}
		case 'SET_PAGE_ASSIGNMENTS':
			return { ...state, pageAssignments: action.payload, revision: state.revision + 1, isDirty: true, saveStatus: null };
		case 'SET_GLOBAL_STYLES':
			return { ...state, globalStyles: action.payload, revision: state.revision + 1, isDirty: true, saveStatus: null };
		case 'SET_STYLE_TEMPLATES': {
			const change = action.change || { type: 'reconcile' };
			const styleTemplates = retainTemplateSlugReference(action.payload, change);
			return { ...state, styleTemplates, contextStyles: reconcileTemplateReferences(state.contextStyles, change, action.payload), revision: state.revision + 1, isDirty: true, saveStatus: null };
		}
		case 'SET_CONTEXT_STYLES':
			return { ...state, contextStyles: action.payload, revision: state.revision + 1, isDirty: true, saveStatus: null };
		case 'SET_CORE_COMPONENTS':
			return { ...state, coreComponents: action.payload, revision: state.revision + 1, isDirty: action.silent ? state.isDirty : true, saveStatus: action.silent ? state.saveStatus : null };
		case 'SET_SPACING':
			return { ...state, spacing: action.payload, revision: state.revision + 1, isDirty: true, saveStatus: null };
		case 'SAVING':
			return { ...state, isSaving: true, error: null, saveStatus: null, saveMessage: '', saveWarning: false };
		case 'SAVED':
			return {
				...state,
				isSaving: false,
				isDirty: action.revision === state.revision ? false : state.isDirty,
				saveStatus: 'success',
				saveMessage: action.message || 'Saved.',
				saveWarning: !!action.warning,
				serverRevision: action.serverRevision || state.serverRevision,
				styleTemplates: acknowledgeSavedTemplates(state.styleTemplates, action.styleTemplates || []),
			};
		case 'ERROR':
			return { ...state, isSaving: false, error: action.payload, saveStatus: 'error', saveMessage: '', saveWarning: false };
		case 'CLEAR_STATUS':
			return { ...state, saveStatus: null, error: null };
		case 'SET_THEME_FONT_FILES':
			return { ...state, themeFontFiles: action.payload || [] };
		case 'SET_THEME_FONTS_SCANNING':
			return { ...state, themeFontsScanning: !!action.payload };
		case 'SET_THEME_FONT_FEATURES':
			return {
				...state,
				themeFontFeatures: {
					...state.themeFontFeatures,
					[action.payload.url]: action.payload.features || [],
				},
			};
		case 'INIT_THEME_FONT_FEATURES':
			return { ...state, themeFontFeatures: action.payload || {} };
		default:
			return state;
	}
}

export function ConfigProvider({ children, initialData }) {
	const [state, dispatch] = useReducer(configReducer, {
		...initialState,
		...initialData,
	});

	useEffect(() => {
		const preventUnsavedNavigation = (event) => {
			if (!state.isDirty) return;
			event.preventDefault();
			event.returnValue = '';
		};
		window.addEventListener('beforeunload', preventUnsavedNavigation);
		return () => window.removeEventListener('beforeunload', preventUnsavedNavigation);
	}, [state.isDirty]);

	useEffect(() => {
		synchronizeSettingsRevision(state.serverRevision);
	}, [state.serverRevision]);

	const save = useCallback(async () => {
		if (state.isSaving || !state.isDirty) return;
		const revision = state.revision;
		dispatch({ type: 'SAVING' });
		try {
			const response = await saveConfig(createConfigSavePayload(state));
			dispatch({ type: 'SAVED', revision, serverRevision: response.revision, styleTemplates: state.styleTemplates, message: response.message, warning: response.warning });
		} catch (err) {
			dispatch({ type: 'ERROR', payload: err.message || 'Save failed' });
		}
	}, [
		state.revision,
		state.serverRevision,
		state.isSaving,
		state.isDirty,
		state.fonts,
		state.colors,
		state.breakpoints,
		state.customFonts,
		state.installFonts,
		state.functionalPages,
		state.pageAssignments,
		state.globalStyles,
		state.styleTemplates,
		state.contextStyles,
		state.coreComponents,
		state.spacing,
	]);

	return (
		<ConfigContext.Provider value={{ state, dispatch, save }}>
			{children}
		</ConfigContext.Provider>
	);
}

export function useConfig() {
	const context = useContext(ConfigContext);
	if (!context) {
		throw new Error('useConfig must be used within a ConfigProvider');
	}
	return context;
}
