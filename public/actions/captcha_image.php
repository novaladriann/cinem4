<?php
session_start();

$characters = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
$captcha = '';

for ($i = 0; $i < 5; $i++) {
    $captcha .= $characters[random_int(0, strlen($characters) - 1)];
}

$_SESSION['login_captcha_code'] = $captcha;
$_SESSION['login_captcha_expires'] = time() + 300; // 5 menit

header('Content-Type: image/svg+xml; charset=UTF-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');

$width = 220;
$height = 72;

$particles = '';
for ($i = 0; $i < 26; $i++) {
    $cx = random_int(8, $width - 8);
    $cy = random_int(8, $height - 8);
    $r = random_int(1, 2);
    $opacity = random_int(12, 32) / 100;
    $fill = (random_int(0, 1) === 1) ? '#93c5fd' : '#ffffff';
    $particles .= "<circle cx=\"{$cx}\" cy=\"{$cy}\" r=\"{$r}\" fill=\"{$fill}\" opacity=\"{$opacity}\" />";
}

$paths = '';
for ($i = 0; $i < 2; $i++) {
    $startY = random_int(16, 56);
    $controlY = random_int(5, 67);
    $endY = random_int(14, 58);
    $opacity = random_int(12, 20) / 100;
    $paths .= "<path d=\"M -12 {$startY} C 48 {$controlY}, 130 " . random_int(8, 64) . ", 232 {$endY}\" fill=\"none\" stroke=\"#60a5fa\" stroke-width=\"1.35\" stroke-linecap=\"round\" opacity=\"{$opacity}\" />";
}

$text = '';
$startX = 34;
for ($i = 0; $i < strlen($captcha); $i++) {
    $char = htmlspecialchars($captcha[$i], ENT_QUOTES, 'UTF-8');
    $x = $startX + ($i * 32) + random_int(-1, 1);
    $y = 47 + random_int(-3, 3);
    $rotate = random_int(-7, 7);
    $fontSize = random_int(28, 31);
    $fill = ($i % 2 === 0) ? '#f8fafc' : '#dbeafe';
    $text .= "<text x=\"{$x}\" y=\"{$y}\" transform=\"rotate({$rotate} {$x} {$y})\" fill=\"{$fill}\" font-size=\"{$fontSize}\" font-family=\"Arial, Helvetica, sans-serif\" font-weight=\"800\" letter-spacing=\"1\">{$char}</text>";
}

echo <<<SVG
<svg xmlns="http://www.w3.org/2000/svg" width="{$width}" height="{$height}" viewBox="0 0 {$width} {$height}" role="img" aria-label="Kode captcha">
  <defs>
    <linearGradient id="bg" x1="0" y1="0" x2="1" y2="1">
      <stop offset="0%" stop-color="#08111f" />
      <stop offset="55%" stop-color="#0d1b32" />
      <stop offset="100%" stop-color="#12284a" />
    </linearGradient>
    <radialGradient id="glowA" cx="0.18" cy="0.5" r="0.9">
      <stop offset="0%" stop-color="#1d4ed8" stop-opacity="0.32" />
      <stop offset="100%" stop-color="#1d4ed8" stop-opacity="0" />
    </radialGradient>
    <radialGradient id="glowB" cx="0.82" cy="0.3" r="0.65">
      <stop offset="0%" stop-color="#38bdf8" stop-opacity="0.26" />
      <stop offset="100%" stop-color="#38bdf8" stop-opacity="0" />
    </radialGradient>
    <clipPath id="clipCaptcha">
      <rect x="0" y="0" width="{$width}" height="{$height}" rx="16" />
    </clipPath>
  </defs>

  <g clip-path="url(#clipCaptcha)">
    <rect width="100%" height="100%" rx="16" fill="url(#bg)" />
    <rect width="100%" height="100%" rx="16" fill="url(#glowA)" />
    <rect width="100%" height="100%" rx="16" fill="url(#glowB)" />
    <rect x="0" y="0" width="{$width}" height="{$height}" rx="16" fill="none" stroke="#ffffff" stroke-opacity="0.08" />

    <circle cx="26" cy="16" r="22" fill="#2563eb" opacity="0.14" />
    <circle cx="188" cy="60" r="28" fill="#38bdf8" opacity="0.12" />
    <circle cx="205" cy="12" r="16" fill="#1d4ed8" opacity="0.10" />

    {$paths}
    {$particles}

    <rect x="14" y="12" width="192" height="48" rx="12" fill="#020617" fill-opacity="0.18" />
    {$text}
  </g>
</svg>
SVG;
