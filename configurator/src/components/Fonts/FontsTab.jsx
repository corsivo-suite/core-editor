import GoogleFontsImport from '../GoogleFonts/GoogleFontsImport';
import InstalledFontsTab from '../InstalledFonts/InstalledFontsTab';

export default function FontsTab() {
	return (
		<div className="ts-fonts-tab">
			<h2>Fonts</h2>

			<GoogleFontsImport />

			<hr />

			<InstalledFontsTab />
		</div>
	);
}
