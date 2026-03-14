<?php
declare(strict_types=1);

function is_https(): bool
{
	return (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
}

function h(string $value): string
{
	return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function read_projects_config(string $path): array
{
	if (!is_file($path)) {
		return [];
	}
	$json = file_get_contents($path);
	if ($json === false) {
		return [];
	}
	$decoded = json_decode($json, true);
	return is_array($decoded) ? $decoded : [];
}

function list_project_dirs(string $htdocs_path, array $config): array
{
	$entries = @scandir($htdocs_path);
	if (!is_array($entries)) {
		return [];
	}

	$projects = [];
	foreach ($entries as $name) {
		if ($name === '.' || $name === '..') {
			continue;
		}
		if ($name[0] === '.') {
			continue;
		}

		$full = $htdocs_path . DIRECTORY_SEPARATOR . $name;
		if (!is_dir($full)) {
			continue;
		}

		$item_cfg = $config[$name] ?? [];
		if (is_array($item_cfg) && !empty($item_cfg['hidden'])) {
			continue;
		}

		$projects[] = $name;
	}

	natcasesort($projects);
	return array_values($projects);
}

function current_base_url(): string
{
	$script = (string) ($_SERVER['SCRIPT_NAME'] ?? '');
	$dir = str_replace('\\', '/', dirname($script));
	if ($dir === '/' || $dir === '.' || $dir === '') {
		return '';
	}
	return rtrim($dir, '/');
}

function current_origin(): string
{
	$scheme = is_https() ? 'https' : 'http';
	$host = (string) ($_SERVER['HTTP_HOST'] ?? '');
	if ($host === '') {
		$host = 'localhost';
	}
	return $scheme . '://' . $host;
}

function resolve_logo_url(string $master_hub_dir, string $logo, string $base_url): ?string
{
	$logo = trim($logo);
	if ($logo === '') {
		return null;
	}
	if (preg_match('~^https?://~i', $logo)) {
		return $logo;
	}
	if ($logo[0] === '/') {
		return $logo;
	}

	$logo = str_replace(["\\", "\0"], ['/', ''], $logo);

	$hub_real = realpath($master_hub_dir);
	if ($hub_real === false) {
		return null;
	}

	$candidates = [$logo];
	if (strpos($logo, '/') === false) {
		// Compatibility: if projects.json only provides the filename (for example, "logo.png"),
		// assume it lives in master-hub/logos/.
		array_unshift($candidates, 'logos/' . $logo);
	}

	foreach ($candidates as $candidate) {
		$logo_path = realpath($master_hub_dir . DIRECTORY_SEPARATOR . $candidate);
		if ($logo_path === false) {
			continue;
		}
		if (stripos($logo_path, $hub_real) !== 0) {
			continue;
		}

		$rel = str_replace('\\', '/', substr($logo_path, strlen($hub_real)));
		$rel = ltrim($rel, '/');
		return ($base_url !== '' ? $base_url : '') . '/' . $rel;
	}

	return null;
}

$hub_dir = __DIR__;
$htdocs_dir = realpath(__DIR__ . DIRECTORY_SEPARATOR . '..') ?: dirname(__DIR__);
$config_path = $hub_dir . DIRECTORY_SEPARATOR . 'projects.json';
$config = read_projects_config($config_path);

$projects = list_project_dirs($htdocs_dir, $config);
$dash_url = '/dashboard/';
$phpmyadmin_url = rtrim(current_origin(), '/') . '/phpmyadmin/';
$hub_base_url = current_base_url();
$default_logo = 'logos/hub-mark.svg';

$all_groups = [];
foreach ($projects as $dir) {
	if (!empty($config[$dir]['group'])) {
		$all_groups[$config[$dir]['group']] = true;
	}
}
$all_groups = array_keys($all_groups);
sort($all_groups);
?>
<!doctype html>
<html lang="en">

<head>
	<meta charset="utf-8" />
	<meta name="viewport" content="width=device-width,initial-scale=1" />
	<title>Project Hub</title>
	<link rel="icon" type="image/svg+xml" href="logos/hub-mark.svg">
	<link rel="preconnect" href="https://fonts.googleapis.com">
	<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>

	<link
		href="https://fonts.googleapis.com/css2?family=Geist:wght@300;400;500;600;700&family=Geist+Mono:wght@400;500&display=swap"
		rel="stylesheet">
	<style>
		/* ── TOKENS ───────────────────────────────────────────────── */
		:root {
			--bg: #09090b;
			--bg-card: #111113;
			--bg-card2: #141416;
			--border: rgba(255, 255, 255, .07);
			--border-hi: rgba(255, 255, 255, .14);
			--text: #fafaf9;
			--muted: #71717a;
			--muted-2: #3f3f46;
			--sans: 'Geist', 'Inter', system-ui, sans-serif;
			--mono: 'Geist Mono', ui-monospace, monospace;
			--r: 10px;
			--r-sm: 6px;

			/* 6 card accent palette */
			--a1: #a3e635;
			/* lime    */
			--a2: #38bdf8;
			/* sky     */
			--a3: #f472b6;
			/* pink    */
			--a4: #fb923c;
			/* orange  */
			--a5: #a78bfa;
			/* violet  */
			--a6: #34d399;
			/* emerald */
		}

		*,
		*::before,
		*::after {
			box-sizing: border-box;
			margin: 0;
			padding: 0
		}

		html,
		body {
			min-height: 100%
		}

		body {
			font-family: var(--sans);
			font-size: 13px;
			color: var(--text);
			background: var(--bg);
			color-scheme: dark;
			-webkit-font-smoothing: antialiased;
			/* subtle dot grid */
			background-image: radial-gradient(circle, rgba(255, 255, 255, .045) 1px, transparent 1px);
			background-size: 24px 24px;
		}

		a {
			color: inherit;
			text-decoration: none
		}

		/* ── PAGE WRAPPER ─────────────────────────────────────────── */
		.wrap {
			max-width: 1640px;
			margin: 0 auto;
			padding: 20px 20px 80px
		}

		/* ── HEADER ───────────────────────────────────────────────── */
		.hdr {
			position: sticky;
			top: 12px;
			z-index: 100;
			display: flex;
			flex-wrap: wrap;
			gap: 10px;
			align-items: center;
			justify-content: space-between;
			padding: 10px 14px;
			background: rgba(9, 9, 11, .82);
			backdrop-filter: blur(20px) saturate(1.6);
			border: 1px solid var(--border-hi);
			border-radius: var(--r);
			box-shadow: 0 0 0 1px rgba(255, 255, 255, .04) inset, 0 4px 32px rgba(0, 0, 0, .7);
		}

		/* logo mark — pure CSS geometric */
		.mark {
			width: 32px;
			height: 32px;
			flex: 0 0 auto;
			position: relative;
			border-radius: 7px;
			background: var(--a1);
			display: grid;
			place-items: center;
			overflow: hidden;
		}

		.mark::before {
			content: '';
			position: absolute;
			width: 18px;
			height: 18px;
			background: rgba(0, 0, 0, .35);
			border-radius: 3px;
			transform: rotate(15deg);
		}

		.mark::after {
			content: '';
			position: absolute;
			width: 10px;
			height: 10px;
			background: rgba(0, 0, 0, .55);
			border-radius: 2px;
			transform: rotate(15deg) translate(5px, -5px);
		}

		.brand {
			display: flex;
			gap: 10px;
			align-items: center
		}

		.brand-text {}

		.brand-name {
			font-size: 15px;
			font-weight: 600;
			letter-spacing: -.4px
		}

		.brand-sub {
			font-family: var(--mono);
			font-size: 11px;
			color: var(--muted);
			margin-top: 1px
		}

		.brand-sub b {
			color: var(--a1);
			font-weight: 500
		}

		.controls {
			display: flex;
			flex-wrap: wrap;
			gap: 8px;
			align-items: center
		}

		/* ── SEARCH ───────────────────────────────────────────────── */
		.search {
			display: flex;
			align-items: center;
			gap: 8px;
			padding: 7px 12px;
			background: rgba(255, 255, 255, .04);
			border: 1px solid var(--border-hi);
			border-radius: var(--r-sm);
			min-width: 200px;
			transition: border-color .15s, background .15s;
		}

		.search:focus-within {
			border-color: rgba(255, 255, 255, .28);
			background: rgba(255, 255, 255, .06)
		}

		.search-icon {
			color: var(--muted);
			font-size: 12px;
			font-family: var(--mono)
		}

		.search input {
			background: transparent;
			border: 0;
			outline: 0;
			color: var(--text);
			font-size: 13px;
			font-family: var(--sans);
			width: 100%;
		}

		.search input::placeholder {
			color: var(--muted-2)
		}

		/* ── SELECT ───────────────────────────────────────────────── */
		.sel {
			display: flex;
			align-items: center;
			gap: 7px;
			padding: 7px 12px;
			background: rgba(255, 255, 255, .04);
			border: 1px solid var(--border);
			border-radius: var(--r-sm);
		}

		.sel-lbl {
			font-family: var(--mono);
			font-size: 10px;
			color: var(--muted-2);
			text-transform: uppercase;
			letter-spacing: .8px;
			white-space: nowrap
		}

		.sel select {
			appearance: none;
			background: transparent;
			border: 0;
			outline: 0;
			color: var(--text);
			font-size: 12px;
			font-family: var(--sans);
			cursor: pointer;
			padding-right: 10px;
		}

		.sel-arr {
			color: var(--muted);
			font-size: 9px;
			pointer-events: none
		}

		/* ── BUTTONS ──────────────────────────────────────────────── */
		.btn {
			display: inline-flex;
			align-items: center;
			gap: 6px;
			padding: 7px 14px;
			border-radius: var(--r-sm);
			border: 1px solid var(--border-hi);
			background: rgba(255, 255, 255, .05);
			font-size: 12px;
			font-family: var(--sans);
			font-weight: 500;
			color: var(--text);
			cursor: pointer;
			white-space: nowrap;
			transition: background .12s, border-color .12s, transform .08s;
		}

		.btn:hover {
			background: rgba(255, 255, 255, .1);
			border-color: rgba(255, 255, 255, .22)
		}

		.btn:active {
			transform: scale(.95)
		}

		.btn-lime {
			background: var(--a1);
			border-color: var(--a1);
			color: #09090b;
			font-weight: 600
		}

		.btn-lime:hover {
			background: #bef264;
			border-color: #bef264
		}

		.btn-ghost {
			background: transparent;
			border-color: var(--border);
			color: var(--muted)
		}

		.btn-ghost:hover {
			color: var(--text);
			border-color: var(--border-hi);
			background: rgba(255, 255, 255, .04)
		}

		/* ── GLOBAL COLOR SWATCHES ────────────────────────────────── */
		.gswatches {
			display: flex;
			align-items: center;
			gap: 5px;
			padding: 6px 10px;
			background: rgba(255, 255, 255, .03);
			border: 1px solid var(--border);
			border-radius: var(--r-sm);
		}

		.gsw-lbl {
			font-family: var(--mono);
			font-size: 10px;
			color: var(--muted-2);
			white-space: nowrap;
			letter-spacing: .5px
		}

		.sw {
			width: 16px;
			height: 16px;
			border-radius: 4px;
			cursor: pointer;
			border: 2px solid transparent;
			transition: transform .12s, border-color .12s;
			flex: 0 0 auto;
		}

		.sw:hover {
			transform: scale(1.25)
		}

		.sw.on {
			border-color: #fff;
			transform: scale(1.15)
		}

		/* ── SECTION LABELS ───────────────────────────────────────── */
		.groups {
			margin-top: 18px;
			display: flex;
			flex-direction: column;
			gap: 24px
		}

		.groups.flat {
			display: grid;
			grid-template-columns: repeat(5, 1fr);
			gap: 10px;
		}

		@media(max-width:1200px) {
			.groups.flat {
				grid-template-columns: repeat(4, 1fr)
			}
		}

		@media(max-width:900px) {
			.groups.flat {
				grid-template-columns: repeat(3, 1fr)
			}
		}

		@media(max-width:620px) {
			.groups.flat {
				grid-template-columns: repeat(2, 1fr)
			}
		}

		.grp-head {
			display: flex;
			align-items: center;
			gap: 10px;
			margin-bottom: 12px;
			padding-bottom: 10px;
			border-bottom: 1px solid var(--border);
		}

		.grp-label {
			font-family: var(--mono);
			font-size: 10px;
			font-weight: 500;
			letter-spacing: 1.2px;
			text-transform: uppercase;
			color: var(--muted);
			display: flex;
			align-items: center;
			gap: 8px;
		}

		.grp-dot {
			width: 5px;
			height: 5px;
			border-radius: 50%;
			background: var(--a1);
			box-shadow: 0 0 8px var(--a1);
		}

		.grp-count {
			color: var(--muted-2)
		}

		.grp-grid {
			display: grid;
			grid-template-columns: repeat(5, 1fr);
			gap: 10px;
		}

		@media(max-width:1200px) {
			.grp-grid {
				grid-template-columns: repeat(4, 1fr)
			}
		}

		@media(max-width:900px) {
			.grp-grid {
				grid-template-columns: repeat(3, 1fr)
			}
		}

		@media(max-width:620px) {
			.grp-grid {
				grid-template-columns: repeat(2, 1fr)
			}
		}

		.groups[data-collapsed="1"] .grp-grid {
			display: none
		}

		/* ── CARD ─────────────────────────────────────────────────── */
		.card {
			--ac: var(--a1);
			position: relative;
			display: flex;
			flex-direction: column;
			border-radius: var(--r);
			border: 1px solid var(--border);
			background: var(--bg-card);
			overflow: hidden;
			transition: border-color .18s, transform .14s, box-shadow .18s;
			cursor: default;
			min-height: 0;
		}

		/* top color bar */
		.card::before {
			content: '';
			position: absolute;
			top: 0;
			left: 0;
			right: 0;
			height: 2px;
			background: var(--ac);
			opacity: .6;
			transition: opacity .18s;
		}

		.card:hover {
			border-color: var(--border-hi);
			transform: translateY(-3px);
			box-shadow: 0 12px 40px rgba(0, 0, 0, .5), 0 0 0 1px rgba(255, 255, 255, .06) inset;
		}

		.card:hover::before {
			opacity: 1
		}

		/* decorative shape inside card */
		.card-deco {
			position: absolute;
			top: -20px;
			right: -20px;
			width: 80px;
			height: 80px;
			border-radius: 50%;
			background: var(--ac);
			opacity: .4;
			pointer-events: none;
			transition: opacity .18s;
		}

		.card:hover .card-deco {
			opacity: .4
		}

		/* pinned */
		.card[data-pinned="1"] {
			border-color: rgba(251, 146, 60, .25)
		}

		.card[data-pinned="1"]::before {
			background: var(--pin, #fb923c);
			opacity: .9
		}

		/* color variants */
		.card[data-color="1"] {
			--ac: var(--a1)
		}

		.card[data-color="2"] {
			--ac: var(--a2)
		}

		.card[data-color="3"] {
			--ac: var(--a3)
		}

		.card[data-color="4"] {
			--ac: var(--a4)
		}

		.card[data-color="5"] {
			--ac: var(--a5)
		}

		.card[data-color="6"] {
			--ac: var(--a6)
		}

		/* ── CARD BODY ────────────────────────────────────────────── */
		.card-body {
			padding: 14px 14px 12px;
			display: flex;
			flex-direction: column;
			gap: 10px;
			flex: 1;
		}

		.card-row1 {
			display: flex;
			align-items: flex-start;
			gap: 10px;
			position: relative
		}

		.card-icon {
			width: 34px;
			height: 34px;
			flex: 0 0 auto;
			border-radius: 7px;
			background: rgba(255, 255, 255, .05);
			border: 1px solid var(--border);
			display: grid;
			place-items: center;
			overflow: hidden;
			font-family: var(--mono);
			font-size: 11px;
			font-weight: 500;
			color: var(--ac);
			position: relative;
			transition: background .15s;
		}

		/* tiny inner shape for flair */
		.card-icon::after {
			content: '';
			position: absolute;
			width: 20px;
			height: 20px;
			border-radius: 50%;
			background: var(--ac);
			opacity: .08;
			top: -5px;
			right: -5px;
			transition: opacity .15s;
		}

		.card:hover .card-icon {
			background: rgba(255, 255, 255, .08)
		}

		.card:hover .card-icon::after {
			opacity: .18
		}

		.card-icon img {
			width: 100%;
			height: 100%;
			object-fit: cover;
			border-radius: 6px
		}

		.card-meta {
			min-width: 0;
			flex: 1
		}

		.card-name {
			font-size: 13px;
			font-weight: 600;
			letter-spacing: -.25px;
			white-space: nowrap;
			overflow: hidden;
			text-overflow: ellipsis;
			padding-right: 24px;
			color: var(--text);
			line-height: 1.3;
		}

		.card-folder {
			font-family: var(--mono);
			font-size: 10px;
			color: var(--muted);
			margin-top: 2px;
			white-space: nowrap;
			overflow: hidden;
			text-overflow: ellipsis;
		}

		.card-desc {
			font-size: 11.5px;
			color: var(--muted);
			line-height: 1.55;
			display: -webkit-box;
			-webkit-line-clamp: 2;
			-webkit-box-orient: vertical;
			overflow: hidden;
		}

		.card-chips {
			display: flex;
			flex-wrap: wrap;
			gap: 4px
		}

		.chip {
			font-family: var(--mono);
			font-size: 10px;
			padding: 2px 7px;
			border-radius: 4px;
			border: 1px solid var(--border);
			background: rgba(255, 255, 255, .03);
			color: var(--muted);
			white-space: nowrap;
		}

		.chip-accent {
			border-color: rgba(163, 230, 53, .2);
			background: rgba(163, 230, 53, .06);
			color: var(--ac);
			border-color: color-mix(in srgb, var(--ac) 30%, transparent);
			background: color-mix(in srgb, var(--ac) 7%, transparent);
		}

		/* ── PIN BUTTON ───────────────────────────────────────────── */
		.pin {
			position: absolute;
			top: 0;
			right: 0;
			width: 22px;
			height: 22px;
			border-radius: 5px;
			border: 1px solid var(--border);
			background: rgba(255, 255, 255, .04);
			color: var(--muted-2);
			cursor: pointer;
			display: grid;
			place-items: center;
			transition: color .12s, background .12s, border-color .12s, transform .08s;
		}

		.pin:hover {
			color: var(--text);
			border-color: var(--border-hi);
			background: rgba(255, 255, 255, .08)
		}

		.pin:active {
			transform: scale(.85)
		}

		.pin svg {
			display: block;
			width: 11px;
			height: 11px
		}

		.card[data-pinned="1"] .pin {
			color: #fb923c;
			border-color: rgba(251, 146, 60, .3);
			background: rgba(251, 146, 60, .08);
		}

		/* ── COLOR SWATCHES INSIDE CARD ───────────────────────────── */
		.card-swatches {
			display: flex;
			gap: 4px;
			padding: 0 14px 10px;
		}

		.csw {
			width: 13px;
			height: 13px;
			border-radius: 3px;
			border: 1.5px solid transparent;
			cursor: pointer;
			transition: transform .1s, border-color .1s;
			flex: 0 0 auto;
		}

		.csw:hover {
			transform: scale(1.3)
		}

		.csw.on {
			border-color: #fff
		}

		/* ── CARD FOOTER ──────────────────────────────────────────── */
		.card-foot {
			padding: 9px 10px 10px;
			border-top: 1px solid var(--border);
			display: flex;
			gap: 6px;
		}

		.cta {
			flex: 1;
			display: inline-flex;
			align-items: center;
			justify-content: center;
			padding: 6px 8px;
			border-radius: 5px;
			border: 1px solid var(--border);
			background: rgba(255, 255, 255, .04);
			font-size: 11px;
			font-family: var(--sans);
			font-weight: 500;
			color: var(--text);
			cursor: pointer;
			transition: background .1s, border-color .1s;
			white-space: nowrap;
		}

		.cta:hover {
			background: rgba(255, 255, 255, .08);
			border-color: var(--border-hi)
		}

		.cta-primary {
			background: var(--ac);
			border-color: var(--ac);
			color: #09090b;
			font-weight: 600;
			transition: filter .12s;
		}

		.cta-primary:hover {
			filter: brightness(1.12);
			background: var(--ac);
			border-color: var(--ac)
		}

		/* ── PANEL ────────────────────────────────────────────────── */
		.panel {
			margin-top: 18px;
			border: 1px solid var(--border);
			border-radius: var(--r);
			overflow: hidden;
			background: var(--bg-card);
		}

		.panel-head {
			display: flex;
			align-items: center;
			justify-content: space-between;
			padding: 11px 16px;
			border-bottom: 1px solid var(--border);
		}

		.panel-title {
			font-family: var(--mono);
			font-size: 10px;
			color: var(--muted);
			letter-spacing: .8px;
			text-transform: uppercase
		}

		.panel-body {
			padding: 0
		}

		iframe {
			width: 100%;
			height: 720px;
			border: 0;
			background: #fff
		}

		/* ── FOOTER ───────────────────────────────────────────────── */
		.foot {
			margin-top: 24px;
			padding-top: 14px;
			border-top: 1px solid var(--border);
			display: flex;
			align-items: center;
			justify-content: space-between;
			gap: 12px;
			flex-wrap: wrap;
			font-family: var(--mono);
			font-size: 10px;
			color: var(--muted-2);
		}

		.foot-tip {
			display: flex;
			align-items: center;
			gap: 4px;
			flex-wrap: wrap;
		}

		.foot-credit {
			display: inline-flex;
			align-items: center;
			gap: 10px;
			padding: 6px 10px;
			border: 1px solid var(--border);
			border-radius: 999px;
			background: rgba(255, 255, 255, .03);
			color: var(--text);
			transition: border-color .12s, background .12s, transform .08s;
		}

		.foot-credit:hover {
			border-color: var(--border-hi);
			background: rgba(255, 255, 255, .06);
			transform: translateY(-1px);
		}

		.foot-credit img {
			width: 24px;
			height: 24px;
			object-fit: contain;
			border-radius: 999px;
			flex: 0 0 auto;
		}

		.foot-credit span {
			color: var(--muted);
		}

		.foot-credit strong {
			color: var(--text);
			font-weight: 600;
		}

		.foot code {
			color: var(--muted);
			background: rgba(255, 255, 255, .05);
			padding: 1px 5px;
			border-radius: 3px;
			border: 1px solid var(--border)
		}

		/* ── MODAL ────────────────────────────────────────────────── */
		.modal {
			padding: 0;
			border: 1px solid var(--border-hi);
			border-radius: var(--r);
			background: var(--bg-card);
			color: var(--text);
			margin: auto;
			width: 100%;
			max-width: 480px;
			box-shadow: 0 20px 60px rgba(0,0,0,0.8), 0 0 0 1px rgba(255,255,255,0.05) inset;
		}
		.modal::backdrop {
			background: rgba(0,0,0,0.7);
			backdrop-filter: blur(4px);
		}
		.modal-head {
			padding: 16px 20px;
			border-bottom: 1px solid var(--border);
			display: flex;
			align-items: center;
			justify-content: space-between;
			font-size: 14px;
			font-weight: 600;
		}
		.modal-close {
			background: transparent;
			border: none;
			color: var(--muted);
			cursor: pointer;
			font-size: 18px;
			line-height: 1;
		}
		.modal-close:hover { color: var(--text); }
		.modal-body {
			padding: 20px;
			display: flex;
			flex-direction: column;
			gap: 16px;
		}
		.form-group {
			display: flex;
			flex-direction: column;
			gap: 6px;
		}
		.form-label {
			font-family: var(--mono);
			font-size: 11px;
			color: var(--muted-2);
			text-transform: uppercase;
			letter-spacing: .5px;
		}
		.form-control {
			background: rgba(255,255,255,0.03);
			border: 1px solid var(--border);
			border-radius: var(--r-sm);
			color: var(--text);
			font-family: var(--sans);
			font-size: 13px;
			padding: 8px 12px;
			width: 100%;
			outline: none;
			transition: border-color .15s, background .15s;
		}
		.form-control:focus {
			border-color: rgba(255,255,255,0.28);
			background: rgba(255,255,255,0.06);
		}
		.form-control::file-selector-button {
			background: rgba(255,255,255,0.05);
			border: 1px solid var(--border);
			color: var(--text);
			padding: 4px 8px;
			border-radius: var(--r-sm);
			cursor: pointer;
			font-family: var(--sans);
			font-size: 12px;
			font-weight: 500;
			margin-right: 12px;
		}
		.form-control option {
			background: var(--bg-card);
			color: var(--text);
		}
		.modal-foot {
			padding: 16px 20px;
			border-top: 1px solid var(--border);
			display: flex;
			justify-content: flex-end;
			gap: 10px;
		}

		/* ── ALERTS ───────────────────────────────────────────────── */
		.alert {
			padding: 10px 14px;
			border-radius: var(--r-sm);
			margin-bottom: 20px;
			font-size: 13px;
		}
		.alert-error {
			background: rgba(239, 68, 68, 0.1);
			border: 1px solid rgba(239, 68, 68, 0.2);
			color: #ef4444;
		}
		.alert-success {
			background: rgba(34, 197, 94, 0.1);
			border: 1px solid rgba(34, 197, 94, 0.2);
			color: #22c55e;
		}
	</style>
</head>

<body>
	<div class="wrap">

		<!-- HEADER -->
		<header class="hdr">
			<div class="brand">
				<div class="mark"></div>
				<div class="brand-text">
					<div class="brand-name">Master Hub</div>
					<div class="brand-sub"><b id="count">0</b> active projects</div>
				</div>
			</div>

			<div class="controls">
				<div class="search">
					<span class="search-icon">/</span>
					<input id="q" type="search" placeholder="search projects..." autocomplete="off" />
				</div>

				<div class="sel">
					<span class="sel-lbl">group</span>
					<select id="groupBy">
						<option value="favorites">Favorites</option>
						<option value="group">By group</option>
						<option value="alpha">A–Z</option>
						<option value="none">None</option>
					</select>
					<span class="sel-arr">▾</span>
				</div>

				<div class="gswatches">
					<span class="gsw-lbl">color</span>
					<div class="sw on" data-gc="1" style="background:#a3e635" title="Lime"></div>
					<div class="sw" data-gc="2" style="background:#38bdf8" title="Sky"></div>
					<div class="sw" data-gc="3" style="background:#f472b6" title="Pink"></div>
					<div class="sw" data-gc="4" style="background:#fb923c" title="Orange"></div>
					<div class="sw" data-gc="5" style="background:#a78bfa" title="Violet"></div>
					<div class="sw" data-gc="6" style="background:#34d399" title="Emerald"></div>
				</div>

				<button class="btn btn-ghost" id="toggleCollapse" style="display:none">Collapse</button>
				<button class="btn btn-lime" id="btnNewProject">New Project</button>
				<a class="btn btn-lime" href="<?= h($dash_url) ?>">XAMPP</a>
				<a class="btn btn-ghost" href="<?= h($phpmyadmin_url) ?>" target="_blank"
					rel="noreferrer">phpMyAdmin</a>
				<button class="btn btn-ghost" id="toggleDash">Embedded</button>
			</div>
		</header>

		<?php if (!empty($_GET['error'])): ?>
			<div class="alert alert-error"><?= h($_GET['error']) ?></div>
		<?php endif; ?>
		<?php if (!empty($_GET['success'])): ?>
			<div class="alert alert-success">Project created successfully!</div>
		<?php endif; ?>

		<!-- GRID -->
		<div class="groups" id="grid">
			<?php foreach ($projects as $dir):
				$cfg = is_array($config[$dir] ?? null) ? $config[$dir] : [];
				$title = (string) ($cfg['title'] ?? $dir);
				$desc = (string) ($cfg['description'] ?? '');
				$logo = (string) ($cfg['logo'] ?? '');
				$group = (string) ($cfg['group'] ?? '');
				$tags = [];
				foreach (($cfg['tags'] ?? []) as $t) {
					$t = trim((string) $t);
					if ($t !== '')
						$tags[] = $t;
				}
				$dflt_pin = !empty($cfg['pinned']);
				$eff_logo = $logo !== '' ? $logo : $default_logo;
				$logo_url = resolve_logo_url($hub_dir, $eff_logo, $hub_base_url);
				$url = '/' . rawurlencode($dir) . '/';
				$ini = strtoupper(mb_substr($dir, 0, 2, 'UTF-8'));
				?>
				<div class="card" data-key="<?= h($dir) ?>" data-sort="<?= h(mb_strtolower($dir, 'UTF-8')) ?>"
					data-name="<?= h(mb_strtolower($dir, 'UTF-8')) ?>" data-desc="<?= h(mb_strtolower($desc, 'UTF-8')) ?>"
					data-group="<?= h(mb_strtolower($group, 'UTF-8')) ?>" data-group-label="<?= h($group) ?>"
					data-tags="<?= h(mb_strtolower(implode(',', $tags), 'UTF-8')) ?>"
					data-default-pinned="<?= $dflt_pin ? '1' : '0' ?>" data-pinned="<?= $dflt_pin ? '1' : '0' ?>" data-color="1">

					<div class="card-deco"></div>

					<div class="card-body">
						<div class="card-row1">
							<div class="card-icon">
								<?php if ($logo_url): ?><img src="<?= h($logo_url) ?>"
										alt=""><?php else: ?><?= h($ini) ?><?php endif; ?>
							</div>
							<div class="card-meta">
								<div class="card-name"><?= h($title) ?></div>
								<div class="card-folder"><?= h($dir) ?></div>
							</div>
							<button class="pin" type="button" data-action="pin" aria-label="Pin project">
								<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"
									stroke-linecap="round" stroke-linejoin="round">
									<polygon
										points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2" />
								</svg>
							</button>
						</div>

						<?php if ($desc !== ''): ?>
							<div class="card-desc"><?= h($desc) ?></div>
						<?php endif; ?>

						<?php if ($group !== '' || !empty($tags)): ?>
							<div class="card-chips">
								<?php if ($group !== ''): ?><span class="chip chip-accent"><?= h($group) ?></span><?php endif; ?>
								<?php foreach ($tags as $t): ?><span class="chip"><?= h($t) ?></span><?php endforeach; ?>
							</div>
						<?php endif; ?>
					</div>

					<div class="card-swatches">
						<div class="csw on" data-cc="1" style="background:#a3e635" title="Lime"></div>
						<div class="csw" data-cc="2" style="background:#38bdf8" title="Sky"></div>
						<div class="csw" data-cc="3" style="background:#f472b6" title="Pink"></div>
						<div class="csw" data-cc="4" style="background:#fb923c" title="Orange"></div>
						<div class="csw" data-cc="5" style="background:#a78bfa" title="Violet"></div>
						<div class="csw" data-cc="6" style="background:#34d399" title="Emerald"></div>
					</div>

					<div class="card-foot">
						<a class="cta cta-primary" href="<?= h($url) ?>">Open</a>
						<a class="cta" href="<?= h($url) ?>" target="_blank" rel="noreferrer">↗</a>
					</div>
				</div>
			<?php endforeach; ?>
		</div>

		<!-- IFRAME PANEL -->
		<div class="panel" id="dashPanel" style="display:none">
			<div class="panel-head">
				<span class="panel-title">XAMPP Dashboard</span>
				<div style="display:flex;gap:8px;align-items:center">
					<a class="btn btn-ghost" href="<?= h($dash_url) ?>" target="_blank" rel="noreferrer">Open
						separately</a>
					<button class="btn btn-ghost" id="closeDash">Close</button>
				</div>
			</div>
			<div class="panel-body">
				<iframe src="<?= h($dash_url) ?>" loading="lazy" referrerpolicy="no-referrer"></iframe>
			</div>
		</div>

		<!-- MODAL: NEW PROJECT -->
		<dialog id="modalNewProject" class="modal">
			<form action="api_create.php" method="POST" enctype="multipart/form-data">
				<div class="modal-head">
					<span>Create New Project</span>
					<button type="button" class="modal-close" id="btnCloseProject">&times;</button>
				</div>
				<div class="modal-body">
					<div class="form-group">
						<label class="form-label">Folder Name <span style="color:#ef4444">*</span></label>
						<input type="text" name="folder_name" class="form-control" placeholder="my-awesome-project" pattern="[a-zA-Z0-9_\-]+" title="Only letters, numbers, hyphens, and underscores" required>
					</div>
					<div class="form-group">
						<label class="form-label">Title <span style="color:#ef4444">*</span></label>
						<input type="text" name="title" class="form-control" placeholder="Project Title" required>
					</div>
					<div class="form-group">
						<label class="form-label">Description</label>
						<textarea name="description" class="form-control" rows="2" placeholder="Brief description..."></textarea>
					</div>
					<div class="form-group">
						<label class="form-label">Group</label>
						<input type="text" name="group" class="form-control" list="groupList" placeholder="Select or type new...">
						<datalist id="groupList">
							<?php foreach ($all_groups as $g): ?>
								<option value="<?= h($g) ?>"></option>
							<?php endforeach; ?>
						</datalist>
					</div>
					<div class="form-group">
						<label class="form-label">Logo</label>
						<input type="file" name="logo" class="form-control" accept="image/*">
					</div>
					<div class="form-group">
						<label class="form-label">Create From</label>
						<select name="create_from" class="form-control" style="appearance:none">
							<option value="">-- Empty Directory --</option>
							<?php foreach ($projects as $dir): ?>
								<option value="<?= h($dir) ?>"><?= h($dir) ?></option>
							<?php endforeach; ?>
						</select>
					</div>
				</div>
				<div class="modal-foot">
					<button type="button" class="btn btn-ghost" id="btnCancelProject">Cancel</button>
					<button type="submit" class="btn btn-lime">Create Project</button>
				</div>
			</form>
		</dialog>

		<div class="foot">
			<div class="foot-tip">
				<span>Tip: hide folders with</span>
				<code>{ "folder": { "hidden": true } }</code>
				<span>in</span>
				<code>master-hub/projects.json</code>
			</div>
			<a class="foot-credit" href="https://goh.com.ar" target="_blank" rel="noreferrer">
				<div><span>Developed by</span></div>
				<img src="logos/goh1.png" alt="GoH">
			</a>
		</div>
	</div>

	<script>
		(function () {
			const SK = 'mhub:pins:v3';
			const VK = 'mhub:view:v3';
			const CK = 'mhub:colors:v2';
			const GCK = 'mhub:gc:v1';

			const $q = document.getElementById('q');
			const $g = document.getElementById('grid');
			const $cnt = document.getElementById('count');
			const $grp = document.getElementById('groupBy');
			const $tc = document.getElementById('toggleCollapse');
			const $td = document.getElementById('toggleDash');
			const $dp = document.getElementById('dashPanel');
			const $cd = document.getElementById('closeDash');
			const cards = Array.from($g.querySelectorAll('.card'));

			/* storage */
			const rj = (k, fb) => { try { const v = localStorage.getItem(k); return v ? JSON.parse(v) : fb } catch { return fb } };
			const wj = (k, v) => { try { localStorage.setItem(k, JSON.stringify(v)) } catch { } };

			/* pins */
			const ps = rj(SK, { pinned: [], unpinned: [] });
			const pinned = new Set(ps.pinned);
			const unpinned = new Set(ps.unpinned);
			const savePins = () => wj(SK, { pinned: [...pinned].sort(), unpinned: [...unpinned].sort() });
			const ck = c => c.getAttribute('data-key') || '';
			const idf = c => c.getAttribute('data-default-pinned') === '1';
			const ip = c => { const k = ck(c); return (idf(c) && !unpinned.has(k)) || pinned.has(k) };

			const syncPins = () => {
				for (const c of cards) {
					const p = ip(c);
					c.setAttribute('data-pinned', p ? '1' : '0');
					const b = c.querySelector('[data-action="pin"]');
					if (b) { b.setAttribute('aria-pressed', p ? 'true' : 'false'); b.title = p ? 'Unpin' : 'Pin'; }
				}
			};

			/* colors */
			const colorStore = rj(CK, {});
			const setColor = (card, idx) => {
				idx = String(idx);
				card.setAttribute('data-color', idx);
				colorStore[ck(card)] = idx;
				wj(CK, colorStore);
				card.querySelectorAll('.csw').forEach(s => s.classList.toggle('on', s.getAttribute('data-cc') === idx));
			};

			/* global color */
			let gc = String(rj(GCK, 1));
			const syncGSw = () => document.querySelectorAll('[data-gc]').forEach(s => s.classList.toggle('on', s.getAttribute('data-gc') === gc));
			syncGSw();

			document.querySelectorAll('[data-gc]').forEach(sw => {
				sw.addEventListener('click', () => {
					gc = sw.getAttribute('data-gc');
					wj(GCK, gc); syncGSw();
					for (const c of cards) { if (!colorStore[ck(c)]) setColor(c, gc); }
				});
			});

			/* init card colors */
			for (const c of cards) {
				const saved = colorStore[ck(c)];
				const idx = saved || gc;
				c.setAttribute('data-color', idx);
				c.querySelectorAll('.csw').forEach(s => s.classList.toggle('on', s.getAttribute('data-cc') === String(idx)));
			}

			/* view state */
			const vs = rj(VK, { groupBy: 'favorites', collapsed: false });
			const VGBS = ['favorites', 'group', 'alpha', 'none'];
			if (!VGBS.includes(vs.groupBy)) vs.groupBy = 'favorites';
			const sv = () => wj(VK, vs);

			/* filter */
			const matches = c => {
				const t = ($q.value || '').trim().toLowerCase();
				if (!t) return true;
				return ['data-name', 'data-desc', 'data-group', 'data-tags'].some(a => (c.getAttribute(a) || '').includes(t));
			};
			const cmp = (a, b) => {
				const pa = ip(a), pb = ip(b);
				if (pa !== pb) return pa ? -1 : 1;
				return (a.getAttribute('data-sort') || '').localeCompare(b.getAttribute('data-sort') || '', undefined, { numeric: true, sensitivity: 'base' });
			};
			const gk = (c, m) => {
				if (m === 'favorites') return ip(c) ? 'Favorites' : 'Others';
				if (m === 'alpha') { const ch = (c.getAttribute('data-sort') || '').slice(0, 1).toUpperCase(); return ch || '#'; }
				if (m === 'group') { const g = (c.getAttribute('data-group-label') || '').trim(); return g || 'Ungrouped'; }
				return '';
			};

			const clear = el => { while (el.firstChild) el.removeChild(el.firstChild) };

			const mkGrp = (lbl, gc2) => {
				const sec = document.createElement('section'); sec.className = 'grp';
				const hd = document.createElement('div'); hd.className = 'grp-head';
				const lel = document.createElement('div'); lel.className = 'grp-label';
				const dot = document.createElement('span'); dot.className = 'grp-dot';
				const txt = document.createTextNode(lbl);
				const cnt2 = document.createElement('span'); cnt2.className = 'grp-count'; cnt2.textContent = `(${gc2.length})`;
				lel.append(dot, txt, cnt2); hd.append(lel); sec.append(hd);
				const gg = document.createElement('div'); gg.className = 'grp-grid';
				gc2.forEach(c => gg.append(c)); sec.append(gg);
				return sec;
			};

			const render = () => {
				const mode = $grp.value || 'favorites';
				const vis = cards.filter(matches);
				$cnt.textContent = vis.length;
				const sorted = vis.slice().sort(cmp);
				clear($g); $g.removeAttribute('data-collapsed');

				if (mode === 'none') {
					$g.className = 'groups flat';
					sorted.forEach(c => $g.append(c));
					$tc.style.display = 'none';
					return;
				}
				$g.className = 'groups';
				$tc.style.display = '';
				$g.setAttribute('data-collapsed', vs.collapsed ? '1' : '0');
				$tc.textContent = vs.collapsed ? 'Expand' : 'Collapse';

				const map = new Map();
				for (const c of sorted) { const k = gk(c, mode); if (!map.has(k)) map.set(k, []); map.get(k).push(c); }
				let keys = [...map.keys()];
				if (mode === 'favorites') keys = ['Favorites', 'Others'].filter(k => map.has(k));
				else {
					keys.sort((a, b) => a.localeCompare(b, undefined, { numeric: true, sensitivity: 'base' }));
					if (mode === 'group' && map.has('Ungrouped')) keys = [...keys.filter(k => k !== 'Ungrouped'), 'Ungrouped'];
				}
				keys.forEach(k => $g.append(mkGrp(k, map.get(k))));
			};

			/* events */
			$q.addEventListener('input', render);
			$grp.value = vs.groupBy;
			$grp.addEventListener('change', () => { vs.groupBy = $grp.value || 'favorites'; sv(); render(); });
			$tc.addEventListener('click', () => { vs.collapsed = !vs.collapsed; sv(); render(); });

			$g.addEventListener('click', ev => {
				const el = ev.target;
				const pinBtn = el.closest('[data-action="pin"]');
				if (pinBtn) {
					ev.preventDefault();
					const c = pinBtn.closest('.card'); if (!c) return;
					const k = ck(c);
					if (idf(c)) { ip(c) ? unpinned.add(k) : unpinned.delete(k); }
					else { ip(c) ? pinned.delete(k) : pinned.add(k); }
					savePins(); syncPins(); render();
					return;
				}
				const sw = el.closest('[data-cc]');
				if (sw) {
					ev.preventDefault();
					const c = sw.closest('.card'); if (!c) return;
					setColor(c, sw.getAttribute('data-cc'));
				}
			});

			$td.addEventListener('click', () => { $dp.style.display = ''; $dp.scrollIntoView({ behavior: 'smooth', block: 'start' }); });
			$cd.addEventListener('click', () => { $dp.style.display = 'none'; });

			const modalNewProject = document.getElementById('modalNewProject');
			document.getElementById('btnNewProject').addEventListener('click', () => modalNewProject.showModal());
			const closeMdl = () => modalNewProject.close();
			document.getElementById('btnCloseProject').addEventListener('click', closeMdl);
			document.getElementById('btnCancelProject').addEventListener('click', closeMdl);

			syncPins(); render();
		})();
	</script>
</body>

</html>