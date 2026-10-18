<?php

defined('ABSPATH') || exit;

spl_autoload_register(static function ($class) {
	$prefix = 'CorsivoToolkit\\Vendor\\SvgSanitize\\';
	if (!str_starts_with($class, $prefix)) {
		return;
	}
	$relative = substr($class, strlen($prefix));
	if (!preg_match('/^[A-Za-z0-9_\\\\]+$/', $relative)) {
		return;
	}
	$path = __DIR__ . '/vendor/svg-sanitize/src/' . str_replace('\\', '/', $relative) . '.php';
	if (is_file($path)) {
		require_once $path;
	}
});

function corsivo_core_svg_sanitize($contents) {
	if (!class_exists('DOMDocument') || !is_string($contents) || trim($contents) === ''
		|| strlen($contents) > 2 * 1024 * 1024 || str_contains($contents, "\0")
		|| preg_match('/<!DOCTYPE|<!ENTITY/i', $contents)) {
		return false;
	}
	try {
		$sanitizer = new CorsivoToolkit\Vendor\SvgSanitize\Sanitizer();
		$sanitizer->removeRemoteReferences(true);
		$clean = $sanitizer->sanitize($contents);
		if (!is_string($clean) || $clean === '') {
			return false;
		}
		$document = new DOMDocument();
		$previous = libxml_use_internal_errors(true);
		try {
			$valid = $document->loadXML($clean, LIBXML_NONET);
		} finally {
			libxml_clear_errors();
			libxml_use_internal_errors($previous);
		}
		return $valid && $document->documentElement->localName === 'svg'
			&& in_array($document->documentElement->namespaceURI, [null, '', 'http://www.w3.org/2000/svg'], true)
			? $clean : false;
	} catch (Throwable $error) {
		return false;
	}
}

if ( ! function_exists( 'corsivo_toolkit_svg_sanitize' ) ) {
	function corsivo_toolkit_svg_sanitize( $contents ) {
		return corsivo_core_svg_sanitize( $contents );
	}
}
