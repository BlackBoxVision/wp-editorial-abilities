<?php

declare(strict_types=1);

/**
 * Integration checks for MediaService::uploadMediaFromFile().
 *
 * Run inside wp-env:
 *   npx @wordpress/env run cli wp eval-file wp-content/plugins/wp-editorial-abilities/tests/upload-media-file.php
 */

use WpEditorialAbilities\Services\MediaService;

if (! defined('ABSPATH')) {
    fwrite(STDERR, "Run this script through WP-CLI inside WordPress.\n");
    exit(1);
}

$media = new MediaService();
$failures = 0;

$assert = static function (bool $condition, string $message) use (&$failures): void {
    if (! $condition) {
        fwrite(STDERR, "FAIL: {$message}\n");
        ++$failures;
    }
};

$missing = $media->uploadMediaFromFile(['file_path' => '/tmp/wpea-does-not-exist-' . wp_generate_password(8, false) . '.png']);
$assert(is_wp_error($missing), 'unreadable path should return WP_Error');
$assert(
    is_wp_error($missing) && $missing->get_error_code() === 'wpea_file_not_readable',
    'unreadable path should use wpea_file_not_readable'
);

$tmp = wp_tempnam('wpea-upload-test');
if ($tmp === '') {
    fwrite(STDERR, "FAIL: could not create temp file\n");
    exit(1);
}

$png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==', true);
file_put_contents($tmp, $png);

$uploaded = $media->uploadMediaFromFile([
    'file_path' => $tmp,
    'alt_text' => 'Integration alt text',
    'caption' => 'Integration caption',
]);

@unlink($tmp);

$assert(! is_wp_error($uploaded), 'readable file should upload successfully');
$assert(is_array($uploaded) && ! empty($uploaded['media_id']), 'upload should return media_id');
$assert(is_array($uploaded) && ! empty($uploaded['url']), 'upload should return url');

if (is_array($uploaded) && ! empty($uploaded['media_id'])) {
    $alt = get_post_meta((int) $uploaded['media_id'], '_wp_attachment_image_alt', true);
    $assert($alt === 'Integration alt text', 'alt_text should be stored on attachment');

    $attachment = get_post((int) $uploaded['media_id']);
    $assert($attachment instanceof WP_Post, 'attachment post should exist');
    if ($attachment instanceof WP_Post) {
        $assert($attachment->post_excerpt === 'Integration caption', 'caption should be stored on attachment');
    }

    wp_delete_attachment((int) $uploaded['media_id'], true);
}

if ($failures > 0) {
    fwrite(STDERR, "{$failures} assertion(s) failed.\n");
    exit(1);
}

echo "PASS: upload-media-file integration checks\n";
