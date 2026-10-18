<?php
if (!defined('ABSPATH')) exit;

function corsivo_core_save_wordpress_features() {
	if (!current_user_can('manage_options')) wp_die('Unauthorized.', '', ['response' => 403]);
	if (!isset($_POST['_wpnonce']) || !is_string($_POST['_wpnonce'])) wp_die('Invalid nonce.', '', ['response' => 400]);
	check_admin_referer('corsivo_core_save_wordpress_features');
	if (!isset($_POST['corsivo_core_wordpress_features']) || !is_array($_POST['corsivo_core_wordpress_features'])) {
		wp_die('Invalid WordPress settings.', '', ['response' => 400]);
	}
	$settings = corsivo_core_wordpress_features_sanitize(wp_unslash($_POST['corsivo_core_wordpress_features']));
	$previous = corsivo_core_wordpress_features_get();
	if (corsivo_core_revisions_locked()) {
		$settings['revisions_mode'] = $previous['revisions_mode'];
		$settings['revisions_limit'] = $previous['revisions_limit'];
	}
	update_option('corsivo_core_wordpress_features', $settings);
	$saved = get_option('corsivo_core_wordpress_features') === $settings;
	if ($saved && $settings !== $previous && function_exists('corsivo_theme_log_activity')) {
		corsivo_theme_log_activity('wordpress_features_saved', 'success', 'Site WordPress features updated.');
	}
	$url = add_query_arg('wordpress_features_saved', $saved ? '1' : '0', admin_url('options-general.php?page=corsivo-core-editor'));
	wp_safe_redirect($url . '#wordpress-features');
	exit;
}
add_action('admin_post_corsivo_core_save_wordpress_features', 'corsivo_core_save_wordpress_features');

function corsivo_core_cron_events() {
	$scheduled = get_option('cron', []);
	if (!is_array($scheduled)) return [];
	$events = [];
	foreach ($scheduled as $timestamp => $hooks) {
		if (!is_numeric($timestamp) || (int) $timestamp < 1 || !is_array($hooks)) continue;
		foreach ($hooks as $hook => $instances) {
			if (!is_string($hook) || !is_array($instances)) continue;
			foreach ($instances as $event) {
				if (!is_array($event)) continue;
				$schedule = isset($event['schedule']) && is_string($event['schedule']) ? $event['schedule'] : '';
				$key = $hook . "\0" . $schedule;
				if (!isset($events[$key])) {
					$events[$key] = ['hook' => $hook, 'schedule' => $schedule, 'next_run' => (int) $timestamp, 'count' => 0, 'callback' => has_action($hook)];
				}
				$events[$key]['next_run'] = min($events[$key]['next_run'], (int) $timestamp);
				$events[$key]['count']++;
			}
		}
	}
	$events = array_values($events);
	usort($events, static function ($first, $second) {
		return ($first['next_run'] <=> $second['next_run']) ?: strcmp($first['hook'], $second['hook']);
	});
	return $events;
}

function corsivo_core_cron_render() {
	$disabled = defined('DISABLE_WP_CRON') && DISABLE_WP_CRON;
	$alternate = defined('ALTERNATE_WP_CRON') && ALTERNATE_WP_CRON;
	$events = corsivo_core_cron_events();
	$schedules = wp_get_schedules();
	?>
	<h3>WP-Cron</h3>
	<p><code>DISABLE_WP_CRON</code>: <strong><?= $disabled ? 'true' : 'false' ?></strong> · <code>ALTERNATE_WP_CRON</code>: <strong><?= $alternate ? 'true' : 'false' ?></strong></p>
	<p class="description"><?= esc_html($disabled ? 'L’avvio automatico legato alle visite è disattivato. Questa schermata non verifica la presenza di uno scheduler esterno.' : 'WordPress può avviare gli eventi pianificati durante le visite al sito.') ?></p>
	<details>
		<summary><?= esc_html(sprintf('Eventi pianificati: %d gruppi', count($events))) ?></summary>
		<p class="description">Eventi raggruppati per hook e ricorrenza. Le callback indicate sono quelle registrate nella richiesta corrente. La schermata legge la pianificazione senza avviare o modificare gli eventi.</p>
		<table class="widefat striped">
			<thead><tr><th scope="col">Hook</th><th scope="col">Prossima esecuzione</th><th scope="col">Ricorrenza</th><th scope="col">Eventi</th><th scope="col">Callback registrata</th></tr></thead>
			<tbody>
			<?php if (!$events): ?><tr><td colspan="5">Nessun evento pianificato.</td></tr><?php endif; ?>
			<?php foreach ($events as $event):
				$schedule = $event['schedule'] === '' ? 'Singola esecuzione' : $event['schedule'];
				if (isset($schedules[$event['schedule']]['display']) && is_string($schedules[$event['schedule']]['display'])) $schedule = $schedules[$event['schedule']]['display'];
				?>
				<tr><td><code><?= esc_html($event['hook']) ?></code></td><td><?= esc_html(wp_date('Y-m-d H:i:s T', $event['next_run'])) ?></td><td><?= esc_html($schedule) ?></td><td><?= esc_html((string) $event['count']) ?></td><td><?= esc_html($event['callback'] ? 'Sì' : 'Non presente in questa richiesta') ?></td></tr>
			<?php endforeach; ?>
			</tbody>
		</table>
	</details>
	<?php
}

