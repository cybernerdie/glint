<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@yield('page-title', 'Dashboard') — Glint</title>
    <link rel="icon" type="image/svg+xml" href="data:image/svg+xml,{{ rawurlencode(trim(view('glint::partials.logo', ['variant' => 'icon'])->render())) }}">
    <link rel="apple-touch-icon" sizes="180x180" href="{{ route('glint.touch-icon') }}">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Figtree:wght@400;500;600;700&family=JetBrains+Mono:wght@400;500;600;700&display=swap" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.7/dist/chart.umd.min.js"
            integrity="sha256-IGtui7APx7uix+6AykHbPp4FunvgqjWr66nP1TV/XQ4="
            crossorigin="anonymous"></script>
    <script>
        if (window.Chart) {
            Chart.defaults.font.family = "'Figtree', system-ui, sans-serif";
            Chart.defaults.font.size = 11;
            Chart.defaults.color = '#8A8A94';
            Chart.defaults.borderColor = 'rgba(255,255,255,0.05)';
            Chart.defaults.plugins.legend.display = false;
            Chart.defaults.plugins.tooltip.backgroundColor = '#1E1E24';
            Chart.defaults.plugins.tooltip.borderColor = 'rgba(255,255,255,0.1)';
            Chart.defaults.plugins.tooltip.borderWidth = 1;
            Chart.defaults.plugins.tooltip.titleColor = '#F4F4F5';
            Chart.defaults.plugins.tooltip.bodyColor = '#D4D4D8';
            Chart.defaults.plugins.tooltip.titleFont = { family: "'JetBrains Mono', monospace", size: 11, weight: '500' };
            Chart.defaults.plugins.tooltip.bodyFont = { family: "'JetBrains Mono', monospace", size: 11 };
            Chart.defaults.plugins.tooltip.padding = 10;
            Chart.defaults.plugins.tooltip.cornerRadius = 8;
            Chart.defaults.plugins.tooltip.displayColors = false;
        }
    </script>
    <script defer
            src="https://cdn.jsdelivr.net/npm/alpinejs@3.14.9/dist/cdn.min.js"
            integrity="sha256-PtHu0lJIiSHfZeNj1nFd6wTX+Squ255SGZ/fc8seCtM="
            crossorigin="anonymous"></script>
    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

        /* ─── Design tokens — "Ember console" ─────────────────── */
        :root {
            color-scheme: dark;

            /* Surfaces — depth through tone, not shadow */
            --bg:            #0A0A0C;
            --surface:       #111114;
            --surface-2:     #16161A;
            --surface-3:     #1C1C22;

            /* Borders — hairlines do the work shadows used to */
            --border:        #1E1E24;
            --border-strong: #2C2C35;

            /* Accent — ember orange. Reserved for: primary actions,
               active states, cost data, chart data. Never decoration. */
            --accent:        #ED5E2B;
            --accent-hover:  #FF6F3C;
            --accent-bright: #FF8A57;
            --accent-dim:    rgba(237, 94, 43, 0.12);

            /* Text */
            --text-1:        #F4F4F5;
            --text-2:        #B4B4BD;
            --text-3:        #84848E;

            /* Sidebar text */
            --nav-text:       #9C9CA6;
            --nav-text-hover: #D4D4DC;
            --nav-text-active:#FFFFFF;

            /* Status — bright text on dim tints */
            --success:       #34D399;
            --success-bg:    rgba(52, 211, 153, 0.10);
            --success-text:  #34D399;
            --error:         #F87171;
            --error-bg:      rgba(248, 113, 113, 0.10);
            --error-text:    #F87171;
            --warning:       #FBBF24;
            --warning-bg:    rgba(251, 191, 36, 0.10);
            --warning-text:  #FBBF24;
            --info:          #60A5FA;
            --info-bg:       rgba(96, 165, 250, 0.10);
            --info-text:     #60A5FA;

            /* Fonts */
            --font-ui:   'Figtree', system-ui, -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
            --font-mono: 'JetBrains Mono', 'Cascadia Code', 'Fira Code', ui-monospace, 'Courier New', monospace;

            /* Layout */
            --sidebar-w:    236px;
            --topbar-h:     52px;
            --content-w:    1280px;
            --radius:       8px;
            --radius-lg:    12px;
        }

        /* ─── Base ────────────────────────────────────────────── */
        html, body { height: 100%; }

        body {
            background: var(--bg);
            color: var(--text-1);
            font-family: var(--font-ui);
            font-size: 16px;
            line-height: 1.5;
            -webkit-font-smoothing: antialiased;
            -moz-osx-font-smoothing: grayscale;
            display: flex;
        }

        ::selection { background: rgba(237,94,43,0.32); color: #fff; }

        :focus-visible {
            outline: none;
            box-shadow: 0 0 0 2px var(--bg), 0 0 0 4px rgba(237,94,43,0.55);
            border-radius: 6px;
        }

        /* Soft-navigation loading bar — thin ember sweep under the topbar */
        body.glint-loading::after {
            content: '';
            position: fixed;
            top: 0;
            left: var(--sidebar-w);
            right: 0;
            height: 2px;
            z-index: 50;
            background: linear-gradient(90deg, transparent, var(--accent-bright), transparent);
            background-size: 50% 100%;
            background-repeat: no-repeat;
            animation: glintLoad 0.9s ease-in-out infinite;
        }

        @keyframes glintLoad {
            0%   { background-position: -50% 0; }
            100% { background-position: 150% 0; }
        }

        @media (prefers-reduced-motion: reduce) {
            *, *::before, *::after {
                animation-duration: 0.01ms !important;
                animation-iteration-count: 1 !important;
                transition-duration: 0.01ms !important;
            }
        }

        /* ─── Sidebar ─────────────────────────────────────────── */
        .sidebar {
            width: var(--sidebar-w);
            background: var(--bg);
            border-right: 1px solid var(--border);
            display: flex;
            flex-direction: column;
            position: fixed;
            top: 0;
            left: 0;
            height: 100vh;
            overflow-y: auto;
            overflow-x: hidden;
            z-index: 30;
        }

        .sidebar::-webkit-scrollbar { width: 0; }

        /* Brand */
        .sidebar-brand {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 18px 18px 18px;
            flex-shrink: 0;
        }

        .brand-mark {
            width: 30px;
            height: 30px;
            background: linear-gradient(145deg, #FF7E47, #DE4A18);
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
            box-shadow: 0 0 20px rgba(237,94,43,0.35), inset 0 1px 0 rgba(255,255,255,0.28);
        }

        .brand-mark svg {
            width: 16px;
            height: 16px;
            color: #fff;
        }

        .brand-name {
            font-size: 14.5px;
            font-weight: 600;
            color: #FFFFFF;
            letter-spacing: -0.01em;
            line-height: 1.2;
        }

        .brand-tagline {
            font-size: 9.5px;
            font-weight: 500;
            color: var(--text-3);
            letter-spacing: 0.08em;
            text-transform: uppercase;
            margin-top: 2px;
        }

        /* Nav */
        .sidebar-nav {
            padding: 6px 12px 14px;
            flex: 1;
        }

        .nav-group {
            margin-bottom: 22px;
        }

        .nav-group-label {
            font-size: 10px;
            font-weight: 600;
            color: #6B6B75;
            text-transform: uppercase;
            letter-spacing: 0.09em;
            padding: 0 8px;
            margin-bottom: 5px;
        }

        .nav-link {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 7px 9px;
            border-radius: var(--radius);
            color: var(--nav-text);
            text-decoration: none;
            font-size: 13.5px;
            font-weight: 500;
            transition: background 0.12s, color 0.12s;
            margin-bottom: 1px;
            position: relative;
        }

        .nav-link:hover {
            background: #131316;
            color: var(--nav-text-hover);
        }

        .nav-link.is-active {
            background: #1A1A20;
            color: var(--nav-text-active);
        }

        .nav-link.is-active::before {
            content: '';
            position: absolute;
            left: -12px;
            top: 22%;
            height: 56%;
            width: 3px;
            background: var(--accent);
            border-radius: 0 3px 3px 0;
            box-shadow: 0 0 8px rgba(237,94,43,0.5);
        }

        .nav-link svg {
            width: 15px;
            height: 15px;
            flex-shrink: 0;
            opacity: 0.45;
            transition: opacity 0.12s;
        }

        .nav-link:hover svg { opacity: 0.75; }
        .nav-link.is-active svg { opacity: 1; color: var(--accent-bright); }

        /* Mobile nav toggle — hidden on desktop */
        .nav-toggle {
            display: none;
            align-items: center;
            justify-content: center;
            width: 38px;
            height: 38px;
            margin-left: auto;
            border-radius: 8px;
            border: 1px solid var(--border-strong);
            background: transparent;
            color: var(--text-2);
            cursor: pointer;
        }

        .nav-toggle svg { width: 18px; height: 18px; }

        .sidebar.is-open .nav-toggle {
            color: var(--text-1);
            background: var(--surface-2);
        }

        /* Footer */
        .sidebar-footer {
            padding: 12px 18px;
            border-top: 1px solid var(--border);
            flex-shrink: 0;
        }

        .sidebar-version {
            font-size: 11px;
            font-family: var(--font-mono);
            color: #5A5A64;
            letter-spacing: 0.02em;
        }

        /* ─── Main ────────────────────────────────────────────── */
        .main {
            flex: 1;
            display: flex;
            flex-direction: column;
            min-width: 0;
            margin-left: var(--sidebar-w);
        }

        /* Topbar — translucent, blends with the canvas */
        .topbar {
            height: var(--topbar-h);
            background: rgba(10,10,12,0.82);
            backdrop-filter: blur(12px);
            -webkit-backdrop-filter: blur(12px);
            border-bottom: 1px solid var(--border);
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 0 32px;
            flex-shrink: 0;
            position: sticky;
            top: 0;
            z-index: 20;
        }

        .topbar-left {
            display: flex;
            align-items: center;
            gap: 6px;
            font-size: 13px;
        }

        .topbar-sep {
            color: var(--text-3);
            font-size: 12px;
            line-height: 1;
            user-select: none;
            opacity: 0.6;
        }

        .topbar-root {
            color: var(--text-3);
            font-weight: 400;
        }

        .topbar-page {
            color: var(--text-1);
            font-weight: 500;
        }

        .topbar-right {
            display: flex;
            align-items: center;
            gap: 14px;
        }

        .topbar-live {
            display: flex;
            align-items: center;
            gap: 6px;
            font-size: 11px;
            font-weight: 500;
            font-family: var(--font-ui);
            color: var(--success);
            background: var(--success-bg);
            border: 1px solid rgba(52,211,153,0.18);
            border-radius: 9999px;
            padding: 2px 9px 2px 7px;
            letter-spacing: 0.02em;
            cursor: pointer;
            transition: color 0.12s, background 0.12s, border-color 0.12s;
        }

        .topbar-live.is-paused {
            color: var(--text-2);
            background: rgba(255,255,255,0.05);
            border-color: var(--border-strong);
        }

        .topbar-live.is-paused .live-indicator {
            background: var(--text-3);
            box-shadow: none;
        }

        .live-indicator {
            width: 6px;
            height: 6px;
            border-radius: 50%;
            background: var(--success);
            box-shadow: 0 0 6px rgba(52,211,153,0.7);
            flex-shrink: 0;
        }

        .live-indicator.is-pulsing {
            animation: dotPulse 2s ease-in-out infinite;
        }

        @keyframes dotPulse {
            0%,100% { opacity: 1; }
            50%      { opacity: 0.35; }
        }

        .topbar-clock {
            font-family: var(--font-mono);
            font-size: 12px;
            color: var(--text-3);
            letter-spacing: 0.02em;
            font-variant-numeric: tabular-nums;
        }

        /* Content — centered, max-width column */
        .content {
            flex: 1;
            padding: 32px 32px 64px;
            width: 100%;
            max-width: calc(var(--content-w) + 64px);
            margin: 0 auto;
        }

        /* ─── Page head — title left, toolbar right, one row ──── */
        .page-head {
            display: flex;
            align-items: flex-end;
            justify-content: space-between;
            gap: 20px;
            flex-wrap: wrap;
            margin-bottom: 24px;
        }

        .page-title {
            font-size: 22px;
            font-weight: 600;
            color: var(--text-1);
            letter-spacing: -0.02em;
            line-height: 1.2;
        }

        .page-toolbar {
            display: flex;
            align-items: center;
            gap: 8px;
            flex-wrap: wrap;
        }

        /* Legacy aliases (kept for partial reuse) */
        /* ─── Metric strip — one band, hairline dividers ──────── */
        .metric-strip {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: var(--radius-lg);
            overflow: hidden;
            margin-bottom: 24px;
        }

        .metric {
            padding: 24px 26px 22px;
            position: relative;
        }

        .metric + .metric::before {
            content: '';
            position: absolute;
            left: 0;
            top: 16px;
            bottom: 16px;
            width: 1px;
            background: var(--border);
        }

        .metric-label {
            font-size: 10.5px;
            font-weight: 600;
            color: var(--text-3);
            letter-spacing: 0.08em;
            text-transform: uppercase;
            margin-bottom: 12px;
            white-space: nowrap;
        }

        .metric-value {
            font-family: var(--font-mono);
            font-size: 28px;
            font-weight: 700;
            color: var(--text-1);
            letter-spacing: -0.02em;
            line-height: 1;
            font-variant-numeric: tabular-nums;
            white-space: nowrap;
        }

        .metric-unit {
            font-family: var(--font-ui);
            font-size: 14px;
            font-weight: 500;
            color: var(--text-3);
            letter-spacing: 0;
            margin-left: 2px;
        }

        .metric-foot {
            font-size: 11.5px;
            color: var(--text-3);
            margin-top: 10px;
        }

        .metric-value .is-warn  { color: var(--warning); }
        .metric-value .is-bad   { color: var(--error); }

        /* Legacy KPI aliases (mapped onto metric look) */
        /* ─── Panel / generic card ────────────────────────────── */
        .panel {
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: var(--radius-lg);
            overflow: hidden;
            margin-bottom: 20px;
        }

        .panel-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 15px 20px 14px;
            border-bottom: 1px solid var(--border);
        }

        .panel-title {
            font-size: 13.5px;
            font-weight: 550;
            color: var(--text-1);
            letter-spacing: -0.01em;
        }

        .panel-meta {
            font-size: 12px;
            color: var(--text-3);
            font-variant-numeric: tabular-nums;
        }

        .panel-body {
            padding: 20px;
        }

        /* Muted "view all" link — accent stays reserved */
        /* Panel-header destination button — "Costs ↗" */
        .panel-link {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            height: 30px;
            padding: 0 12px;
            border-radius: 7px;
            background: var(--surface-2);
            border: 1px solid var(--border-strong);
            color: var(--text-2);
            font-size: 12px;
            font-weight: 500;
            text-decoration: none;
            letter-spacing: -0.01em;
            transition: color 0.1s, border-color 0.1s, background 0.1s;
            white-space: nowrap;
        }
        .panel-link:hover { color: var(--text-1); background: var(--surface-3); border-color: #3A3A45; }
        .panel-link svg { width: 11px; height: 11px; opacity: 0.7; }

        /* Toolbar attached inside a panel, above the table */
        .panel-toolbar {
            display: flex;
            align-items: center;
            gap: 10px;
            flex-wrap: wrap;
            padding: 14px 16px;
            border-bottom: 1px solid var(--border);
        }

        .panel-toolbar .input { background: var(--surface-2); }
        .panel-toolbar .status-pills { background: var(--surface-2); }

        /* Search input with inline icon */
        .search-wrap {
            position: relative;
            display: inline-flex;
            align-items: center;
        }
        .search-wrap svg {
            position: absolute;
            left: 10px;
            width: 13px;
            height: 13px;
            color: var(--text-3);
            pointer-events: none;
        }
        .search-wrap .input { padding-left: 30px; }

        /* ─── Segmented period control (Nightwatch-style) ─────── */
        .seg {
            display: inline-flex;
            align-items: center;
            background: var(--surface);
            border: 1px solid var(--border-strong);
            border-radius: 10px;
            padding: 3px;
            gap: 2px;
            height: 40px;
        }

        .seg-btn {
            padding: 0 14px;
            height: 32px;
            border-radius: 7px;
            font-size: 13px;
            font-weight: 500;
            font-family: var(--font-ui);
            color: var(--text-2);
            background: transparent;
            border: none;
            cursor: pointer;
            letter-spacing: 0;
            white-space: nowrap;
            line-height: 32px;
            transition: background 0.12s, color 0.12s;
            display: inline-flex;
            align-items: center;
            gap: 5px;
        }

        .seg-btn:hover { color: var(--text-1); background: var(--surface-2); }

        .seg-btn.is-active {
            background: var(--accent);
            color: #FFFFFF;
            font-weight: 600;
            box-shadow: inset 0 1px 0 rgba(255,255,255,0.18), 0 0 10px rgba(237,94,43,0.3);
        }

        .seg-btn svg { width: 14px; height: 14px; }

        /* Custom range popover */
        .seg-pop {
            position: absolute;
            top: calc(100% + 6px);
            right: 0;
            z-index: 40;
            background: var(--surface-3);
            border: 1px solid var(--border-strong);
            border-radius: 10px;
            padding: 12px;
            display: flex;
            align-items: center;
            gap: 8px;
            box-shadow: 0 12px 32px rgba(0,0,0,0.5);
        }

        .seg-anchor { position: relative; display: inline-flex; }

        /* ─── Ranked list with track bars (replaces bar charts) ── */
        .listbar { display: flex; flex-direction: column; }

        .listbar-row {
            display: flex;
            align-items: center;
            gap: 14px;
            padding: 10px 20px;
            text-decoration: none;
            border-bottom: 1px solid var(--border);
            transition: background 0.08s;
        }

        .listbar-row:last-child { border-bottom: none; }
        a.listbar-row:hover { background: var(--surface-2); }

        .listbar-rank {
            font-family: var(--font-mono);
            font-size: 10.5px;
            color: var(--text-3);
            width: 18px;
            flex-shrink: 0;
            text-align: right;
            font-variant-numeric: tabular-nums;
        }

        .listbar-main {
            flex: 1;
            min-width: 0;
        }

        .listbar-name {
            font-family: var(--font-mono);
            font-size: 12px;
            font-weight: 500;
            color: var(--text-1);
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
            margin-bottom: 5px;
        }

        .listbar-sub {
            font-size: 11px;
            color: var(--text-3);
            font-family: var(--font-ui);
            font-weight: 400;
            margin-left: 8px;
        }

        .listbar-track {
            height: 3px;
            background: rgba(255,255,255,0.06);
            border-radius: 2px;
            overflow: hidden;
        }

        .listbar-fill {
            height: 100%;
            background: linear-gradient(90deg, var(--accent), var(--accent-bright));
            border-radius: 2px;
        }

        .listbar-val {
            font-family: var(--font-mono);
            font-size: 12px;
            color: var(--text-2);
            flex-shrink: 0;
            text-align: right;
            min-width: 64px;
            font-variant-numeric: tabular-nums;
        }

        .listbar-val strong { color: var(--text-1); font-weight: 500; }

        /* ─── Tables ──────────────────────────────────────────── */
        table {
            width: 100%;
            border-collapse: collapse;
        }

        th {
            text-align: left;
            padding: 0 20px;
            height: 40px;
            font-size: 10.5px;
            font-weight: 600;
            color: var(--text-3);
            text-transform: uppercase;
            letter-spacing: 0.08em;
            border-bottom: 1px solid var(--border);
            white-space: nowrap;
        }

        td {
            padding: 0 20px;
            height: 50px;
            border-bottom: 1px solid var(--border);
            font-size: 13.5px;
            color: var(--text-1);
            vertical-align: middle;
        }

        th.num, td.num { text-align: right; }
        td.num { font-variant-numeric: tabular-nums; }

        tfoot td {
            background: var(--surface-2);
            font-size: 13px;
        }

        tr:last-child td { border-bottom: none; }

        tbody tr { transition: background 0.08s; }
        tbody tr:hover td { background: var(--surface-2); }
        tbody tr:has(a):hover { cursor: pointer; }
        tbody tr a { transition: color 0.1s; }

        .t-mono {
            font-family: var(--font-mono);
            font-size: 12.5px;
            letter-spacing: 0.01em;
            font-variant-numeric: tabular-nums;
        }

        .t-muted { color: var(--text-2); }
        .t-sub   { font-size: 11px; color: var(--text-3); margin-top: 1px; }
        .t-dim   { color: var(--text-3); }
        .t-name  { font-family: var(--font-mono); font-size: 12.5px; font-weight: 500; }
        .t-cost  { color: var(--accent-bright); font-weight: 500; }

        /* Mono ID chip */
        .id-chip {
            display: inline-flex;
            align-items: center;
            font-family: var(--font-mono);
            font-size: 11px;
            color: var(--text-3);
            background: rgba(255,255,255,0.04);
            border: 1px solid var(--border);
            border-radius: 6px;
            padding: 2px 7px;
            letter-spacing: 0.01em;
            text-decoration: none;
            transition: color 0.1s, border-color 0.1s;
            white-space: nowrap;
        }
        a.id-chip:hover { color: var(--text-1); border-color: var(--border-strong); }

        /* ─── Filter bar ──────────────────────────────────────── */
        .input {
            appearance: none;
            background: var(--surface);
            border: 1px solid var(--border-strong);
            color: var(--text-1);
            border-radius: 8px;
            padding: 6px 12px;
            height: 38px;
            font-family: var(--font-ui);
            font-size: 13px;
            font-weight: 400;
            outline: none;
            transition: border-color 0.12s, box-shadow 0.12s, background 0.12s;
        }

        .input:hover { border-color: #3A3A45; }

        .input:focus {
            border-color: var(--accent);
            box-shadow: 0 0 0 3px var(--accent-dim);
        }

        .input::placeholder { color: var(--text-3); }

        select.input {
            cursor: pointer;
            border-radius: 8px;
            padding: 0 30px 0 13px;
            height: 38px;
            font-size: 13px;
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='10' height='6' viewBox='0 0 10 6'%3E%3Cpath d='M1 1l4 4 4-4' stroke='%2384848E' stroke-width='1.5' fill='none' stroke-linecap='round' stroke-linejoin='round'/%3E%3C/svg%3E");
            background-repeat: no-repeat;
            background-position: right 10px center;
        }

        select.input option { background: var(--surface-3); color: var(--text-1); }

        /* ─── Buttons ─────────────────────────────────────────── */
        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 6px;
            padding: 6px 15px;
            height: 38px;
            border-radius: 8px;
            font-family: var(--font-ui);
            font-size: 13px;
            font-weight: 500;
            letter-spacing: -0.01em;
            cursor: pointer;
            text-decoration: none;
            border: none;
            transition: background 0.1s, border-color 0.1s, color 0.1s, box-shadow 0.1s, transform 0.09s;
            white-space: nowrap;
        }

        .btn:active { transform: scale(0.98); }

        .btn-primary {
            background: var(--accent);
            color: #fff;
            box-shadow: inset 0 1px 0 rgba(255,255,255,0.18), 0 0 16px rgba(237,94,43,0.25);
        }
        .btn-primary:hover { background: var(--accent-hover); }

        .btn-ghost {
            background: transparent;
            color: var(--text-2);
            border: 1px solid var(--border-strong);
        }
        .btn-ghost:hover { background: var(--surface-2); color: var(--text-1); }

        .btn-sm { padding: 5px 12px; font-size: 12.5px; height: 32px; }

        .btn svg { width: 14px; height: 14px; }

        /* Inline link */
        /* ─── Status badges ───────────────────────────────────── */
        .badge {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            padding: 2px 9px;
            border-radius: 9999px;
            font-size: 10.5px;
            font-weight: 500;
            font-family: var(--font-mono);
            white-space: nowrap;
            letter-spacing: 0.05em;
            text-transform: uppercase;
            line-height: 18px;
        }

        .badge-success,
        .badge-error,
        .badge-pending,
        .badge-running { padding-left: 8px; }

        .badge-success::before,
        .badge-error::before,
        .badge-pending::before,
        .badge-running::before {
            content: '';
            width: 5px;
            height: 5px;
            border-radius: 50%;
            background: currentColor;
            flex-shrink: 0;
        }

        .badge-success {
            background: var(--success-bg);
            color: var(--success-text);
        }

        .badge-error {
            background: var(--error-bg);
            color: var(--error-text);
        }

        .badge-pending {
            background: rgba(255,255,255,0.06);
            color: var(--text-2);
        }

        .badge-running {
            background: var(--info-bg);
            color: var(--info-text);
            animation: badgePulse 1.6s ease-in-out infinite;
        }

        .badge-neutral {
            background: rgba(255,255,255,0.06);
            color: var(--text-2);
        }

        @keyframes badgePulse {
            0%, 100% { opacity: 1; }
            50%       { opacity: 0.6; }
        }

        /* ─── Pagination ──────────────────────────────────────── */
        .pagination {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
            padding: 12px 20px;
            border-top: 1px solid var(--border);
        }

        .pagination-count {
            font-size: 12px;
            color: var(--text-3);
            white-space: nowrap;
            flex-shrink: 0;
            font-variant-numeric: tabular-nums;
        }

        .pagination nav ul {
            display: flex;
            gap: 4px;
            list-style: none;
            padding: 0;
            margin: 0;
            flex-wrap: wrap;
        }

        .pagination nav a,
        .pagination nav span {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-width: 32px;
            height: 32px;
            padding: 0 10px;
            gap: 4px;
            border-radius: 7px;
            font-size: 12px;
            font-weight: 500;
            text-decoration: none;
            border: 1px solid transparent;
            color: var(--text-2);
            background: transparent;
            transition: all 0.1s;
            font-variant-numeric: tabular-nums;
        }

        .pagination nav a:hover {
            background: var(--surface-2);
            color: var(--text-1);
            border-color: var(--border-strong);
        }

        .pagination nav .active span {
            background: var(--surface-3);
            color: var(--text-1);
            border-color: var(--border-strong);
            font-weight: 600;
        }

        .pagination nav .disabled span { opacity: 0.3; cursor: not-allowed; }

        /* ─── Detail cards (show pages) ───────────────────────── */
        /* ─── Hero (show pages) ───────────────────────────────── */
        .hero {
            margin-bottom: 20px;
        }

        .hero-title-row {
            display: flex;
            align-items: center;
            gap: 12px;
            flex-wrap: wrap;
        }

        .hero-title {
            font-size: 22px;
            font-weight: 600;
            color: var(--text-1);
            letter-spacing: -0.02em;
            line-height: 1.2;
        }

        .hero-id {
            font-family: var(--font-mono);
            font-size: 12px;
            color: var(--text-3);
            margin-top: 7px;
            letter-spacing: 0.01em;
            word-break: break-all;
        }

        /* Stat strip — compact metric band for show pages */
        .stat-strip {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(130px, 1fr));
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: var(--radius-lg);
            overflow: hidden;
            margin-bottom: 16px;
        }

        .stat {
            padding: 14px 20px 13px;
            position: relative;
        }

        .stat + .stat::before {
            content: '';
            position: absolute;
            left: 0;
            top: 12px;
            bottom: 12px;
            width: 1px;
            background: var(--border);
        }

        .stat-label {
            font-size: 10px;
            font-weight: 600;
            color: var(--text-3);
            letter-spacing: 0.08em;
            text-transform: uppercase;
            margin-bottom: 6px;
            white-space: nowrap;
        }

        .stat-value {
            font-family: var(--font-mono);
            font-size: 14px;
            font-weight: 500;
            color: var(--text-1);
            font-variant-numeric: tabular-nums;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .stat-value.is-cost { color: var(--accent-bright); }

        /* ─── Code blocks ─────────────────────────────────────── */
        .code-card {
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: var(--radius-lg);
            overflow: hidden;
            margin-bottom: 16px;
        }

        .code-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 9px 16px;
            border-bottom: 1px solid var(--border);
        }

        .code-label {
            font-size: 10.5px;
            font-weight: 600;
            color: var(--text-3);
            text-transform: uppercase;
            letter-spacing: 0.08em;
        }

        .code-body {
            padding: 16px;
            font-family: var(--font-mono);
            font-size: 12.5px;
            line-height: 1.7;
            color: #C9C9D1;
            background: #0C0C0F;
            white-space: pre-wrap;
            word-break: break-word;
            overflow-x: auto;
            max-height: 500px;
            overflow-y: auto;
        }

        .code-body::-webkit-scrollbar { width: 5px; height: 5px; }
        .code-body::-webkit-scrollbar-track { background: transparent; }
        .code-body::-webkit-scrollbar-thumb { background: var(--border-strong); border-radius: 3px; }

        /* Error block */
        .error-card {
            background: var(--error-bg);
            border: 1px solid rgba(248,113,113,0.2);
            border-radius: var(--radius-lg);
            overflow: hidden;
            margin-bottom: 16px;
        }

        .error-card-header {
            display: flex;
            align-items: center;
            gap: 7px;
            padding: 10px 16px;
            border-bottom: 1px solid rgba(248,113,113,0.15);
        }

        .error-card-title {
            font-size: 11px;
            font-weight: 600;
            color: var(--error-text);
            text-transform: uppercase;
            letter-spacing: 0.07em;
        }

        .error-body {
            padding: 14px 16px;
            font-family: var(--font-mono);
            font-size: 12.5px;
            color: var(--error-text);
            line-height: 1.65;
            white-space: pre-wrap;
            word-break: break-word;
        }

        /* Tags */
        .tag {
            display: inline-flex;
            align-items: center;
            padding: 3px 9px;
            background: var(--surface-2);
            border: 1px solid var(--border-strong);
            border-radius: 9999px;
            font-family: var(--font-mono);
            font-size: 11.5px;
            color: var(--text-2);
        }

        .tag-key { color: var(--accent-bright); margin-right: 4px; }

        /* ─── Empty state — dashed ring (Nightwatch-style) ────── */
        .empty {
            display: flex;
            flex-direction: column;
            align-items: center;
            padding: 56px 24px 60px;
            text-align: center;
        }

        .empty-icon {
            width: 30px;
            height: 30px;
            color: var(--text-3);
            margin-bottom: 18px;
            padding: 30px;
            border: 1.5px dashed var(--border-strong);
            border-radius: 50%;
            box-sizing: content-box;
        }

        .empty-title {
            font-size: 15px;
            font-weight: 550;
            color: var(--text-1);
            margin-bottom: 6px;
            letter-spacing: -0.01em;
        }

        .empty-sub {
            font-size: 13.5px;
            color: var(--text-2);
            max-width: 320px;
            line-height: 1.55;
        }

        /* ─── Share bar (cost table) ──────────────────────────── */
        .share-cell {
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .share-bar-track {
            flex: 1;
            min-width: 50px;
            height: 3px;
            background: rgba(255,255,255,0.07);
            border-radius: 2px;
            overflow: hidden;
        }

        .share-bar-fill {
            height: 100%;
            background: linear-gradient(90deg, var(--accent), var(--accent-bright));
            border-radius: 2px;
        }

        .share-pct {
            font-family: var(--font-mono);
            font-size: 11px;
            color: var(--text-3);
            width: 38px;
            text-align: right;
            flex-shrink: 0;
            font-variant-numeric: tabular-nums;
        }

        /* ─── Grids ───────────────────────────────────────────── */
        .two-col {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
            align-items: stretch;
            margin-bottom: 20px;
        }

        .two-col > .panel { display: flex; flex-direction: column; margin-bottom: 0; }
        .two-col > .panel > .panel-body { flex: 1; display: flex; flex-direction: column; }

        .main-side {
            display: grid;
            grid-template-columns: minmax(0, 1.7fr) minmax(0, 1fr);
            gap: 20px;
            align-items: stretch;
            margin-bottom: 20px;
        }

        .main-side > .panel { display: flex; flex-direction: column; margin-bottom: 0; }
        .main-side > .panel > .panel-body { flex: 1; display: flex; flex-direction: column; }

        @media (max-width: 1000px) {
            .two-col, .main-side { grid-template-columns: 1fr; }
        }

        .custom-range-sep {
            font-size: 12px;
            color: var(--text-3);
        }

        /* ─── LLM Call cards (trace show) ────────────────────── */
        .llm-call {
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: var(--radius-lg);
            margin-bottom: 8px;
            overflow: hidden;
            transition: border-color 0.15s;
        }

        .llm-call:hover { border-color: var(--border-strong); }

        .llm-call-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 12px 16px;
            cursor: pointer;
            gap: 16px;
            user-select: none;
        }

        .llm-call-header:hover { background: var(--surface-2); }

        .llm-call-left {
            display: flex;
            align-items: center;
            gap: 12px;
            min-width: 0;
        }

        .llm-call-index {
            font-family: var(--font-mono);
            font-size: 11px;
            font-weight: 500;
            color: var(--accent-bright);
            background: var(--accent-dim);
            border: 1px solid rgba(237,94,43,0.22);
            border-radius: 6px;
            padding: 2px 7px;
            flex-shrink: 0;
        }

        .llm-call-model {
            font-size: 13px;
            font-weight: 550;
            color: var(--text-1);
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            font-family: var(--font-mono);
        }

        .llm-call-name {
            font-size: 12px;
            color: var(--text-3);
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .llm-call-right {
            display: flex;
            align-items: center;
            gap: 14px;
            flex-shrink: 0;
        }

        .llm-call-stat {
            font-family: var(--font-mono);
            font-size: 11.5px;
            color: var(--text-2);
            white-space: nowrap;
            font-variant-numeric: tabular-nums;
        }

        .llm-call-chevron {
            width: 14px;
            height: 14px;
            color: var(--text-3);
            transition: transform 0.15s;
            flex-shrink: 0;
        }

        .llm-call-chevron.is-open { transform: rotate(180deg); }

        .llm-stat-label {
            font-size: 10px;
            color: var(--text-3);
            text-transform: uppercase;
            letter-spacing: 0.07em;
        }
        .llm-stat-val {
            font-family: var(--font-mono);
            font-size: 12px;
            color: var(--text-1);
            margin-left: 6px;
            font-variant-numeric: tabular-nums;
        }

        .llm-call-body {
            border-top: 1px solid var(--border);
        }

        .llm-msg-section {
            padding: 0;
        }

        .llm-msg-label {
            font-size: 10px;
            font-weight: 600;
            color: var(--text-3);
            text-transform: uppercase;
            letter-spacing: 0.08em;
            padding: 10px 16px 6px;
            border-bottom: 1px solid var(--border);
            background: var(--surface-2);
        }

        .llm-msg-item {
            border-bottom: 1px solid var(--border);
        }

        .llm-msg-item:last-child { border-bottom: none; }

        .llm-msg-role {
            display: inline-block;
            font-size: 10px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.06em;
            padding: 8px 16px 0;
            color: var(--text-3);
        }

        .llm-msg-role.role-user { color: var(--info-text); }
        .llm-msg-role.role-assistant { color: var(--success-text); }
        .llm-msg-role.role-system { color: var(--warning-text); }

        .llm-msg-content {
            padding: 4px 16px 12px;
            font-family: var(--font-mono);
            font-size: 12px;
            line-height: 1.65;
            color: #C9C9D1;
            white-space: pre-wrap;
            word-break: break-word;
        }

        .llm-completion {
            padding: 12px 16px;
            font-family: var(--font-mono);
            font-size: 12px;
            line-height: 1.65;
            color: #C9C9D1;
            white-space: pre-wrap;
            word-break: break-word;
            background: #0C0C0F;
            border-left: 2px solid rgba(52,211,153,0.4);
        }

        /* ─── Span cards (trace show) ─────────────────────────── */
        .span-card {
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: var(--radius-lg);
            margin-bottom: 8px;
            overflow: hidden;
            transition: border-color 0.15s;
        }

        .span-card:hover { border-color: var(--border-strong); }

        .span-card-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 11px 16px;
            cursor: pointer;
            gap: 16px;
            user-select: none;
        }

        .span-card-header:hover { background: var(--surface-2); }

        .span-card-body {
            border-top: 1px solid var(--border);
        }

        /* Section heading between card groups */
        .section-head {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin: 28px 0 12px;
        }

        .section-title {
            font-size: 13px;
            font-weight: 550;
            color: var(--text-1);
            letter-spacing: -0.01em;
        }

        /* ─── Accessibility ───────────────────────────────────── */
        .sr-only {
            position: absolute;
            width: 1px;
            height: 1px;
            padding: 0;
            margin: -1px;
            overflow: hidden;
            clip: rect(0,0,0,0);
            white-space: nowrap;
            border-width: 0;
        }

        /* ─── Scrollbar ───────────────────────────────────────── */
        ::-webkit-scrollbar { width: 6px; height: 6px; }
        ::-webkit-scrollbar-track { background: transparent; }
        ::-webkit-scrollbar-thumb { background: var(--border-strong); border-radius: 3px; }
        ::-webkit-scrollbar-thumb:hover { background: #3A3A45; }

        /* ─── Table row links ────────────────────────────────── */
        .row-link {
            color: var(--text-1);
            text-decoration: none;
            font-weight: 500;
            transition: color 0.1s;
        }
        .row-link:hover { color: var(--accent-bright); }

        .row-link-dim {
            color: var(--text-3);
            text-decoration: none;
            font-family: var(--font-mono);
            font-size: 12px;
            transition: color 0.1s;
        }
        .row-link-dim:hover { color: var(--accent-bright); }

        .row-link-mono {
            color: var(--text-1);
            text-decoration: none;
            font-family: var(--font-mono);
            font-size: 12.5px;
            font-weight: 500;
            transition: color 0.1s;
        }
        .row-link-mono:hover { color: var(--accent-bright); }

        /* ─── Status segmented control (filter bar) ──────────── */
        .status-pills {
            display: inline-flex;
            background: var(--surface);
            border: 1px solid var(--border-strong);
            border-radius: 10px;
            padding: 3px;
            gap: 2px;
            height: 40px;
            align-items: center;
        }

        .status-pill {
            padding: 0 14px;
            height: 32px;
            border-radius: 7px;
            font-size: 13px;
            font-weight: 500;
            color: var(--text-2);
            background: transparent;
            border: none;
            cursor: pointer;
            font-family: var(--font-ui);
            letter-spacing: -0.01em;
            white-space: nowrap;
            transition: background 0.12s, color 0.12s;
            line-height: 32px;
        }

        .status-pill:hover { color: var(--text-1); background: var(--surface-2); }

        .status-pill.is-active {
            background: var(--accent);
            color: #FFFFFF;
            font-weight: 600;
            box-shadow: inset 0 1px 0 rgba(255,255,255,0.18), 0 0 10px rgba(237,94,43,0.3);
        }

        /* ─── Percentile distribution bar (latency) ───────────── */
        .pct-bar {
            display: flex;
            align-items: center;
            height: 5px;
            min-width: 120px;
            background: rgba(255,255,255,0.06);
            border-radius: 3px;
            overflow: hidden;
        }

        .pct-seg-p50  { height: 100%; background: #4A4A55; }
        .pct-seg-p95  { height: 100%; background: linear-gradient(90deg, var(--accent), var(--accent-bright)); }
        .pct-seg-p99  { height: 100%; background: var(--error); opacity: 0.85; }

        /* ─── Breadcrumb ──────────────────────────────────────── */
        .breadcrumb-link {
            color: var(--text-3);
            text-decoration: none;
            font-weight: 400;
            transition: color 0.1s;
        }

        .breadcrumb-link:hover { color: var(--text-1); }

        .breadcrumb-current {
            color: var(--text-2);
            font-weight: 500;
            font-family: var(--font-mono);
            font-size: 12px;
            max-width: 360px;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        /* ─── Copy button (code blocks) ───────────────────────── */
        .copy-btn {
            display: inline-flex;
            align-items: center;
            gap: 4px;
            padding: 3px 10px;
            height: 26px;
            border-radius: 6px;
            border: 1px solid var(--border-strong);
            background: transparent;
            color: var(--text-3);
            font-size: 11px;
            font-weight: 500;
            font-family: var(--font-ui);
            cursor: pointer;
            letter-spacing: 0.01em;
            transition: all 0.1s;
        }

        .copy-btn:hover { background: var(--surface-2); color: var(--text-1); border-color: #3A3A45; }

        .copy-btn.is-copied {
            color: var(--success-text);
            border-color: rgba(52,211,153,0.3);
            background: var(--success-bg);
        }

        .copy-btn svg { width: 11px; height: 11px; }

        /* ─── Responsive ──────────────────────────────────────── */
        @media (max-width: 900px) {
            :root { --sidebar-w: 200px; }
        }

        @media (max-width: 640px) {
            body { flex-direction: column; }

            .sidebar {
                width: 100%;
                height: auto;
                position: sticky;
                top: 0;
                overflow: visible;
                border-right: none;
                border-bottom: 1px solid var(--border);
                z-index: 40;
            }

            .main { margin-left: 0; }

            body.glint-loading::after { left: 0; }

            .sidebar-brand { padding: 10px 16px; }
            .brand-tagline { display: none; }
            .nav-toggle { display: inline-flex; }

            /* Nav is collapsed behind the hamburger; sticky so it is always reachable */
            .sidebar-nav {
                display: none;
                flex-wrap: wrap;
                gap: 4px;
                padding: 0 10px 12px;
            }

            .sidebar.is-open .sidebar-nav { display: flex; }

            /* Only one sticky bar on mobile — the nav header */
            .topbar { position: static; }

            .nav-group { margin-bottom: 0; display: flex; gap: 4px; }
            .nav-group-label { display: none; }
            .sidebar-footer { display: none; }

            .nav-link { font-size: 12.5px; padding: 7px 10px; margin-bottom: 0; }
            .nav-link::before { display: none; }
            .nav-link svg { display: none; }

            .topbar { padding: 0 16px; }
            .breadcrumb-current { max-width: 140px; }

            .content { padding: 16px 16px 48px; }

            .page-head { align-items: flex-start; flex-direction: column; gap: 12px; }
            .page-title { font-size: 19px; }

            /* Segmented controls: tighter pills so 24H–90D + calendar fit a phone */
            .seg-btn { padding: 0 10px; font-size: 12px; }
            .status-pill { padding: 0 10px; font-size: 12px; }

            /* Custom-range popover wraps within the viewport */
            .seg-pop {
                flex-wrap: wrap;
                max-width: min(340px, calc(100vw - 32px));
            }
            .seg-pop .input { width: 100% !important; flex: 1 1 120px; }
            .seg-pop .btn { flex: 1 1 100%; }

            /* Panel toolbars stack; inputs take the full row */
            .panel-toolbar { gap: 8px; }
            .panel-toolbar .search-wrap { width: 100%; }
            .panel-toolbar .search-wrap .input { width: 100% !important; }
            .panel-toolbar > .input { width: 100% !important; }
            .panel-toolbar .status-pills { margin-left: 0 !important; }

            .metric-strip { grid-template-columns: repeat(2, 1fr); }
            .metric + .metric::before { display: none; }
            .metric { border-top: 1px solid var(--border); padding: 16px 18px 14px; }
            .metric:first-child, .metric:nth-child(2) { border-top: none; }
            .metric-value { font-size: 24px; }

            .stat-strip { grid-template-columns: repeat(2, 1fr); }
            .stat + .stat::before { display: none; }
            .stat { border-top: 1px solid var(--border); }
            .stat:first-child, .stat:nth-child(2) { border-top: none; }

            .hero-title { font-size: 19px; }

            /* Collapsible call/span headers wrap their stat row */
            .llm-call-header, .span-card-header { flex-wrap: wrap; row-gap: 6px; }
            .llm-call-right { flex-wrap: wrap; gap: 10px; }

            .listbar-row { padding: 10px 16px; }
            .listbar-val { min-width: 56px; }

            .panel { overflow-x: auto; }
            table { min-width: 560px; }
            th, td { padding: 0 14px; }

            .empty { padding: 40px 20px 44px; }
            .empty-icon { padding: 22px; }

            .pagination { flex-wrap: wrap; }
        }

        @media (max-width: 420px) {
            .topbar-clock { display: none; }
            .seg-btn { padding: 0 8px; }
            .metric-value { font-size: 21px; }
        }

        /* Form utilities */
        .form-section { margin-bottom: 28px; }
        .form-section-title {
            font-size: 11px;
            font-weight: 600;
            color: var(--text-3);
            text-transform: uppercase;
            letter-spacing: 0.08em;
            margin-bottom: 16px;
            padding-bottom: 10px;
            border-bottom: 1px solid var(--border);
        }
        .form-row {
            display: grid;
            grid-template-columns: 180px 1fr;
            gap: 12px 24px;
            align-items: start;
            margin-bottom: 16px;
        }
        @media (max-width: 640px) {
            .form-row { grid-template-columns: 1fr; gap: 6px; }
        }
        .form-label {
            font-size: 13px;
            font-weight: 500;
            color: var(--text-2);
            padding-top: 9px;
            line-height: 1.4;
        }
        .form-hint {
            font-size: 11.5px;
            color: var(--text-3);
            margin-top: 5px;
            line-height: 1.5;
        }
        .form-error {
            font-size: 11.5px;
            color: var(--error-text);
            margin-top: 5px;
        }
        .form-field { display: flex; flex-direction: column; }
        .input-group { display: flex; align-items: center; gap: 8px; }
        .input-suffix {
            font-size: 12.5px;
            color: var(--text-3);
            font-family: var(--font-mono);
            white-space: nowrap;
        }
        .check-group { display: flex; flex-direction: column; gap: 10px; padding-top: 4px; }
        .check-item { display: flex; align-items: center; gap: 10px; cursor: pointer; }
        .check-item input[type="checkbox"] {
            width: 16px; height: 16px;
            accent-color: var(--accent);
            cursor: pointer;
            flex-shrink: 0;
        }
        .check-label { font-size: 13px; color: var(--text-1); font-weight: 500; }
        .check-sub { font-size: 11.5px; color: var(--text-3); }
        .toggle-row { display: flex; align-items: center; gap: 12px; padding-top: 6px; }
        .toggle-switch {
            position: relative; width: 40px; height: 22px;
            background: var(--border-strong); border-radius: 9999px;
            cursor: pointer; transition: background 0.15s; flex-shrink: 0;
        }
        .toggle-switch.is-on { background: var(--accent); }
        .toggle-knob {
            position: absolute; top: 3px; left: 3px;
            width: 16px; height: 16px; background: #fff;
            border-radius: 50%; transition: transform 0.15s;
            box-shadow: 0 1px 3px rgba(0,0,0,0.3);
        }
        .toggle-switch.is-on .toggle-knob { transform: translateX(18px); }
        .toggle-label { font-size: 13px; color: var(--text-2); }
        .rule-header {
            display: flex;
            align-items: flex-start;
            gap: 24px;
            margin-bottom: 28px;
            padding-bottom: 20px;
            border-bottom: 1px solid var(--border);
        }
        .rule-name-wrap { flex: 1; }
        .rule-name-wrap label {
            display: block;
            font-size: 11px;
            font-weight: 600;
            color: var(--text-3);
            text-transform: uppercase;
            letter-spacing: 0.08em;
            margin-bottom: 6px;
        }
        .rule-status-wrap {
            display: flex;
            flex-direction: column;
            gap: 6px;
            padding-top: 2px;
        }
        .rule-status-wrap > span {
            font-size: 11px;
            font-weight: 600;
            color: var(--text-3);
            letter-spacing: 0.03em;
        }
    </style>
</head>
<body>

    <aside class="sidebar">
        <div class="sidebar-brand">
            <div class="brand-mark">
                @include('glint::partials.logo')
            </div>
            <div>
                <div class="brand-name">Glint</div>
                <div class="brand-tagline">LLM Observability</div>
            </div>
            <button type="button" class="nav-toggle" aria-label="Toggle navigation"
                    onclick="document.querySelector('.sidebar').classList.toggle('is-open')">
                <svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.75">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6.75h16.5M3.75 12h16.5m-16.5 5.25h16.5"/>
                </svg>
            </button>
        </div>

        <nav class="sidebar-nav">
            <div class="nav-group">
                <div class="nav-group-label">Overview</div>
                <a href="{{ route('glint.dashboard') }}"
                   class="nav-link {{ request()->routeIs('glint.dashboard') ? 'is-active' : '' }}">
                    <svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.75">
                        <rect x="3" y="3" width="7" height="7" rx="1.5" stroke-linecap="round"/>
                        <rect x="14" y="3" width="7" height="7" rx="1.5" stroke-linecap="round"/>
                        <rect x="3" y="14" width="7" height="7" rx="1.5" stroke-linecap="round"/>
                        <rect x="14" y="14" width="7" height="7" rx="1.5" stroke-linecap="round"/>
                    </svg>
                    Dashboard
                </a>
            </div>

            <div class="nav-group">
                <div class="nav-group-label">Observability</div>

                <a href="{{ route('glint.traces.index') }}"
                   class="nav-link {{ request()->routeIs('glint.traces*') ? 'is-active' : '' }}">
                    <svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.75">
                        <path stroke-linecap="round" stroke-linejoin="round"
                              d="M7.5 8.25h9m-9 3H12m-9.75 1.51c0 1.6 1.123 2.994 2.707 3.227 1.129.166 2.27.293 3.423.379.35.026.67.21.865.501L12 21l2.755-4.133a1.14 1.14 0 01.865-.501 48.172 48.172 0 003.423-.379c1.584-.233 2.707-1.626 2.707-3.228V6.741c0-1.602-1.123-2.995-2.707-3.228A48.394 48.394 0 0012 3c-2.392 0-4.744.175-7.043.513C3.373 3.746 2.25 5.14 2.25 6.741v6.018z"/>
                    </svg>
                    Traces
                </a>

                <a href="{{ route('glint.generations.index') }}"
                   class="nav-link {{ request()->routeIs('glint.generations*') ? 'is-active' : '' }}">
                    <svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.75">
                        <path stroke-linecap="round" stroke-linejoin="round"
                              d="M9.813 15.904L9 18.75l-.813-2.846a4.5 4.5 0 00-3.09-3.09L2.25 12l2.846-.813a4.5 4.5 0 003.09-3.09L9 5.25l.813 2.846a4.5 4.5 0 003.09 3.09L15.75 12l-2.846.813a4.5 4.5 0 00-3.09 3.09zM18.259 8.715L18 9.75l-.259-1.035a3.375 3.375 0 00-2.455-2.456L14.25 6l1.036-.259a3.375 3.375 0 002.455-2.456L18 2.25l.259 1.035a3.375 3.375 0 002.456 2.456L21.75 6l-1.035.259a3.375 3.375 0 00-2.456 2.456z"/>
                    </svg>
                    Generations
                </a>

                <a href="{{ route('glint.users.index') }}"
                   class="nav-link {{ request()->routeIs('glint.users*') ? 'is-active' : '' }}">
                    <svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.75">
                        <path stroke-linecap="round" stroke-linejoin="round"
                              d="M15.75 6a3.75 3.75 0 11-7.5 0 3.75 3.75 0 017.5 0zM4.501 20.118a7.5 7.5 0 0114.998 0A17.933 17.933 0 0112 21.75c-2.676 0-5.216-.584-7.499-1.632z"/>
                    </svg>
                    Users
                </a>
            </div>

            <div class="nav-group">
                <div class="nav-group-label">Analytics</div>

                <a href="{{ route('glint.costs.index') }}"
                   class="nav-link {{ request()->routeIs('glint.costs*') ? 'is-active' : '' }}">
                    <svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.75">
                        <path stroke-linecap="round" stroke-linejoin="round"
                              d="M12 6v12m-3-2.818l.879.659c1.171.879 3.07.879 4.242 0 1.172-.879 1.172-2.303 0-3.182C13.536 12.219 12.768 12 12 12c-.725 0-1.45-.22-2.003-.659-1.106-.879-1.106-2.303 0-3.182s2.9-.879 4.006 0l.415.33"/>
                    </svg>
                    Costs
                </a>

                <a href="{{ route('glint.analytics.latency') }}"
                   class="nav-link {{ request()->routeIs('glint.analytics*') ? 'is-active' : '' }}">
                    <svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.75">
                        <path stroke-linecap="round" stroke-linejoin="round"
                              d="M3 13.125C3 12.504 3.504 12 4.125 12h2.25c.621 0 1.125.504 1.125 1.125v6.75C7.5 20.496 6.996 21 6.375 21h-2.25A1.125 1.125 0 013 19.875v-6.75zM9.75 8.625c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125v11.25c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 01-1.125-1.125V8.625zM16.5 4.125c0-.621.504-1.125 1.125-1.125h2.25C20.496 3 21 3.504 21 4.125v15.75c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 01-1.125-1.125V4.125z"/>
                    </svg>
                    Latency
                </a>

                <a href="{{ route('glint.alerts.index') }}"
                   class="nav-link {{ request()->routeIs('glint.alerts*') ? 'is-active' : '' }}">
                    <svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.75">
                        <path stroke-linecap="round" stroke-linejoin="round"
                              d="M14.857 17.082a23.848 23.848 0 005.454-1.31A8.967 8.967 0 0118 9.75v-.7V9A6 6 0 006 9v.75a8.967 8.967 0 01-2.312 6.022c1.733.64 3.56 1.085 5.455 1.31m5.714 0a24.255 24.255 0 01-5.714 0m5.714 0a3 3 0 11-5.714 0"/>
                    </svg>
                    Alerts
                </a>
            </div>
        </nav>

        <div class="sidebar-footer">
            <div class="sidebar-version">
                v{{ \Composer\InstalledVersions::getPrettyVersion('cybernerdie/glint') ?? 'dev' }}
            </div>
        </div>
    </aside>

    <div class="main"
         x-data="{ refreshEvery: @yield('refresh-interval', 0), ago: '0s', live: false, paused: localStorage.getItem('glint:paused') === '1' }"
         x-init="
             if (window.__glintRefreshTimer) { clearInterval(window.__glintRefreshTimer); window.__glintRefreshTimer = null; }
             if (window.__glintClockTimer) { clearInterval(window.__glintClockTimer); }
             if (refreshEvery > 0) {
                 live = true;
                 window.__glintRefreshTimer = setInterval(() => {
                     if (!paused && document.visibilityState === 'visible') { window.glintSoftRefresh(); }
                 }, refreshEvery * 1000);
             }
             const loadedAt = Date.now();
             const tick = () => {
                 const s = Math.floor((Date.now() - loadedAt) / 1000);
                 ago = s < 60 ? s + 's' : (s < 3600 ? Math.floor(s / 60) + 'm' : Math.floor(s / 3600) + 'h');
             };
             tick(); window.__glintClockTimer = setInterval(tick, 1000);
         ">

        <header class="topbar">
            <div class="topbar-left">
                <span class="topbar-root">Glint</span>
                @hasSection('breadcrumb')
                    @yield('breadcrumb')
                @else
                    <span class="topbar-sep">/</span>
                    <span class="topbar-page">@yield('page-title', 'Dashboard')</span>
                @endif
            </div>
            <div class="topbar-right">
                <button type="button"
                        x-show="live"
                        class="topbar-live"
                        :class="paused ? 'is-paused' : ''"
                        :title="paused ? 'Live updates paused — click to resume' : 'Live updates on — click to pause'"
                        style="display:none"
                        @click="paused = !paused; localStorage.setItem('glint:paused', paused ? '1' : '0')">
                    <span class="live-indicator" :class="paused ? '' : 'is-pulsing'"></span>
                    <span x-text="paused ? 'Paused' : 'Live'"></span>
                </button>
                <span class="topbar-clock" x-show="live">Updated <span x-text="ago"></span> ago</span>
            </div>
        </header>

        <main class="content">
            @yield('content')
        </main>
    </div>

    <script>
    function glintCopy(btn) {
        var body = btn.closest('.code-card').querySelector('.code-body');
        navigator.clipboard.writeText(body.textContent.trim()).then(function () {
            btn.classList.add('is-copied');
            btn.querySelector('.copy-label').textContent = 'Copied!';
            setTimeout(function () {
                btn.classList.remove('is-copied');
                btn.querySelector('.copy-label').textContent = 'Copy';
            }, 2000);
        }).catch(function () {});
    }

    /*
     * Soft navigation — server-rendered Blade stays the source of truth, but
     * every in-app navigation (links, filter submits, pagination, auto-refresh)
     * fetches the page and swaps the main column in place instead of doing a
     * full browser navigation.
     */
    (function () {
        function runScripts(root) {
            root.querySelectorAll('script').forEach(function (old) {
                var s = document.createElement('script');
                s.textContent = old.textContent;
                old.replaceWith(s);
            });
        }

        // Highlight the sidebar link whose path is the longest prefix of the URL.
        function setActiveNav(url) {
            var links = Array.prototype.slice.call(document.querySelectorAll('.sidebar .nav-link'));
            var best = null;
            links.forEach(function (link) {
                var lp = new URL(link.href, window.location.href).pathname;
                var matches = url.pathname === lp || url.pathname.indexOf(lp + '/') === 0;
                if (matches && (best === null || lp.length > new URL(best.href, window.location.href).pathname.length)) {
                    best = link;
                }
            });
            links.forEach(function (link) { link.classList.toggle('is-active', link === best); });
        }

        function visit(url, opts) {
            opts = opts || {};
            document.body.classList.add('glint-loading');

            fetch(url, { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
                .then(function (r) {
                    if (!r.ok) { throw new Error(); }
                    return r.text();
                })
                .then(function (html) {
                    var doc = new DOMParser().parseFromString(html, 'text/html');
                    var next = doc.querySelector('div.main');
                    var current = document.querySelector('div.main');
                    if (!next || !current) { window.location.href = url; return; }

                    // Layout drift guard: if the page's CSS or this script changed
                    // since this tab loaded, a swap would run stale layout code —
                    // fall back to a full navigation to pick up the new version.
                    var newStyle = doc.querySelector('head style');
                    var curStyle = document.querySelector('head style');
                    var newScript = doc.querySelector('body > script:last-of-type');
                    var curScript = document.querySelector('body > script:last-of-type');
                    if ((newStyle && curStyle && newStyle.textContent !== curStyle.textContent)
                        || (newScript && curScript && newScript.textContent !== curScript.textContent)) {
                        window.location.href = url;
                        return;
                    }

                    // Keep focus and caret across the swap so live filtering
                    // and auto-refresh never interrupt typing.
                    var active = document.activeElement;
                    var focusId = active && active.id && current.contains(active) ? active.id : null;
                    var focusValue = focusId && 'value' in active ? active.value : null;

                    var y = opts.preserveScroll ? window.scrollY : 0;
                    current.replaceWith(next);
                    runScripts(next);
                    document.title = doc.title;
                    if (opts.push !== false) { history.pushState({ glint: true }, '', url); }
                    setActiveNav(new URL(url, window.location.href));
                    var sidebar = document.querySelector('.sidebar');
                    if (sidebar) { sidebar.classList.remove('is-open'); }
                    window.scrollTo(0, y);

                    if (focusId) {
                        var refocus = next.querySelector('#' + CSS.escape(focusId));
                        if (refocus) {
                            if (focusValue !== null) { refocus.value = focusValue; }
                            refocus.focus();
                            try { refocus.setSelectionRange(refocus.value.length, refocus.value.length); } catch (err) {}
                        }
                    }
                })
                .catch(function () { window.location.href = url; })
                .finally(function () { document.body.classList.remove('glint-loading'); });
        }

        // GET filter forms — fetch and swap instead of navigating.
        // Empty fields are dropped so URLs stay clean (?period=7d, not ?period=7d&search=&status=).
        document.addEventListener('submit', function (e) {
            var form = e.target;
            if (!(form instanceof HTMLFormElement) || form.method.toLowerCase() !== 'get') { return; }
            e.preventDefault();
            var params = new URLSearchParams();
            new URLSearchParams(new FormData(form)).forEach(function (value, key) {
                if (value !== '') { params.append(key, value); }
            });
            var qs = params.toString();
            var action = form.getAttribute('action') || window.location.pathname;
            visit(action + (qs ? '?' + qs : ''));
        });

        // All same-origin links — sidebar, rows, pagination, breadcrumbs.
        document.addEventListener('click', function (e) {
            if (e.metaKey || e.ctrlKey || e.shiftKey || e.altKey || e.button !== 0) { return; }
            var a = e.target.closest('a[href]');
            if (!a || a.target || a.hasAttribute('download')) { return; }
            var url = new URL(a.href, window.location.href);
            if (url.origin !== window.location.origin) { return; }
            e.preventDefault();
            visit(url.pathname + url.search);
        });

        // Live filtering — toolbar text inputs auto-submit as you type.
        var liveFilterTimer;
        document.addEventListener('input', function (e) {
            var el = e.target;
            if (!(el instanceof HTMLInputElement) || el.type !== 'text' || !el.form) { return; }
            if (!el.closest('.panel-toolbar') && !el.closest('.page-toolbar')) { return; }
            clearTimeout(liveFilterTimer);
            liveFilterTimer = setTimeout(function () { el.form.requestSubmit(); }, 450);
        });

        window.addEventListener('popstate', function () {
            visit(window.location.pathname + window.location.search, { push: false });
        });

        // Programmatic navigation (clickable table rows).
        window.glintVisit = function (url) { visit(url); };

        // Used by the auto-refresh interval — keeps scroll position, no flicker.
        window.glintSoftRefresh = function () {
            visit(window.location.pathname + window.location.search, { push: false, preserveScroll: true });
        };
    })();
    </script>
</body>
</html>
