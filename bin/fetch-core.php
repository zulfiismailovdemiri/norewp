<?php
// Download WordPress (zip) for a locale and unpack it into <dir>.
// Why not `wp core download`? It unpacks the .tar.gz with PHP's PharData, which cuts file names
// longer than 100 characters (WordPress 7.1 has such files). ZipArchive handles them. Every file
// is checked afterwards with `wp core verify-checksums` (setup.sh).
// Usage: php bin/fetch-core.php <locale> <version|latest> <dir>
[$_, $locale, $version, $dir] = $argv + [null, 'de_DE', 'latest', 'wordpress'];
$api = 'https://api.wordpress.org/core/version-check/1.7/?locale=' . rawurlencode($locale)
    . ($version !== 'latest' ? '&version=' . rawurlencode($version) : '');
$offers = json_decode(file_get_contents($api), true)['offers'] ?? [];
$offer = null;
foreach ($offers as $o) {
    if ($o['locale'] === $locale && ($version === 'latest' || $o['current'] === $version)) {
        $offer = $o;
        break;
    }
}
if (!$offer) {
    fwrite(STDERR, "no WordPress $version download for locale $locale\n");
    exit(1);
}
echo "downloading WordPress {$offer['current']} ({$locale})\n";
$zip = tempnam(sys_get_temp_dir(), 'wordpress');
file_put_contents($zip, file_get_contents($offer['download']));
$tmp = sys_get_temp_dir() . '/wordpress-' . getmypid();
$archive = new ZipArchive();
if ($archive->open($zip) !== true || !$archive->extractTo($tmp)) {
    fwrite(STDERR, "could not unpack WordPress\n");
    exit(1);
}
$archive->close();
unlink($zip);
if (is_dir($dir)) {
    fwrite(STDERR, "$dir already exists\n");
    exit(1);
}
rename("$tmp/wordpress", $dir);
rmdir($tmp);
echo "unpacked into $dir\n";