function corsivo_core_wordpress_features_render() {
	if (!current_user_can('manage_options')) return;
	$settings = corsivo_core_wordpress_features_get();
	$locked = corsivo_core_revisions_locked();
	?>
	<div class="wrap corsivo-theme-component">
		<h2>Funzionalità WordPress</h2>
		<p>Impostazioni del sito, indipendenti dal tema attivo. I valori predefiniti mantengono il comportamento di WordPress.</p>
		<?php if (isset($_GET['wordpress_features_saved']) && $_GET['wordpress_features_saved'] === '1'): ?>
			<div class="notice notice-success inline"><p>Funzionalità WordPress salvate.</p></div>
		<?php elseif (isset($_GET['wordpress_features_saved']) && $_GET['wordpress_features_saved'] === '0'): ?>
			<div class="notice notice-error inline"><p>Impossibile salvare le funzionalità WordPress.</p></div>
		<?php endif; ?>
		<form action="<?= esc_url(admin_url('admin-post.php')) ?>" method="post">
			<input type="hidden" name="action" value="corsivo_core_save_wordpress_features">
			<?php wp_nonce_field('corsivo_core_save_wordpress_features'); ?>
			<table class="form-table" role="presentation"><tbody>
				<tr><th scope="row">Pingback e trackback</th><td>
					<label><input type="checkbox" name="corsivo_core_wordpress_features[disable_pingbacks_trackbacks]" value="1" <?php checked($settings['disable_pingbacks_trackbacks']); ?>> Disattiva</label>
					<p class="description">Chiude i ping in entrata e disattiva pingback, trackback e notifiche ai servizi di aggiornamento in uscita. I normali commenti e gli allegati podcast continuano a funzionare; gli eventi di enclosure restano pianificati.</p>
				</td></tr>
				<tr><th scope="row"><label for="corsivo-core-revisions-mode">Revisioni</label></th><td>
					<select id="corsivo-core-revisions-mode" name="corsivo_core_wordpress_features[revisions_mode]" <?php disabled($locked); ?>>
						<?php foreach (['native' => 'Comportamento WordPress', 'unlimited' => 'Illimitate', 'disabled' => 'Disattivate', 'limited' => 'Numero massimo'] as $value => $label): ?>
							<option value="<?= esc_attr($value) ?>" <?php selected($settings['revisions_mode'], $value); ?>><?= esc_html($label) ?></option>
						<?php endforeach; ?>
					</select>
					<label for="corsivo-core-revisions-limit">Limite</label>
					<input type="number" class="small-text" id="corsivo-core-revisions-limit" name="corsivo_core_wordpress_features[revisions_limit]" min="1" max="1000" value="<?= esc_attr($settings['revisions_limit']) ?>" <?php disabled($locked); ?>>
					<?php if ($locked): ?>
						<p class="description">La costante <code>WP_POST_REVISIONS</code> è impostata dall’ambiente e ha precedenza su questi controlli.</p>
					<?php endif; ?>
					<p class="description">Il limite si applica ai successivi salvataggi, solo ai tipi di contenuto che supportano le revisioni. WordPress può eliminare le revisioni oltre il limite. Gli autosalvataggi restano gestiti dal core.</p>
				</td></tr>
				<?php foreach (['admin' => 'Heartbeat amministrazione', 'editor' => 'Heartbeat editor'] as $context => $label): ?>
					<tr><th scope="row"><label for="corsivo-core-heartbeat-<?= esc_attr($context) ?>"><?= esc_html($label) ?></label></th><td>
						<select id="corsivo-core-heartbeat-<?= esc_attr($context) ?>" name="corsivo_core_wordpress_features[heartbeat_<?= esc_attr($context) ?>]">
							<?php foreach (['native' => 'Comportamento WordPress', '60' => 'Minimo 60 secondi', '120' => 'Minimo 120 secondi'] as $value => $interval_label): ?>
								<option value="<?= esc_attr($value) ?>" <?php selected($settings['heartbeat_' . $context], (string) $value); ?>><?= esc_html($interval_label) ?></option>
							<?php endforeach; ?>
						</select>
						<p class="description"><?= esc_html($context === 'editor' ? 'Si applica all’editor contenuti e all’editor del sito. Intervalli più lunghi rallentano rinnovo dei blocchi di modifica, aggiornamenti di sessione e funzioni collegate a Heartbeat.' : 'Si applica alle altre schermate amministrative che caricano Heartbeat. Il frontend mantiene il comportamento corrente.') ?></p>
					</td></tr>
				<?php endforeach; ?>
				<tr><th scope="row">Pattern remoti</th><td>
					<label><input type="checkbox" name="corsivo_core_wordpress_features[disable_remote_patterns]" value="1" <?php checked($settings['disable_remote_patterns']); ?>> Disattiva il caricamento automatico</label>
					<p class="description">Esclude il caricamento automatico dei pattern remoti del core e di quelli richiesti dal tema. I pattern locali e quelli già inseriti nei contenuti restano disponibili.</p>
				</td></tr>
				<tr><th scope="row">Fallback emoji</th><td>
					<label><input type="checkbox" name="corsivo_core_wordpress_features[disable_emoji]" value="1" <?php checked($settings['disable_emoji']); ?>> Disattiva</label>
					<p class="description">Rimuove script e stili del fallback WordPress e la conversione delle emoji in immagini nei feed e nelle email. Le emoji vengono visualizzate dal browser e dal sistema operativo.</p>
				</td></tr>
			</tbody></table>
			<?php submit_button('Salva funzionalità WordPress', 'primary', 'corsivo_core_wordpress_features_submit'); ?>
		</form>
		<?php corsivo_core_cron_render(); ?>
	</div>
	<?php
}
