<?php
/**
 * Snow Archive Template.
 *
 * A bar chart of the snowfall of the posts in an archive, with a list of them. A theme can override it with a
 * snow-archive.php of its own.
 *
 * @package Simple_Location
 * @since 6.2.0
 */

defined( 'ABSPATH' ) || exit;

$sloc_property = 'snow';
require __DIR__ . '/precipitation-archive.php';
