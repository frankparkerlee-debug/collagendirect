<?php
declare(strict_types=1);

if (!function_exists('env')) {
  function env(string $key, string $default = ''): string {
    static $loaded = false, $vars = [];

    if (!$loaded) {
      $loaded = true;
      $vars = [];

      $path = __DIR__ . '/../.env'; // /public/api/.env
      if (is_file($path) && is_readable($path)) {
        $raw = file_get_contents($path) ?: '';
        if (substr($raw, 0, 3) === "\xEF\xBB\xBF") $raw = substr($raw, 3); // strip UTF-8 BOM
        $lines = preg_split("/\r\n|\n|\r/", $raw);
        foreach ($lines as $line) {
          $line = trim($line);
          if ($line === '' || $line[0] === '#') continue;
          $hashPos = strpos($line, '#'); // inline comments
          if ($hashPos !== false) {
            $before = substr($line, 0, $hashPos);
            if (strpos($before, '=') !== false) $line = rtrim($before);
          }
          $parts = explode('=', $line, 2);
          if (count($parts) !== 2) continue;
          $k = trim($parts[0]);
          $v = trim($parts[1]);
          if ((str_starts_with($v, '"') && str_ends_with($v, '"')) ||
              (str_starts_with($v, "'") && str_ends_with($v, "'"))) {
            $v = substr($v, 1, -1);
          }
          $vars[$k] = $v;
        }
      }
    }

    $v = getenv($key);
    if ($v !== false && $v !== null && $v !== '') return trim((string)$v);
    return array_key_exists($key, $vars) ? (string)$vars[$key] : $default;
  }
}
