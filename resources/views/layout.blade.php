<!DOCTYPE html>
<html lang="en" class="h-full">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Glint &mdash; LLM Observability</title>
    <script defer
            src="https://cdn.jsdelivr.net/npm/alpinejs@3.14.9/dist/cdn.min.js"
            integrity="sha256-PtHu0lJIiSHfZeNj1nFd6wTX+Squ255SGZ/fc8seCtM="
            crossorigin="anonymous"></script>
    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

        :root {
            --bg:        #0f172a;
            --sidebar:   #0d1526;
            --card:      #1e293b;
            --card-alt:  #243044;
            --border:    #2d3f58;
            --text:      #e2e8f0;
            --muted:     #94a3b8;
            --accent:    #6366f1;
            --accent-h:  #818cf8;
            --success:   #22c55e;
            --warning:   #f59e0b;
            --error:     #ef4444;
            --pending:   #64748b;
        }

        html, body { height: 100%; }

        body {
            background: var(--bg);
            color: var(--text);
            font-family: ui-sans-serif, system-ui, -apple-system, sans-serif;
            font-size: 14px;
            line-height: 1.5;
            display: flex;
        }

        /* ── Sidebar ─────────────────────────────────── */
        .sidebar {
            width: 220px;
            min-height: 100vh;
            background: var(--sidebar);
            border-right: 1px solid var(--border);
            display: flex;
            flex-direction: column;
            flex-shrink: 0;
        }

        .sidebar-logo {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 20px 18px 16px;
            border-bottom: 1px solid var(--border);
        }

        .sidebar-logo-icon {
            width: 30px;
            height: 30px;
            background: var(--accent);
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 16px;
            flex-shrink: 0;
        }

        .sidebar-logo-text {
            font-size: 17px;
            font-weight: 700;
            color: var(--text);
            letter-spacing: -0.3px;
        }

        .sidebar-logo-sub {
            font-size: 10px;
            color: var(--muted);
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .sidebar-nav {
            padding: 12px 10px;
            flex: 1;
        }

        .sidebar-label {
            font-size: 10px;
            font-weight: 600;
            color: var(--muted);
            text-transform: uppercase;
            letter-spacing: 0.8px;
            padding: 8px 8px 4px;
        }

        .nav-link {
            display: flex;
            align-items: center;
            gap: 9px;
            padding: 8px 10px;
            border-radius: 6px;
            color: var(--muted);
            text-decoration: none;
            font-size: 13.5px;
            font-weight: 500;
            transition: background 0.15s, color 0.15s;
            margin-bottom: 2px;
        }

        .nav-link:hover {
            background: var(--card);
            color: var(--text);
        }

        .nav-link.active {
            background: var(--card-alt);
            color: var(--accent-h);
        }

        .nav-link svg {
            width: 16px;
            height: 16px;
            flex-shrink: 0;
            opacity: 0.8;
        }

        .nav-link.active svg { opacity: 1; }

        .sidebar-footer {
            padding: 14px 18px;
            border-top: 1px solid var(--border);
            font-size: 11px;
            color: var(--muted);
        }

        /* ── Main layout ─────────────────────────────── */
        .main-wrap {
            flex: 1;
            display: flex;
            flex-direction: column;
            min-width: 0;
            overflow-x: hidden;
        }

        .topbar {
            height: 52px;
            border-bottom: 1px solid var(--border);
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 0 24px;
            background: var(--bg);
            flex-shrink: 0;
        }

        .topbar-title {
            font-size: 15px;
            font-weight: 600;
            color: var(--text);
        }

        .topbar-right {
            display: flex;
            align-items: center;
            gap: 16px;
            font-size: 12px;
            color: var(--muted);
        }

        .topbar-time {
            font-variant-numeric: tabular-nums;
        }

        .status-dot {
            width: 7px;
            height: 7px;
            border-radius: 50%;
            background: var(--success);
            display: inline-block;
            animation: pulse 2s infinite;
        }

        @keyframes pulse {
            0%, 100% { opacity: 1; }
            50% { opacity: 0.4; }
        }

        .content {
            padding: 24px;
            flex: 1;
            overflow-y: auto;
        }

        /* ── Cards ───────────────────────────────────── */
        .page-header {
            margin-bottom: 20px;
        }

        .page-title {
            font-size: 20px;
            font-weight: 700;
            color: var(--text);
        }

        .page-subtitle {
            font-size: 13px;
            color: var(--muted);
            margin-top: 2px;
        }

        .card {
            background: var(--card);
            border: 1px solid var(--border);
            border-radius: 10px;
            padding: 18px 20px;
        }

        .card-title {
            font-size: 12px;
            font-weight: 600;
            color: var(--muted);
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 8px;
        }

        .card-value {
            font-size: 26px;
            font-weight: 700;
            color: var(--text);
            line-height: 1.1;
        }

        .card-value-sm {
            font-size: 20px;
        }

        .card-sub {
            font-size: 11px;
            color: var(--muted);
            margin-top: 4px;
        }

        /* ── Stats grid ──────────────────────────────── */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(170px, 1fr));
            gap: 14px;
            margin-bottom: 24px;
        }

        /* ── Tables ──────────────────────────────────── */
        .table-wrap {
            background: var(--card);
            border: 1px solid var(--border);
            border-radius: 10px;
            overflow: hidden;
            margin-bottom: 20px;
        }

        .table-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 14px 18px;
            border-bottom: 1px solid var(--border);
        }

        .table-title {
            font-size: 14px;
            font-weight: 600;
            color: var(--text);
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        th {
            text-align: left;
            padding: 10px 16px;
            font-size: 11px;
            font-weight: 600;
            color: var(--muted);
            text-transform: uppercase;
            letter-spacing: 0.5px;
            border-bottom: 1px solid var(--border);
            background: var(--sidebar);
        }

        td {
            padding: 11px 16px;
            border-bottom: 1px solid var(--border);
            color: var(--text);
            font-size: 13px;
        }

        tr:last-child td { border-bottom: none; }

        tr:hover td { background: rgba(255,255,255,0.02); }

        .td-mono {
            font-family: ui-monospace, SFMono-Regular, Menlo, monospace;
            font-size: 12px;
        }

        .td-muted { color: var(--muted); }

        /* ── Badges ──────────────────────────────────── */
        .badge {
            display: inline-flex;
            align-items: center;
            padding: 2px 8px;
            border-radius: 9999px;
            font-size: 11px;
            font-weight: 600;
            letter-spacing: 0.2px;
        }

        .badge-success { background: rgba(34,197,94,0.15); color: #4ade80; }
        .badge-error   { background: rgba(239,68,68,0.15);  color: #f87171; }
        .badge-pending { background: rgba(100,116,139,0.2); color: #94a3b8; }
        .badge-info    { background: rgba(99,102,241,0.15); color: #a5b4fc; }

        /* ── Links ───────────────────────────────────── */
        a.link {
            color: var(--accent-h);
            text-decoration: none;
            font-weight: 500;
        }

        a.link:hover { text-decoration: underline; }

        /* ── Forms / filters ─────────────────────────── */
        .filter-bar {
            display: flex;
            gap: 10px;
            align-items: center;
            flex-wrap: wrap;
            margin-bottom: 16px;
        }

        .input {
            background: var(--card);
            border: 1px solid var(--border);
            color: var(--text);
            border-radius: 6px;
            padding: 7px 12px;
            font-size: 13px;
            outline: none;
            transition: border-color 0.15s;
        }

        .input:focus { border-color: var(--accent); }

        .input::placeholder { color: var(--muted); }

        .btn {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 7px 14px;
            border-radius: 6px;
            font-size: 13px;
            font-weight: 500;
            border: none;
            cursor: pointer;
            text-decoration: none;
            transition: background 0.15s;
        }

        .btn-primary {
            background: var(--accent);
            color: #fff;
        }

        .btn-primary:hover { background: var(--accent-h); }

        .btn-ghost {
            background: var(--card-alt);
            color: var(--muted);
            border: 1px solid var(--border);
        }

        .btn-ghost:hover { color: var(--text); }

        /* ── Pagination ──────────────────────────────── */
        .pagination {
            display: flex;
            justify-content: center;
            gap: 6px;
            padding: 16px;
        }

        .pagination a, .pagination span {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-width: 32px;
            height: 32px;
            padding: 0 8px;
            border-radius: 6px;
            font-size: 13px;
            text-decoration: none;
            border: 1px solid var(--border);
            color: var(--muted);
            transition: all 0.15s;
        }

        .pagination a:hover { background: var(--card-alt); color: var(--text); }
        .pagination .active span { background: var(--accent); color: #fff; border-color: var(--accent); }
        .pagination .disabled span { opacity: 0.4; cursor: default; }

        /* ── Detail sections ─────────────────────────── */
        .detail-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
            gap: 14px;
            margin-bottom: 20px;
        }

        .detail-item label {
            display: block;
            font-size: 11px;
            font-weight: 600;
            color: var(--muted);
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 4px;
        }

        .detail-item .value {
            font-size: 14px;
            color: var(--text);
            word-break: break-all;
        }

        .detail-item .value-mono {
            font-family: ui-monospace, SFMono-Regular, Menlo, monospace;
            font-size: 12px;
            word-break: break-all;
        }

        .code-block {
            background: var(--sidebar);
            border: 1px solid var(--border);
            border-radius: 8px;
            padding: 14px 16px;
            font-family: ui-monospace, SFMono-Regular, Menlo, monospace;
            font-size: 12px;
            line-height: 1.6;
            color: #cbd5e1;
            white-space: pre-wrap;
            word-break: break-word;
            overflow-x: auto;
            max-height: 400px;
            overflow-y: auto;
        }

        .section-title {
            font-size: 14px;
            font-weight: 600;
            color: var(--text);
            margin-bottom: 10px;
            padding-bottom: 8px;
            border-bottom: 1px solid var(--border);
        }

        .mb-4 { margin-bottom: 16px; }
        .mb-6 { margin-bottom: 24px; }
        .mt-4 { margin-top: 16px; }

        .empty-state {
            text-align: center;
            padding: 48px 24px;
            color: var(--muted);
        }

        .empty-state-icon { font-size: 36px; margin-bottom: 12px; }
        .empty-state-text { font-size: 14px; }

        /* ── Accessibility ───────────────────────────── */
        .sr-only {
            position: absolute;
            width: 1px;
            height: 1px;
            padding: 0;
            margin: -1px;
            overflow: hidden;
            clip: rect(0, 0, 0, 0);
            white-space: nowrap;
            border-width: 0;
        }

        /* ── Dashboard two-column layout ─────────────── */
        .dashboard-layout {
            display: grid;
            grid-template-columns: 1fr;
            gap: 20px;
            align-items: start;
        }

        @media (min-width: 1024px) {
            .dashboard-layout {
                grid-template-columns: 1fr 340px;
            }
        }

        /* ── Responsive ──────────────────────────────── */
        @media (max-width: 768px) {
            .sidebar {
                width: 180px;
            }

            .sidebar-logo-text { font-size: 15px; }
            .sidebar-logo-sub { display: none; }

            .content { padding: 16px; }

            .stats-grid {
                grid-template-columns: repeat(auto-fill, minmax(140px, 1fr));
            }
        }

        @media (max-width: 640px) {
            body { flex-direction: column; }

            .sidebar {
                width: 100%;
                min-height: auto;
                border-right: none;
                border-bottom: 1px solid var(--border);
            }

            .sidebar-nav {
                display: flex;
                flex-wrap: wrap;
                gap: 4px;
                padding: 8px;
            }

            .sidebar-label { display: none; }
            .sidebar-footer { display: none; }

            .nav-link {
                flex-direction: column;
                gap: 3px;
                padding: 6px 10px;
                font-size: 11px;
            }

            .table-wrap { overflow-x: auto; }

            table { min-width: 500px; }
        }

        /* ── Bar chart ───────────────────────────────── */
        .bar-chart {
            display: flex;
            flex-direction: column;
            gap: 10px;
        }

        .bar-row {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .bar-label {
            width: 120px;
            font-size: 12px;
            color: var(--muted);
            text-align: right;
            flex-shrink: 0;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        .bar-track {
            flex: 1;
            height: 20px;
            background: var(--sidebar);
            border-radius: 4px;
            overflow: hidden;
        }

        .bar-fill {
            height: 100%;
            background: var(--accent);
            border-radius: 4px;
            transition: width 0.4s ease;
        }

        .bar-value {
            width: 70px;
            font-size: 12px;
            color: var(--text);
            text-align: left;
            flex-shrink: 0;
        }
    </style>
</head>
<body>

    {{-- Sidebar --}}
    <aside class="sidebar">
        <div class="sidebar-logo">
            <div class="sidebar-logo-icon">&#10024;</div>
            <div>
                <div class="sidebar-logo-text">Glint</div>
                <div class="sidebar-logo-sub">LLM Observability</div>
            </div>
        </div>

        <nav class="sidebar-nav">
            <div class="sidebar-label">Overview</div>

            <a href="{{ route('glint.dashboard') }}"
               class="nav-link {{ request()->routeIs('glint.dashboard') ? 'active' : '' }}">
                <svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <rect x="3" y="3" width="7" height="7" rx="1"/>
                    <rect x="14" y="3" width="7" height="7" rx="1"/>
                    <rect x="3" y="14" width="7" height="7" rx="1"/>
                    <rect x="14" y="14" width="7" height="7" rx="1"/>
                </svg>
                Dashboard
            </a>

            <div class="sidebar-label" style="margin-top:12px">Data</div>

            <a href="{{ route('glint.traces.index') }}"
               class="nav-link {{ request()->routeIs('glint.traces*') ? 'active' : '' }}">
                <svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path d="M9 17H7A5 5 0 0 1 7 7h2"/>
                    <path d="M15 7h2a5 5 0 0 1 0 10h-2"/>
                    <line x1="8" y1="12" x2="16" y2="12"/>
                </svg>
                Traces
            </a>

            <a href="{{ route('glint.generations.index') }}"
               class="nav-link {{ request()->routeIs('glint.generations*') ? 'active' : '' }}">
                <svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <circle cx="12" cy="12" r="3"/>
                    <path d="M19.07 4.93a10 10 0 0 1 0 14.14M4.93 4.93a10 10 0 0 0 0 14.14"/>
                </svg>
                Generations
            </a>

            <a href="{{ route('glint.costs.index') }}"
               class="nav-link {{ request()->routeIs('glint.costs*') ? 'active' : '' }}">
                <svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <line x1="12" y1="1" x2="12" y2="23"/>
                    <path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/>
                </svg>
                Costs
            </a>
        </nav>

        <div class="sidebar-footer">
            v{{ \Composer\InstalledVersions::getPrettyVersion('cybernerdie/laravel-glint') ?? 'dev' }}
        </div>
    </aside>

    {{-- Main content --}}
    <div class="main-wrap"
         x-data="{ refreshEvery: @yield('refresh-interval', 0), time: '', live: false }"
         x-init="
             if (refreshEvery > 0) { live = true; setInterval(() => location.reload(), refreshEvery * 1000); }
             const tick = () => { time = new Date().toLocaleTimeString(); };
             tick(); setInterval(tick, 1000);
         ">
        <header class="topbar">
            <span class="topbar-title">@yield('page-title', 'Dashboard')</span>
            <div class="topbar-right">
                <span class="status-dot" :style="live ? '' : 'animation:none'"></span>
                <span x-text="live ? 'Live' : 'Active'">Active</span>
                <span class="topbar-time" x-text="time"></span>
            </div>
        </header>

        <main class="content">
            @yield('content')
        </main>
    </div>

</body>
</html>
