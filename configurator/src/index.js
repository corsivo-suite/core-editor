import { createRoot } from '@wordpress/element';
import App from './components/App';
import '@corsivo-suite/wp-admin-ui/styles.css';
import './index.scss';
import './admin-ui.scss';

function mountConfigurator() {
	// Single React root — all tabs share one ConfigProvider (shared state).
	// Each tab renders via createPortal into its existing PHP container.
	const rootEl = document.getElementById('corsivo-theme-configurator-portal-root') || document.createElement('div');
	rootEl.id = 'corsivo-theme-configurator-portal-root';
	rootEl.style.display = 'none'; // hidden host — portals render elsewhere
	if (!rootEl.parentNode) {
		document.body.appendChild(rootEl);
	}
	createRoot(rootEl).render(<App />);
}

if (document.readyState === 'loading') {
	document.addEventListener('DOMContentLoaded', mountConfigurator);
} else {
	mountConfigurator();
}
