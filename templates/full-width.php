<?php
/**
 * Liebherr Interface Solutions – Vollbild-Seitenvorlage (Template-Datei).
 *
 * Minimales Canvas: nur Seiteninhalt (unsere Shortcodes) + wp_head/wp_footer, kein Theme-Kopf/-Fuß.
 * Geladen über Frontend\PageTemplate::maybe_use(), wenn die Seite die Vorlage „liw-full-width.php" nutzt.
 *
 * @package Liebherr\InterfaceWorld
 * @since   0.1.0-alpha.39
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }
?>
<!doctype html>
<html <?php language_attributes(); ?>>
<head>
	<meta charset="<?php bloginfo( 'charset' ); ?>" />
	<meta name="viewport" content="width=device-width, initial-scale=1" />
	<?php wp_head(); ?>
</head>
<body <?php body_class( 'liw-fullwidth' ); ?>>
<?php wp_body_open(); ?>
<main id="liw-main">
	<?php
	while ( have_posts() ) {
		the_post();
		the_content();
	}
	?>
</main>
<?php wp_footer(); ?>
</body>
</html>
