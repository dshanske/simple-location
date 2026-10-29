<?php
/**
 * Rain Archive Template.
 *
 * A bar chart of the rainfall of the posts in an archive, with a list of them. A theme can override it with a
 * rain-archive.php of its own.
 *
 * @package Simple_Location
 * @since 6.1.2
 */

defined( 'ABSPATH' ) || exit;

$sloc_property = 'rain';
require __DIR__ . '/precipitation-archive.php';
