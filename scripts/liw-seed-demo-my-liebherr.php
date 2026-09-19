<?php
/**
 * Liebherr World – Demo-Befüllung My Liebherr + Pocket (nur Entwicklung/Vorschau, ADR-LIW-MYL-001).
 *
 * Schaltet in der DEV-Umgebung die Flags scharf (liw_myl_enabled, liw_pocket_enabled, liw_ptime_enabled) und legt
 * für den ersten Administrator DEMO-Inhalte an (Dreams, Machines, Gallery, Pocket-Items). Rein zur Vorschau –
 * Testdaten (DEMO-Kennzeichnung), idempotent (Marker-Option verhindert Doppelanlage). NICHT für Produktion.
 *
 *   docker exec araliya_wordpress php \
 *     /var/www/html/wp-content/plugins/liebherr-interface-world/scripts/liw-seed-demo-my-liebherr.php --confirm
 *   … --reset   (entfernt die DEMO-Inhalte des Admins wieder und schaltet die Flags aus)
 *
 * @package Liebherr\InterfaceWorld
 * @since   0.1.0-alpha.127
 */

declare( strict_types = 1 );

if ( 'cli' !== PHP_SAPI ) {
	fwrite( STDERR, "Nur für die Kommandozeile.\n" );
	exit( 2 );
}
$confirm = in_array( '--confirm', $argv, true );
$reset   = in_array( '--reset', $argv, true );
if ( ! $confirm && ! $reset ) {
	fwrite( STDOUT, "Trockenlauf-Schutz: mit --confirm ausführen (oder --reset zum Entfernen).\n" );
	exit( 0 );
}

require_once dirname( __DIR__, 4 ) . '/wp-load.php';

if ( 'development' !== wp_get_environment_type() ) {
	fwrite( STDERR, "Abbruch: Demo-Seeder nur in WP_ENVIRONMENT_TYPE=development.\n" );
	exit( 1 );
}
if ( ! class_exists( \Liebherr\InterfaceWorld\MyLiebherr\DreamRepository::class ) ) {
	fwrite( STDERR, "Plugin liebherr-interface-world ist nicht aktiv.\n" );
	exit( 1 );
}

use Liebherr\InterfaceWorld\MyLiebherr\DreamRepository;
use Liebherr\InterfaceWorld\MyLiebherr\MachineRepository;
use Liebherr\InterfaceWorld\MyLiebherr\GalleryRepository;
use Liebherr\InterfaceWorld\MyLiebherr\Schema as MylSchema;
use Liebherr\InterfaceWorld\Pocket\PocketRepository;
use Liebherr\InterfaceWorld\Pocket\Schema as PocketSchema;

$admins = get_users( [ 'role' => 'administrator', 'number' => 1, 'fields' => 'ID' ] );
$uid    = (int) ( $admins[0] ?? 0 );
if ( $uid <= 0 ) {
	fwrite( STDERR, "Kein Administrator gefunden.\n" );
	exit( 1 );
}
global $wpdb;

if ( $reset ) {
	$wpdb->delete( MylSchema::dream_table(), [ 'user_id' => $uid ] );
	$wpdb->delete( MylSchema::machine_table(), [ 'user_id' => $uid ] );
	$wpdb->delete( MylSchema::gallery_table(), [ 'owner_id' => $uid ] );
	$wpdb->delete( PocketSchema::item_table(), [ 'user_id' => $uid ] );
	delete_option( 'liw_demo_myl_seeded_uid' );
	foreach ( [ 'liw_myl_enabled', 'liw_pocket_enabled', 'liw_ptime_enabled' ] as $flag ) {
		delete_option( $flag );
	}
	echo "DEMO-Inhalte des Admins #{$uid} entfernt, Flags ausgeschaltet.\n";
	exit( 0 );
}

// Flags scharf (nur Dev-Vorschau).
foreach ( [ 'liw_myl_enabled', 'liw_pocket_enabled', 'liw_ptime_enabled' ] as $flag ) {
	update_option( $flag, 1 );
}
echo "Flags gesetzt: liw_myl_enabled, liw_pocket_enabled, liw_ptime_enabled = 1\n";

if ( (int) get_option( 'liw_demo_myl_seeded_uid', 0 ) === $uid ) {
	echo "DEMO-Inhalte für Admin #{$uid} bereits vorhanden (idempotent – nichts neu angelegt).\n";
} else {
	foreach ( [
		[ 'machine_ref' => 'Liebherr R 9200', 'title_words' => 'Raupe Hydraulik Traum', 'note' => 'Wunschkonfiguration Tiefbau.', 'tags' => 'Bagger,Hydraulik', 'wish_status' => 'favorite', 'cover' => 1 ],
		[ 'machine_ref' => 'Liebherr LTM 1300', 'title_words' => 'Kran Teleskop Wunsch', 'note' => 'Mobilkran für Montage.', 'tags' => 'Kran', 'wish_status' => 'wish' ],
		[ 'machine_ref' => 'Liebherr T 264', 'title_words' => 'Muldenkipper Gross Idee', 'note' => 'Mining-Truck.', 'tags' => 'Mining', 'wish_status' => 'idea' ],
	] as $d ) {
		DreamRepository::add( $uid, $d );
	}
	foreach ( [
		[ 'name' => 'DEMO Liebherr R 936', 'serial' => 'SN-DEMO-936', 'location' => 'Baustelle Nord', 'note' => 'Kettenbagger.' ],
		[ 'name' => 'DEMO Liebherr L 566', 'serial' => 'SN-DEMO-566', 'location' => 'Halle 2', 'note' => 'Radlader.' ],
	] as $m ) {
		MachineRepository::add( $uid, $m );
	}
	GalleryRepository::add( $uid, [ 'title' => 'DEMO Baustelle Nord', 'album' => 'Demo', 'description' => 'Beispielbild (Mediathek-ID später setzen).', 'visibility' => 'private' ] );
	foreach ( [
		[ 'title' => 'DEMO Sicherheitshinweis Lastdiagramm', 'body' => 'Bitte Lastdiagramm vor Einsatz prüfen.', 'priority' => 'critical', 'requires_ack' => 1, 'source' => 'Pocket Alerts' ],
		[ 'title' => 'DEMO Wartung fällig: R 936', 'body' => 'Wartungsintervall in 3 Tagen.', 'priority' => 'high', 'requires_ack' => 1, 'source' => 'Machine Pocket' ],
		[ 'title' => 'DEMO Neues Adventure zu Hydraulik', 'body' => 'Passend zu deiner Maschine.', 'priority' => 'normal', 'source' => 'Adventure Pocket' ],
	] as $p ) {
		PocketRepository::add( $uid, $p );
	}
	update_option( 'liw_demo_myl_seeded_uid', $uid );
	echo "DEMO-Inhalte für Admin #{$uid} angelegt: 3 Dreams, 2 Machines, 1 Gallery, 3 Pocket-Items.\n";
}

$myl = (int) get_option( 'liw_my_liebherr_page_id', 0 );
$pk  = (int) get_option( 'liw_pocket_page_id', 0 );
echo "\nSeiten:\n  My Liebherr: " . ( $myl > 0 ? get_permalink( $myl ) : '(nicht angelegt – liw-seed-my-liebherr.php)' ) . "\n";
echo "  Pocket:      " . ( $pk > 0 ? get_permalink( $pk ) : '(nicht angelegt – liw-seed-pocket.php)' ) . "\n";
echo "Sichtbar nach Login als Admin (Rolle mit liw_myl_access). Zum Zurücksetzen: --reset.\n";
