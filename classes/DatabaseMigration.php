<?php
namespace Vpay\VerifiedPay;

class DatabaseMigration {
	/** @var string Latest DB version. Only updated with plugin version if there are migrations. */
	protected $lastVersion;
	/** @var string */
	protected $currentVersion;
	/** @var array */
	protected $lastError = array();
	
	public function __construct(string $lastVersion, string $currentVersion) {
		if (!$lastVersion)
			$lastVersion = '1.0.0'; // when we added migration. shouldn't be needed
		$this->lastVersion = $lastVersion;
		$this->currentVersion = $currentVersion;
	}
	
	public static function checkAndMigrate() {
		$lastVersion = get_option('verifiedpay_version');
		if ($lastVersion === VPAY_VERSION)
			return;
		add_action('plugins_loaded', function() use ($lastVersion) {
			$migrate = new DatabaseMigration($lastVersion, VPAY_VERSION);
			try {
				if ($migrate->migrate() === false) {
					VerifiedPay::notifyErrorExt("Error ensuring latest DB version on migration", $migrate->getLastError());
					return;
				}
				update_option( 'verifiedpay_version', VPAY_VERSION ); // done in main class only after re-activation
			}
			catch (\Exception $e) {
				VerifiedPay::notifyErrorExt("Exception during DB migration: " . get_class(), $e->getMessage());
			}
		}, 200); // load after other plugins
	}
	
	public function migrate(): bool {
		$queries = array();

		switch ($this->lastVersion) {
			// add migration queries in order from oldest version to newest
			case '1.0.29':
			case '1.1.0':
			case '1.1.1':
			$cron_event = 'verifiedpay_update_config';
			$timestamp = wp_next_scheduled ( $cron_event );
			if (!$timestamp)
				wp_schedule_event(time(), 'daily', $cron_event);
				
		}
		if (empty($queries))
			return true; // say successful
		return $this->runQueries($queries);
	}
	
	public function getLastError(): array {
		return $this->lastError;
	}
	
	protected function runQueries(array $queries): bool {
		global $wpdb;
		foreach ($queries as $query) {
			$result = $wpdb->query($query);
			if ($result === false) {
				$this->lastError = array(
						'query' => $query,
						'error' => $wpdb->last_error
				);
				return false; // abort
			}
		}
		return true;
	}
	
	protected function columnExists(string $table, string $column) {
		global $wpdb;
		$rows = $wpdb->query("SHOW COLUMNS FROM `$table` LIKE '$column'");
		return empty($rows) ? false : true;
	}
}
?>