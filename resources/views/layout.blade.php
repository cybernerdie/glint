<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@yield('page-title', 'Dashboard') — Glint</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:opsz,wght@14..32,400;14..32,500;14..32,600;14..32,700&family=JetBrains+Mono:wght@400;500&display=swap" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.7/dist/chart.umd.min.js"></script>
    <script defer
            src="https://cdn.jsdelivr.net/npm/alpinejs@3.14.9/dist/cdn.min.js"
            integrity="sha256-PtHu0lJIiSHfZeNj1nFd6wTX+Squ255SGZ/fc8seCtM="
            crossorigin="anonymous"></script>
    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

        /* ─── Design tokens ───────────────────────────────────── */
        :root {
            /* Page & surfaces */
            --page:          #F6F6F4;
            --sidebar-bg:    #131316;
            --surface:       #FFFFFF;
            --surface-2:     #F9F9F7;

            /* Borders */
            --border:        #E8E8EC;
            --border-strong: #D0D0D8;

            /* Accent — warm orange (Fingerprint / Lunary / Anthropic-inspired) */
            --accent:        #E8510A;
            --accent-hover:  #D44509;
            --accent-dim:    rgba(232, 81, 10, 0.08);

            /* Text */
            --text-1:        #0C0C12;
            --text-2:        #64647A;
            --text-3:        #9898AA;

            /* Sidebar text */
            --nav-text:       #A0A0B8;
            --nav-text-hover: #D8D8E8;
            --nav-text-active:#FFFFFF;
            --nav-bg-hover:   rgba(255,255,255,0.05);
            --nav-bg-active:  rgba(255,255,255,0.08);

            /* Status */
            --success:       #16A34A;
            --success-bg:    #DCFCE7;
            --success-text:  #15803D;
            --error:         #DC2626;
            --error-bg:      #FEE2E2;
            --error-text:    #B91C1C;
            --warning:       #D97706;
            --warning-bg:    #FEF3C7;
            --warning-text:  #B45309;
            --info:          #2563EB;
            --info-bg:       #DBEAFE;
            --info-text:     #1D4ED8;

            /* Fonts */
            --font-ui:   'Inter', system-ui, -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
            --font-mono: 'JetBrains Mono', 'Cascadia Code', 'Fira Code', ui-monospace, 'Courier New', monospace;

            /* Layout */
            --sidebar-w:    240px;
            --topbar-h:     52px;
            --radius:       8px;
            --radius-lg:    10px;
            --shadow-sm:    0 1px 2px rgba(0,0,0,0.05);
            --shadow:       0 1px 3px rgba(0,0,0,0.08), 0 1px 2px rgba(0,0,0,0.04);
            --shadow-md:    0 4px 6px rgba(0,0,0,0.06), 0 2px 4px rgba(0,0,0,0.04);
        }

        /* ─── Base ────────────────────────────────────────────── */
        html, body { height: 100%; }

        body {
            background: var(--page);
            color: var(--text-1);
            font-family: var(--font-ui);
            font-size: 16px;
            line-height: 1.5;
            -webkit-font-smoothing: antialiased;
            -moz-osx-font-smoothing: grayscale;
            display: flex;
        }

        /* ─── Sidebar ─────────────────────────────────────────── */
        .sidebar {
            width: var(--sidebar-w);
            background: var(--sidebar-bg);
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
            padding: 20px 18px 16px;
            border-bottom: 1px solid rgba(255,255,255,0.06);
            flex-shrink: 0;
        }

        .brand-mark {
            width: 30px;
            height: 30px;
            background: var(--accent);
            border-radius: 7px;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
        }

        .brand-mark svg {
            width: 16px;
            height: 16px;
            color: #fff;
        }

        .brand-name {
            font-size: 15px;
            font-weight: 700;
            color: #FFFFFF;
            letter-spacing: -0.3px;
        }

        .brand-tagline {
            font-size: 10px;
            font-weight: 500;
            color: var(--nav-text);
            letter-spacing: 0.04em;
            text-transform: uppercase;
            margin-top: 1px;
        }

        /* Nav */
        .sidebar-nav {
            padding: 14px 10px;
            flex: 1;
        }

        .nav-group {
            margin-bottom: 18px;
        }

        .nav-group-label {
            font-size: 10px;
            font-weight: 600;
            color: rgba(255,255,255,0.22);
            text-transform: uppercase;
            letter-spacing: 0.08em;
            padding: 0 8px;
            margin-bottom: 3px;
        }

        .nav-link {
            display: flex;
            align-items: center;
            gap: 9px;
            padding: 7px 8px;
            border-radius: var(--radius);
            color: var(--nav-text);
            text-decoration: none;
            font-size: 13.5px;
            font-weight: 500;
            letter-spacing: -0.01em;
            transition: background 0.1s, color 0.1s;
            margin-bottom: 1px;
            position: relative;
        }

        .nav-link:hover {
            background: var(--nav-bg-hover);
            color: var(--nav-text-hover);
        }

        .nav-link.is-active {
            background: var(--nav-bg-active);
            color: var(--nav-text-active);
        }

        .nav-link.is-active::before {
            content: '';
            position: absolute;
            left: -10px;
            top: 22%;
            height: 56%;
            width: 3px;
            background: var(--accent);
            border-radius: 0 3px 3px 0;
        }

        .nav-link svg {
            width: 15px;
            height: 15px;
            flex-shrink: 0;
            opacity: 0.5;
            transition: opacity 0.1s;
        }

        .nav-link:hover svg { opacity: 0.8; }
        .nav-link.is-active svg { opacity: 1; }

        /* Footer */
        .sidebar-footer {
            padding: 12px 18px;
            border-top: 1px solid rgba(255,255,255,0.06);
            flex-shrink: 0;
        }

        .sidebar-version {
            font-size: 11px;
            font-family: var(--font-mono);
            color: rgba(255,255,255,0.18);
        }

        /* ─── Main ────────────────────────────────────────────── */
        .main {
            flex: 1;
            display: flex;
            flex-direction: column;
            min-width: 0;
            margin-left: var(--sidebar-w);
        }

        /* Topbar */
        .topbar {
            height: var(--topbar-h);
            background: var(--surface);
            border-bottom: 1px solid var(--border);
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 0 24px;
            flex-shrink: 0;
            position: sticky;
            top: 0;
            z-index: 20;
            box-shadow: 0 1px 0 var(--border);
        }

        .topbar-left {
            display: flex;
            align-items: center;
            gap: 5px;
            font-size: 13px;
        }

        .topbar-sep {
            color: var(--border-strong);
            font-size: 12px;
            line-height: 1;
            user-select: none;
        }

        .topbar-root {
            color: var(--text-3);
            font-weight: 400;
        }

        .topbar-page {
            color: var(--text-1);
            font-weight: 600;
        }

        .topbar-right {
            display: flex;
            align-items: center;
            gap: 14px;
        }

        .topbar-live {
            display: flex;
            align-items: center;
            gap: 5px;
            font-size: 12px;
            color: var(--text-3);
        }

        .live-indicator {
            width: 6px;
            height: 6px;
            border-radius: 50%;
            background: var(--success);
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
        }

        /* Content */
        .content {
            flex: 1;
            padding: 28px 28px 40px;
        }

        /* ─── Page header ─────────────────────────────────────── */
        .page-header {
            margin-bottom: 24px;
        }

        .page-header-row {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 16px;
        }

        .page-title {
            font-size: 20px;
            font-weight: 700;
            color: var(--text-1);
            letter-spacing: -0.5px;
            line-height: 1.25;
        }

        .page-desc {
            font-size: 13px;
            color: var(--text-3);
            margin-top: 4px;
        }

        /* ─── KPI / Stat cards ────────────────────────────────── */
        .kpi-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(160px, 1fr));
            gap: 12px;
            margin-bottom: 28px;
        }

        .kpi-card {
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: var(--radius-lg);
            padding: 18px 20px 16px;
            box-shadow: 0 1px 2px rgba(0,0,0,0.04), 0 1px 4px rgba(0,0,0,0.04);
            position: relative;
            overflow: hidden;
        }

        .kpi-card::before {
            content: '';
            position: absolute;
            top: 0; left: 0; right: 0;
            height: 2px;
            background: linear-gradient(90deg, var(--accent) 0%, transparent 60%);
            opacity: 0;
            transition: opacity 0.18s;
        }

        .kpi-card:hover::before { opacity: 1; }

        .kpi-label {
            font-size: 11px;
            font-weight: 600;
            color: var(--text-3);
            letter-spacing: 0.05em;
            text-transform: uppercase;
            margin-bottom: 10px;
        }

        .kpi-value {
            font-size: 26px;
            font-weight: 700;
            color: var(--text-1);
            font-family: var(--font-mono);
            letter-spacing: -0.03em;
            line-height: 1;
        }

        .kpi-value-sm {
            font-size: 20px;
        }

        .kpi-value-cost {
            font-family: var(--font-mono);
            color: var(--accent);
        }

        .kpi-unit {
            font-size: 13px;
            font-weight: 400;
            color: var(--text-2);
            letter-spacing: 0;
            font-family: var(--font-ui);
        }

        .kpi-footer {
            font-size: 11.5px;
            color: var(--text-3);
            margin-top: 8px;
        }

        /* ─── Panel / generic card ────────────────────────────── */
        .panel {
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: var(--radius-lg);
            box-shadow: 0 1px 2px rgba(0,0,0,0.04), 0 1px 4px rgba(0,0,0,0.04);
            overflow: hidden;
            margin-bottom: 20px;
        }

        .panel-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 13px 18px 12px;
            border-bottom: 1px solid var(--border);
            background: var(--surface-2);
        }

        .panel-title {
            font-size: 14px;
            font-weight: 600;
            color: var(--text-1);
            letter-spacing: -0.01em;
        }

        .panel-meta {
            font-size: 12px;
            color: var(--text-3);
        }

        .panel-body {
            padding: 18px;
        }

        /* ─── Tables ──────────────────────────────────────────── */
        table {
            width: 100%;
            border-collapse: collapse;
        }

        th {
            text-align: left;
            padding: 0 16px;
            height: 36px;
            font-size: 11px;
            font-weight: 600;
            color: var(--text-3);
            text-transform: uppercase;
            letter-spacing: 0.06em;
            border-bottom: 1px solid var(--border);
            background: var(--surface-2);
            white-space: nowrap;
        }

        td {
            padding: 0 16px;
            height: 46px;
            border-bottom: 1px solid var(--border);
            font-size: 13.5px;
            color: var(--text-1);
            vertical-align: middle;
        }

        tfoot td {
            background: var(--surface-2);
            font-size: 13px;
        }

        tr:last-child td { border-bottom: none; }

        tbody tr { transition: background 0.08s; }
        tbody tr:hover td { background: #F7F7F5; }
        tbody tr:hover td:first-child { box-shadow: inset 3px 0 0 var(--accent); }
        tbody tr a { transition: color 0.1s; }
        tbody tr:has(a):hover { cursor: pointer; }

        .t-mono {
            font-family: var(--font-mono);
            font-size: 12px;
            letter-spacing: 0.01em;
        }

        .t-muted { color: var(--text-2); }
        .t-dim   { color: var(--text-3); }
        .t-name  { font-family: var(--font-mono); font-size: 12.5px; font-weight: 500; }

        /* ─── Filter bar ──────────────────────────────────────── */
        .filter-bar {
            display: flex;
            flex-direction: column;
            gap: 6px;
            margin-bottom: 16px;
        }

        .filter-row {
            display: flex;
            align-items: center;
            gap: 8px;
            flex-wrap: wrap;
        }

        .filter-row-end {
            margin-left: auto;
            flex-shrink: 0;
            display: flex;
            align-items: center;
        }

        .filter-chips {
            display: flex;
            align-items: center;
            flex-wrap: wrap;
            gap: 6px;
        }

        .input {
            appearance: none;
            background: var(--surface);
            border: 1px solid var(--border);
            color: var(--text-1);
            border-radius: 8px;
            padding: 6px 12px;
            height: 34px;
            font-family: var(--font-ui);
            font-size: 13px;
            font-weight: 400;
            outline: none;
            transition: border-color 0.12s, box-shadow 0.12s;
            box-shadow: var(--shadow-sm);
        }

        .input:focus {
            border-color: var(--accent);
            box-shadow: 0 0 0 3px var(--accent-dim);
        }

        .input::placeholder { color: var(--text-3); }

        select.input {
            cursor: pointer;
            border-radius: 8px;
            padding: 0 30px 0 14px;
            height: 34px;
            font-size: 12.5px;
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='10' height='6' viewBox='0 0 10 6'%3E%3Cpath d='M1 1l4 4 4-4' stroke='%239898AA' stroke-width='1.5' fill='none' stroke-linecap='round' stroke-linejoin='round'/%3E%3C/svg%3E");
            background-repeat: no-repeat;
            background-position: right 10px center;
        }

        select.input:focus {
            border-color: var(--accent);
            box-shadow: 0 0 0 3px var(--accent-dim);
        }

        /* ─── Buttons ─────────────────────────────────────────── */
        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 6px;
            padding: 6px 14px;
            height: 34px;
            border-radius: 8px;
            font-family: var(--font-ui);
            font-size: 13px;
            font-weight: 500;
            letter-spacing: -0.01em;
            cursor: pointer;
            text-decoration: none;
            border: none;
            transition: background 0.1s, border-color 0.1s, color 0.1s, box-shadow 0.1s;
            white-space: nowrap;
        }

        .btn-primary {
            background: var(--accent);
            color: #fff;
            box-shadow: 0 1px 2px rgba(232,81,10,0.3);
        }
        .btn-primary:hover { background: var(--accent-hover); }

        .btn-secondary {
            background: var(--surface);
            color: var(--text-1);
            border: 1px solid var(--border);
            box-shadow: var(--shadow-sm);
        }
        .btn-secondary:hover { background: var(--surface-2); border-color: var(--border-strong); }

        .btn-ghost {
            background: transparent;
            color: var(--text-2);
            border: 1px solid var(--border);
        }
        .btn-ghost:hover { background: var(--surface-2); color: var(--text-1); }

        .btn-sm { padding: 5px 10px; font-size: 12px; }

        .btn svg { width: 14px; height: 14px; }

        /* Inline link */
        .text-link {
            color: var(--accent);
            text-decoration: none;
            font-weight: 500;
            font-size: 12.5px;
            transition: color 0.1s;
        }
        .text-link:hover { color: var(--accent-hover); }

        /* ─── Status badges ───────────────────────────────────── */
        .badge {
            display: inline-flex;
            align-items: center;
            padding: 2px 7px;
            border-radius: 4px;
            font-size: 10.5px;
            font-weight: 600;
            font-family: var(--font-mono);
            white-space: nowrap;
            letter-spacing: 0.07em;
            text-transform: uppercase;
            line-height: 18px;
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
            background: #EDEDF2;
            color: #5A5A72;
        }

        .badge-running {
            background: var(--info-bg);
            color: var(--info-text);
            animation: badgePulse 1.6s ease-in-out infinite;
        }

        .badge-neutral {
            background: #EEEDF6;
            color: #5A5A7A;
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
            padding: 12px 18px;
            border-top: 1px solid var(--border);
        }

        .pagination-count {
            font-size: 12px;
            color: var(--text-3);
            white-space: nowrap;
            flex-shrink: 0;
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
            min-width: 30px;
            height: 30px;
            padding: 0 8px;
            gap: 4px;
            border-radius: 6px;
            font-size: 12.5px;
            font-weight: 500;
            text-decoration: none;
            border: 1px solid var(--border);
            color: var(--text-2);
            background: var(--surface);
            transition: all 0.1s;
            box-shadow: var(--shadow-sm);
        }

        .pagination nav a:hover {
            background: var(--surface-2);
            color: var(--text-1);
            border-color: var(--border-strong);
        }

        .pagination nav .active span {
            background: var(--accent);
            color: #fff;
            border-color: var(--accent);
            font-weight: 600;
        }

        .pagination nav .disabled span { opacity: 0.35; cursor: not-allowed; box-shadow: none; }

        /* ─── Detail cards (show pages) ───────────────────────── */
        .info-card {
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: var(--radius-lg);
            box-shadow: 0 1px 2px rgba(0,0,0,0.04), 0 1px 4px rgba(0,0,0,0.04);
            overflow: hidden;
            margin-bottom: 16px;
        }

        .info-card-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 12px 18px;
            border-bottom: 1px solid var(--border);
            background: var(--surface-2);
        }

        .info-card-title {
            font-size: 12px;
            font-weight: 600;
            color: var(--text-2);
            text-transform: uppercase;
            letter-spacing: 0.06em;
        }

        .info-card-body {
            padding: 18px;
        }

        .field-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(180px, 1fr));
            gap: 18px 24px;
        }

        .field label {
            display: block;
            font-size: 11px;
            font-weight: 600;
            color: var(--text-3);
            text-transform: uppercase;
            letter-spacing: 0.07em;
            margin-bottom: 4px;
        }

        .field-val {
            font-size: 14px;
            color: var(--text-1);
            word-break: break-all;
            line-height: 1.4;
        }

        .field-val-mono {
            font-family: var(--font-mono);
            font-size: 12.5px;
            color: var(--text-1);
            word-break: break-all;
        }

        .field-val-muted {
            font-size: 14px;
            color: var(--text-2);
        }

        /* Divider inside info-card-body */
        .body-divider {
            height: 1px;
            background: var(--border);
            margin: 16px 0;
        }

        /* ─── Code blocks ─────────────────────────────────────── */
        .code-card {
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: var(--radius-lg);
            overflow: hidden;
            margin-bottom: 16px;
            box-shadow: var(--shadow-sm);
        }

        .code-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 9px 16px;
            border-bottom: 1px solid var(--border);
            background: var(--surface-2);
        }

        .code-label {
            font-size: 11px;
            font-weight: 600;
            color: var(--text-3);
            text-transform: uppercase;
            letter-spacing: 0.07em;
        }

        .code-body {
            padding: 16px;
            font-family: var(--font-mono);
            font-size: 12.5px;
            line-height: 1.7;
            color: #2A2A3C;
            background: #F4F4F2;
            white-space: pre-wrap;
            word-break: break-word;
            overflow-x: auto;
            max-height: 500px;
            overflow-y: auto;
            border-left: 2px solid var(--border-strong);
        }

        .code-body::-webkit-scrollbar { width: 5px; height: 5px; }
        .code-body::-webkit-scrollbar-track { background: transparent; }
        .code-body::-webkit-scrollbar-thumb { background: var(--border-strong); border-radius: 3px; }

        /* Error block */
        .error-card {
            background: var(--error-bg);
            border: 1px solid rgba(185,28,28,0.15);
            border-radius: var(--radius-lg);
            overflow: hidden;
            margin-bottom: 16px;
        }

        .error-card-header {
            display: flex;
            align-items: center;
            gap: 7px;
            padding: 10px 16px;
            border-bottom: 1px solid rgba(185,28,28,0.15);
            background: var(--error-bg);
        }

        .error-card-title {
            font-size: 11.5px;
            font-weight: 600;
            color: var(--error-text);
            text-transform: uppercase;
            letter-spacing: 0.06em;
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
            border: 1px solid var(--border);
            border-radius: 9999px;
            font-family: var(--font-mono);
            font-size: 11.5px;
            color: var(--text-2);
        }

        .tag-key { color: var(--accent); margin-right: 4px; }

        /* ─── Empty state ─────────────────────────────────────── */
        .empty {
            display: flex;
            flex-direction: column;
            align-items: center;
            padding: 60px 24px;
            text-align: center;
        }

        .empty-icon {
            width: 36px;
            height: 36px;
            color: var(--text-3);
            margin-bottom: 14px;
            padding: 8px;
            background: var(--surface-2);
            border: 1px solid var(--border);
            border-radius: 10px;
            box-sizing: content-box;
        }

        .empty-title {
            font-size: 14.5px;
            font-weight: 600;
            color: var(--text-2);
            margin-bottom: 5px;
        }

        .empty-sub {
            font-size: 13px;
            color: var(--text-3);
            max-width: 280px;
        }

        /* ─── Bar chart (cost by provider) ───────────────────── */
        .bar-chart {
            display: flex;
            flex-direction: column;
            gap: 13px;
        }

        .bar-row {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .bar-label {
            width: 140px;
            font-size: 12px;
            font-weight: 500;
            color: var(--text-2);
            text-align: right;
            flex-shrink: 0;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
            font-family: var(--font-mono);
        }

        .bar-track {
            flex: 1;
            height: 7px;
            background: var(--page);
            border-radius: 4px;
            overflow: hidden;
        }

        .bar-fill {
            height: 100%;
            background: var(--accent);
            border-radius: 4px;
            opacity: 0.75;
            transition: width 0.6s cubic-bezier(.4,0,.2,1);
        }

        .bar-value {
            width: 80px;
            font-family: var(--font-mono);
            font-size: 12px;
            color: var(--text-2);
            text-align: left;
            flex-shrink: 0;
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
            height: 4px;
            background: var(--border);
            border-radius: 2px;
            overflow: hidden;
        }

        .share-bar-fill {
            height: 100%;
            background: var(--accent);
            border-radius: 2px;
            opacity: 0.65;
        }

        .share-pct {
            font-family: var(--font-mono);
            font-size: 11px;
            color: var(--text-3);
            width: 34px;
            text-align: right;
            flex-shrink: 0;
        }

        /* ─── Two-column grid ────────────────────────────────────── */
        .two-col {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
            align-items: stretch;
            margin-bottom: 20px;
        }

        /* Panels inside two-col use column flex so panel-body can fill height */
        .two-col > .panel {
            display: flex;
            flex-direction: column;
        }

        .two-col > .panel > .panel-body {
            flex: 1;
            display: flex;
            flex-direction: column;
        }

        /* Activity chart fills the panel body height instead of fixed 72px */
        .two-col .activity-chart {
            flex: 1;
            height: auto;
            min-height: 72px;
        }

        @media (max-width: 960px) {
            .two-col { grid-template-columns: 1fr; }
        }

        /* ─── Dashboard grid ──────────────────────────────────── */
        .dash-grid {
            display: grid;
            grid-template-columns: 1fr;
            gap: 20px;
            align-items: start;
        }

        @media (min-width: 1100px) {
            .dash-grid {
                grid-template-columns: 1fr 300px;
            }
        }

        /* ─── Date range filter ──────────────────────────────── */
        .date-range-row {
            display: flex;
            align-items: center;
            gap: 8px;
            flex-wrap: wrap;
        }

        .custom-range {
            display: flex;
            align-items: center;
            gap: 8px;
            flex-wrap: wrap;
        }

        .custom-range-sep {
            font-size: 12px;
            color: var(--text-3);
        }

        /* ─── Activity chart ─────────────────────────────────── */
        .activity-chart {
            display: flex;
            align-items: flex-end;
            gap: 3px;
            height: 72px;
        }

        .activity-col {
            flex: 1;
            min-width: 0;
            display: flex;
            flex-direction: column;
            align-items: center;
            height: 100%;
            gap: 0;
        }

        .activity-bar-count {
            font-size: 9px;
            font-weight: 700;
            color: var(--text-2);
            text-align: center;
            min-height: 14px;
            flex-shrink: 0;
            display: flex;
            align-items: flex-end;
            justify-content: center;
            padding-bottom: 2px;
            line-height: 1;
        }

        .activity-bar-wrap {
            flex: 1;
            width: 100%;
            display: flex;
            align-items: flex-end;
            min-height: 0;
        }

        .activity-bar {
            width: 100%;
            background: var(--accent);
            border-radius: 3px 3px 0 0;
            opacity: 0.7;
            min-height: 2px;
            transition: opacity 0.1s;
        }

        .activity-bar:hover { opacity: 1; }

        .activity-label {
            font-size: 9.5px;
            color: var(--text-3);
            margin-top: 5px;
            white-space: nowrap;
            letter-spacing: 0.02em;
        }

        /* ─── LLM Call cards (trace show) ────────────────────── */
        .llm-call {
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: var(--radius-lg);
            margin-bottom: 8px;
            overflow: hidden;
            box-shadow: var(--shadow-sm);
        }

        .llm-call-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 12px 16px;
            cursor: pointer;
            gap: 16px;
            user-select: none;
        }

        .llm-call-header:hover { background: #FAFAF8; }

        .llm-call-left {
            display: flex;
            align-items: center;
            gap: 12px;
            min-width: 0;
        }

        .llm-call-index {
            font-family: var(--font-mono);
            font-size: 11px;
            font-weight: 600;
            color: var(--text-3);
            background: var(--page);
            border: 1px solid var(--border);
            border-radius: 5px;
            padding: 2px 7px;
            flex-shrink: 0;
        }

        .llm-call-model {
            font-size: 13.5px;
            font-weight: 600;
            color: var(--text-1);
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
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
            font-size: 10.5px;
            color: var(--text-3);
            text-transform: uppercase;
            letter-spacing: 0.06em;
        }
        .llm-stat-val {
            font-family: var(--font-mono);
            font-size: 12px;
            color: var(--text-1);
            margin-left: 6px;
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
            letter-spacing: 0.07em;
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
            letter-spacing: 0.05em;
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
            color: #2A2A3C;
            white-space: pre-wrap;
            word-break: break-word;
        }

        .llm-completion {
            padding: 12px 16px;
            font-family: var(--font-mono);
            font-size: 12px;
            line-height: 1.65;
            color: #2A2A3C;
            white-space: pre-wrap;
            word-break: break-word;
            background: #F4F4F2;
            border-left: 2px solid var(--border-strong);
        }

        /* ─── Span cards (trace show) ─────────────────────────── */
        .span-card {
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: var(--radius-lg);
            margin-bottom: 8px;
            overflow: hidden;
            box-shadow: var(--shadow-sm);
        }

        .span-card-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 11px 16px;
            cursor: pointer;
            gap: 16px;
            user-select: none;
        }

        .span-card-header:hover { background: #FAFAF8; }

        .span-card-body {
            border-top: 1px solid var(--border);
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
        ::-webkit-scrollbar { width: 5px; height: 5px; }
        ::-webkit-scrollbar-track { background: transparent; }
        ::-webkit-scrollbar-thumb { background: var(--border-strong); border-radius: 3px; }

        /* ─── Table row links ────────────────────────────────── */
        .row-link {
            color: var(--text-1);
            text-decoration: none;
            font-weight: 500;
            transition: color 0.1s;
        }
        .row-link:hover { color: var(--accent); }

        .row-link-dim {
            color: var(--text-3);
            text-decoration: none;
            font-family: var(--font-mono);
            font-size: 12px;
            transition: color 0.1s;
        }
        .row-link-dim:hover { color: var(--accent); }

        .row-link-mono {
            color: var(--text-1);
            text-decoration: none;
            font-family: var(--font-mono);
            font-size: 12.5px;
            font-weight: 500;
            transition: color 0.1s;
        }
        .row-link-mono:hover { color: var(--accent); }

        /* ─── Status segmented control (filter bar) ──────────── */
        .status-pills {
            display: inline-flex;
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: 8px;
            padding: 2px;
            gap: 1px;
            box-shadow: var(--shadow-sm);
            height: 34px;
            align-items: center;
        }

        .status-pill {
            padding: 0 12px;
            height: 28px;
            border-radius: 6px;
            font-size: 12.5px;
            font-weight: 500;
            color: var(--text-2);
            background: transparent;
            border: none;
            cursor: pointer;
            font-family: var(--font-ui);
            letter-spacing: -0.01em;
            white-space: nowrap;
            transition: background 0.12s, color 0.12s;
            line-height: 28px;
        }

        .status-pill:hover { color: var(--text-1); background: var(--surface-2); }

        .status-pill.is-active {
            background: var(--accent);
            color: #fff;
            font-weight: 600;
            box-shadow: 0 1px 2px rgba(232,81,10,0.3);
        }

        /* ─── Active filter chips ─────────────────────────────── */
        .filter-chip {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            padding: 0 10px;
            height: 28px;
            border-radius: 9999px;
            background: var(--accent-dim);
            border: 1px solid rgba(232,81,10,0.2);
            color: var(--accent);
            font-size: 12px;
            font-weight: 500;
            text-decoration: none;
            white-space: nowrap;
            transition: background 0.1s;
        }

        .filter-chip:hover { background: rgba(232,81,10,0.14); }
        .filter-chip-muted {
            background: rgba(0,0,0,0.04);
            border-color: var(--border);
            color: var(--text-2);
        }
        .filter-chip-muted:hover { background: rgba(0,0,0,0.08); }

        .filter-chip-x {
            font-size: 15px;
            line-height: 1;
            opacity: 0.55;
            margin-left: 1px;
        }

        /* ─── Breadcrumb ──────────────────────────────────────── */
        .breadcrumb {
            display: flex;
            align-items: center;
            gap: 6px;
            font-size: 13px;
            margin-bottom: 16px;
            flex-wrap: wrap;
        }

        .breadcrumb-link {
            color: var(--text-3);
            text-decoration: none;
            font-weight: 400;
            transition: color 0.1s;
        }

        .breadcrumb-link:hover { color: var(--text-1); }

        .breadcrumb-sep {
            color: var(--border-strong);
            font-size: 12px;
            user-select: none;
        }

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
            padding: 3px 9px;
            height: 24px;
            border-radius: 5px;
            border: 1px solid var(--border);
            background: var(--surface);
            color: var(--text-3);
            font-size: 11px;
            font-weight: 500;
            font-family: var(--font-ui);
            cursor: pointer;
            letter-spacing: 0.01em;
            transition: all 0.1s;
        }

        .copy-btn:hover { background: var(--surface-2); color: var(--text-1); border-color: var(--border-strong); }

        .copy-btn.is-copied {
            color: var(--success-text);
            border-color: rgba(21,128,61,0.22);
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
                position: static;
                border-bottom: 1px solid rgba(255,255,255,0.06);
            }

            .main { margin-left: 0; }

            .sidebar-nav {
                display: flex;
                flex-wrap: wrap;
                gap: 4px;
                padding: 8px;
            }

            .nav-group { margin-bottom: 0; }
            .nav-group-label { display: none; }
            .sidebar-footer { display: none; }

            .nav-link { font-size: 12px; padding: 6px 8px; }
            .nav-link::before { display: none; }

            .content { padding: 16px; }
            .kpi-grid { grid-template-columns: repeat(2, 1fr); }
            .panel { overflow-x: auto; }
            table { min-width: 560px; }
        }
    </style>
