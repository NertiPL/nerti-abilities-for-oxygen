<?php
/** Samotest werdyktu typografii. Uruchom: wp eval-file testy/sprawdz-typografie.php */
$tmp = sys_get_temp_dir() . '/bdox-typo-test.css';
$ok = 0; $all = 0;
$sprawdz = static function ( string $css, string $klucz, string $oczekiwane ) use ( $tmp, &$ok, &$all ) {
	file_put_contents( $tmp, $css );
	$r = bdox_pack_compiled_typography( $tmp );
	$all++;
	$trafione = false !== strpos( $r[ $klucz ], $oczekiwane );
	$ok += $trafione ? 1 : 0;
	echo ( $trafione ? 'ok  ' : 'BLAD' ) . "  $klucz zawiera \"$oczekiwane\" -> \"{$r[$klucz]}\"\n";
};

// Slug, ktory sie nie rozwinal — to jest przypadek, dla ktorego ten werdykt istnieje.
$sprawdz( ':root{--bde-heading-font-family:gfont-bricolage-grotesque;--bde-body-font-family:"Inter",sans-serif}', 'verdict', 'unresolved_font_slug in heading_font' );
// Oba zle.
$sprawdz( ':root{--bde-heading-font-family:gfont-a-b;--bde-body-font-family:gfont-c-d}', 'verdict', 'heading_font, body_font' );
// Oba dobre.
$sprawdz( ':root{--bde-heading-font-family:"Bricolage Grotesque",sans-serif;--bde-body-font-family:"Inter",sans-serif;--bde-base-font-size:16px}', 'verdict', 'ok' );
// Wygrywa deklaracja pozniejsza, nie pierwsza — Oxygen nadpisuje domyslne w kolejnym bloku.
$sprawdz( ':root{--bde-base-font-size:15px}:root{--bde-base-font-size:16px}', 'base_size', '16px' );
// Brak pliku nie moze udawac sukcesu.
$sprawdz( '', 'verdict', 'ok' );
@unlink( $tmp );
$r = bdox_pack_compiled_typography( '/nie/ma/takiego.css' );
$all++; $trafione = false !== strpos( $r['verdict'], 'no-compiled' ); $ok += $trafione ? 1 : 0;
echo ( $trafione ? 'ok  ' : 'BLAD' ) . "  brak pliku -> \"{$r['verdict']}\"\n";
echo "\n" . ( $ok === $all ? "OK $ok/$all\n" : "BLEDY: $ok/$all\n" );
