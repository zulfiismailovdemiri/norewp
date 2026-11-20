<?php
// Download a plugin from wordpress.org and unpack it into wp-content/plugins (used by setup.sh
// before WordPress is installed, when `wp plugin install` cannot run yet).
// Usage: php bin/fetch-plugin.php <slug> <plugins-dir>
[$_, $slug, $dir] = $argv + [null, null, null];
if (!$slug || !$dir) {
    fwrite(STDERR, "usage: php bin/fetch-plugin.php <slug> <plugins-dir>\n");
    exit(2);
}
$info = json_decode(file_get_contents("https://api.wordpress.org/plugins/info/1.0/" . rawurlencode($slug) . ".json"), true);
if (empty($info['download_link'])) {
    fwrite(STDERR, "plugin $slug not found on wordpress.org\n");
    exit(1);
}
$zip = tempnam(sys_get_temp_dir(), 'wp-plugin');
file_put_contents($zip, file_get_contents($info['download_link']));
$archive = new ZipArchive();
if ($archive->open($zip) !== true || !$archive->extractTo($dir)) {
    fwrite(STDERR, "could not unpack $slug\n");
    exit(1);
}
$archive->close();
unlink($zip);
echo "fetched $slug {$info['version']}\n";
