<?php
if (!defined('ABSPATH')) { exit; }

/**
 * Write journal: every value the importer changes is stored here (with whether it existed) before the write.
 * Rollback replays the journal backwards. The table is kept when the plugin is deactivated or deleted, so a
 * reinstalled copy can still roll back.
 */
final class AGXR_Journal {
	const DB_VERSION = '1';

	static function table() { global $wpdb; return $wpdb->prefix . 'agxr_journal'; }

	static function install() {
		global $wpdb;
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		$t = self::table();
		$c = $wpdb->get_charset_collate();
		dbDelta("CREATE TABLE $t (
 id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
 run varchar(40) NOT NULL,
 step varchar(40) NOT NULL,
 otype varchar(20) NOT NULL,
 oid bigint(20) NOT NULL DEFAULT 0,
 skey varchar(191) NOT NULL DEFAULT '',
 field varchar(191) NOT NULL,
 existed tinyint(1) NOT NULL DEFAULT 1,
 before_v longtext NULL,
 after_v longtext NULL,
 state varchar(16) NOT NULL DEFAULT 'applied',
 note varchar(255) NOT NULL DEFAULT '',
 created datetime NOT NULL,
 undone datetime NULL,
 PRIMARY KEY  (id),
 KEY run (run),
 KEY obj (otype,oid),
 KEY state (state)
) $c;");
		update_option('agxr_journal_db', self::DB_VERSION, false);
	}

	static function ready() { global $wpdb; $t = self::table(); return $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $t)) === $t; }

	/** Record one value before it is written. Returns the row id. */
	static function record($run, $step, $otype, $oid, $skey, $field, $existed, $before, $after, $note = '') {
		global $wpdb;
		$ok = $wpdb->insert(self::table(), [
			'run' => $run, 'step' => $step, 'otype' => $otype, 'oid' => (int) $oid, 'skey' => (string) $skey, 'field' => $field,
			'existed' => $existed ? 1 : 0, 'before_v' => serialize($before), 'after_v' => serialize($after),
			'state' => 'pending', 'note' => mb_substr((string) $note, 0, 250), 'created' => current_time('mysql'),
		]);
		if (!$ok) { throw new RuntimeException('Could not write the rollback journal; nothing was changed. ' . $wpdb->last_error); }
		return (int) $wpdb->insert_id;
	}

	static function mark($id, $state) { global $wpdb; $wpdb->update(self::table(), ['state' => $state], ['id' => (int) $id]); }

	static function rows($where = '1=1', $args = [], $order = 'ASC', $limit = 0) {
		global $wpdb;
		$sql = 'SELECT * FROM ' . self::table() . ' WHERE ' . $where . ' ORDER BY id ' . ($order === 'DESC' ? 'DESC' : 'ASC') . ($limit ? ' LIMIT ' . (int) $limit : '');
		$rows = $args ? $wpdb->get_results($wpdb->prepare($sql, $args), ARRAY_A) : $wpdb->get_results($sql, ARRAY_A);
		foreach ($rows as &$r) { $r['before'] = unserialize($r['before_v'], ['allowed_classes' => false]); $r['after'] = unserialize($r['after_v'], ['allowed_classes' => false]); }
		return $rows;
	}

	static function summary() {
		global $wpdb;
		if (!self::ready()) { return []; }
		return $wpdb->get_results('SELECT step, state, COUNT(*) n, MIN(created) first, MAX(created) last FROM ' . self::table() . ' GROUP BY step, state ORDER BY MIN(id)', ARRAY_A);
	}

	/** Live object created by a run for a staging id (so re-runs reuse it instead of creating a duplicate). */
	static function created($otype, $skey) {
		global $wpdb;
		if (!self::ready()) { return 0; }
		return (int) $wpdb->get_var($wpdb->prepare('SELECT oid FROM ' . self::table() . " WHERE otype=%s AND skey=%s AND field='__created' AND state='applied' ORDER BY id DESC LIMIT 1", $otype, (string) $skey));
	}
}