</head>
<body>

    {{-- Sidebar --}}
    <aside class="sidebar">
        <div class="sidebar-brand">
            <div class="brand-mark">
                <svg viewBox="0 0 16 16" fill="white" xmlns="http://www.w3.org/2000/svg">
                    <path d="M8 0.5 Q8.9 5.2 14.5 8 Q8.9 10.8 8 15.5 Q7.1 10.8 1.5 8 Q7.1 5.2 8 0.5Z"/>
                </svg>
            </div>
            <div>
                <div class="brand-name">Glint</div>
                <div class="brand-tagline">LLM Observability</div>
            </div>
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
            </div>
        </nav>

        <div class="sidebar-footer">
            <div class="sidebar-version">
                v{{ \Composer\InstalledVersions::getPrettyVersion('cybernerdie/laravel-glint') ?? 'dev' }}
            </div>
        </div>
    </aside>

    {{-- Main --}}
    <div class="main"
         x-data="{ refreshEvery: @yield('refresh-interval', 0), clock: '', live: false }"
         x-init="
             if (refreshEvery > 0) {
                 live = true;
                 setInterval(() => location.reload(), refreshEvery * 1000);
             }
             const tick = () => {
                 clock = new Date().toLocaleTimeString([], {
                     hour: '2-digit', minute: '2-digit', second: '2-digit'
                 });
             };
             tick(); setInterval(tick, 1000);
         ">

        <header class="topbar">
            <div class="topbar-left">
                <span class="topbar-root">Glint</span>
                <span class="topbar-sep">/</span>
                <span class="topbar-page">@yield('page-title', 'Dashboard')</span>
            </div>
            <div class="topbar-right">
                <span x-show="live" class="topbar-live" style="display:none">
                    <span class="live-indicator is-pulsing"></span>
                    Live
                </span>
                <span class="topbar-clock" x-text="clock"></span>
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
    </script>
</body>
</html>
