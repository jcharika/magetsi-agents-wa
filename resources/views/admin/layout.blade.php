<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Dashboard') — Magetsi WhatsApp</title>
    <link rel="icon" href="https://magetsi.co.zw/img/Magetsi%20Logo-08.svg">
    <link href="https://fonts.googleapis.com/css2?family=Rubik:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    @if (file_exists(public_path('build/manifest.json')) || file_exists(public_path('hot')))
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    @endif
    <style>
        *, :after, :before { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'Rubik', system-ui, -apple-system, sans-serif;
            background: #f5f5f5;
            min-height: 100vh;
            color: #333;
        }
        a { text-decoration: none; }

        .wrapper { display: flex; min-height: 100vh; }

        .sidebar {
            width: 260px;
            background: #fff;
            position: fixed;
            top: 0; left: 0; bottom: 0;
            z-index: 40;
            display: flex;
            flex-direction: column;
            box-shadow: 0 0 2rem 0 rgba(0,0,0,.08);
            transition: all .2s;
        }
        .sidebar .logo {
            padding: 24px 24px 20px;
            display: flex;
            align-items: center;
            gap: 12px;
        }
        .sidebar .logo .logo-icon {
            width: 38px; height: 38px;
            background: #f05127;
            border-radius: 6px;
            display: flex; align-items: center; justify-content: center;
            flex-shrink: 0; padding: 6px;
        }
        .sidebar .logo h1 { font-size: 16px; font-weight: 700; color: #333; }
        .sidebar .logo p { font-size: 11px; color: #888; font-weight: 400; margin-top: 1px; }

        .sidebar nav { flex: 1; padding: 12px 16px; overflow-y: auto; }
        .sidebar nav .nav-label {
            font-size: 11px; font-weight: 600; color: #888;
            text-transform: uppercase; letter-spacing: .05em;
            padding: 12px 16px 8px; margin-top: 8px;
        }
        .sidebar nav a {
            display: flex; align-items: center; gap: 12px;
            padding: 10px 16px; margin: 2px 0;
            color: #666; font-size: 14px; font-weight: 400;
            border-radius: 6px; transition: all .15s;
        }
        .sidebar nav a:hover { background: #f5f5f5; color: #333; }
        .sidebar nav a.active {
            background: #252c65;
            color: #fff;
            font-weight: 500;
        }
        .sidebar nav a .icon { width: 20px; height: 20px; flex-shrink: 0; display: flex; align-items: center; justify-content: center; }
        .sidebar nav a .icon svg { width: 18px; height: 18px; stroke: currentColor; fill: none; stroke-width: 2; stroke-linecap: round; stroke-linejoin: round; }
        .sidebar .sidebar-footer { padding: 16px 20px; border-top: 1px solid #eee; }
        .sidebar .sidebar-footer .version { font-size: 11px; color: #888; }

        .main { margin-left: 260px; flex: 1; display: flex; flex-direction: column; min-height: 100vh; }

        .topbar {
            background: #fff;
            padding: 16px 32px;
            display: flex; align-items: center; justify-content: space-between;
            position: sticky; top: 0; z-index: 30;
            border-bottom: 1px solid #eee;
        }
        .topbar .breadcrumb { font-size: 14px; color: #888; }
        .topbar .breadcrumb strong { color: #333; font-weight: 600; }
        .topbar .env-badge {
            font-size: 12px; font-weight: 600;
            padding: 4px 14px; border-radius: 4px;
            background: #252c65;
            color: #fff;
        }

        .content { padding: 24px 32px 32px; flex: 1; }

        .card {
            background: #fff;
            border-radius: 6px;
            box-shadow: 0 1px 4px rgba(0,0,0,.06);
            border: 1px solid #eee;
        }

        .stat-row {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(240px, 1fr));
            gap: 20px;
            margin-bottom: 28px;
        }
        .stat-card {
            padding: 20px 24px;
            display: flex;
            align-items: center;
            gap: 16px;
            border-radius: 6px;
            position: relative;
            overflow: hidden;
            border: none !important;
        }
        .stat-card .stat-icon {
            width: 52px; height: 52px;
            border-radius: 6px;
            display: flex; align-items: center; justify-content: center;
            font-size: 22px;
            flex-shrink: 0;
        }
        .stat-card .stat-body { flex: 1; }
        .stat-card .stat-body h3 { font-size: 22px; font-weight: 700; line-height: 1.2; }
        .stat-card .stat-body p { font-size: 13px; opacity: .8; margin-top: 2px; }
        .stat-card .stat-trend {
            position: absolute; top: 16px; right: 20px;
            font-size: 12px; font-weight: 600;
            padding: 2px 10px; border-radius: 999px;
        }

        .stat-card.gradient-primary { background: #252c65; color: #fff; }
        .stat-card.gradient-success { background: #2dce89; color: #fff; }
        .stat-card.gradient-warning { background: #fb6340; color: #fff; }
        .stat-card.gradient-info { background: #11cdef; color: #fff; }
        .stat-card.gradient-danger { background: #f5365c; color: #fff; }
        .stat-card.gradient-dark { background: #333; color: #fff; }
        .stat-card .stat-icon.bg-white-alpha { background: rgba(255,255,255,.2); color: #fff; }

        .card-header {
            padding: 16px 20px;
            border-bottom: 1px solid #eee;
            display: flex; align-items: center; justify-content: space-between;
        }
        .card-header h3 { font-size: 15px; font-weight: 600; color: #333; }
        .card-body { padding: 20px 24px; }
        .card-footer { padding: 14px 20px; border-top: 1px solid #eee; font-size: 13px; color: #888; }

        .table-wrap { overflow-x: auto; }
        table.soft-table { width: 100%; border-collapse: collapse; font-size: 13px; }
        table.soft-table th {
            text-align: left; padding: 12px 16px;
            border-bottom: 1px solid #eee;
            color: #888; font-weight: 600; font-size: 11px;
            text-transform: uppercase; letter-spacing: .05em; white-space: nowrap;
        }
        table.soft-table th a { color: inherit; text-decoration: none; }
        table.soft-table th a:hover { color: #333; }
        table.soft-table td { padding: 12px 16px; border-bottom: 1px solid #eee; color: #666; }
        table.soft-table tbody tr { transition: background .12s; }
        table.soft-table tbody tr:hover { background: #f9f9f9; }

        .badge {
            display: inline-block; padding: 3px 12px;
            border-radius: 999px; font-size: 11px; font-weight: 600;
        }
        .badge-success { background: #e2f9ed; color: #1a966e; }
        .badge-warning { background: #fef3e2; color: #c87a1c; }
        .badge-danger { background: #fce4e8; color: #c72a48; }
        .badge-info { background: #d6f4ff; color: #0e9bc7; }

        .btn {
            display: inline-flex; align-items: center; gap: 6px;
            padding: 10px 20px; border-radius: 4px;
            font-size: 13px; font-weight: 600;
            border: none; cursor: pointer; text-decoration: none;
            transition: all .15s; font-family: inherit;
        }
        .btn-primary {
            background: #252c65;
            color: #fff;
        }
        .btn-primary:hover { background: #1e2555; }
        .btn-secondary {
            background: #f0f0f0; color: #666;
        }
        .btn-secondary:hover { background: #e0e0e0; }
        .btn-outline {
            background: transparent; color: #252c65;
            border: 1px solid #ddd;
        }
        .btn-outline:hover { background: #f5f5f5; border-color: #252c65; }

        .input, .select {
            padding: 10px 14px; border: 1px solid #ddd;
            border-radius: 4px; font-size: 13px; outline: none;
            font-family: inherit; background: #fff; color: #333;
            transition: border-color .15s;
        }
        .input:focus, .select:focus { border-color: #f05127; box-shadow: 0 0 0 3px rgba(240,81,39,.1); }

        .filter-bar { display: flex; gap: 12px; flex-wrap: wrap; align-items: center; }
        .filter-bar .input, .filter-bar .select { width: auto; min-width: 150px; }

        .pagination { display: flex; align-items: center; gap: 4px; }
        .pagination a, .pagination span {
            padding: 6px 12px; border-radius: 4px; font-size: 13px;
            color: #666; text-decoration: none;
            background: #fff; border: 1px solid #ddd; transition: all .12s;
        }
        .pagination a:hover { background: #f5f5f5; }
        .pagination .active { background: #252c65; color: #fff; border-color: #252c65; }

        .alert {
            padding: 14px 20px; border-radius: 4px; font-size: 13px;
            margin-bottom: 24px; display: flex; align-items: center; gap: 10px;
        }
        .alert-success { background: #e2f9ed; color: #1a966e; border: 1px solid #b8f0cf; }

        .grid-2 { display: grid; grid-template-columns: 1fr 1fr; gap: 24px; }
        .grid-3 { display: grid; grid-template-columns: repeat(auto-fill, minmax(300px, 1fr)); gap: 20px; }

        .empty-state { text-align: center; padding: 48px 24px; color: #888; }
        .empty-state p { font-size: 14px; margin-top: 8px; }

        .help-nav { display: flex; gap: 8px; flex-wrap: wrap; margin-bottom: 24px; padding-bottom: 20px; border-bottom: 1px solid #eee; }
        .help-nav a {
            padding: 6px 16px; border-radius: 4px; font-size: 13px; font-weight: 500;
            text-decoration: none; background: #f0f0f0; color: #666; transition: all .12s;
        }
        .help-nav a:hover, .help-nav a.active { background: #252c65; color: #fff; }

        .help-content { max-width: 820px; }
        .help-content h1 { font-size: 24px; font-weight: 700; color: #333; margin-bottom: 16px; margin-top: 36px; }
        .help-content h1:first-child { margin-top: 0; }
        .help-content h2 { font-size: 18px; font-weight: 600; color: #333; margin-bottom: 12px; margin-top: 28px; padding-bottom: 8px; border-bottom: 1px solid #eee; }
        .help-content h3 { font-size: 15px; font-weight: 600; color: #666; margin-bottom: 8px; margin-top: 20px; }
        .help-content p { font-size: 14px; color: #666; line-height: 1.8; margin-bottom: 12px; }
        .help-content ul { margin-bottom: 12px; padding-left: 24px; }
        .help-content li { font-size: 14px; color: #666; line-height: 1.7; margin-bottom: 4px; }
        .help-content strong { color: #333; }
        .help-content code { background: #f5f5f5; padding: 2px 6px; border-radius: 4px; font-size: 13px; color: #252c65; font-family: ui-monospace, monospace; }
        .help-content hr { border: none; border-top: 1px solid #eee; margin: 24px 0; }

        .config-group { margin-bottom: 32px; }
        .config-group h3 {
            font-size: 14px; font-weight: 600; color: #333;
            margin-bottom: 16px; padding-bottom: 10px;
            border-bottom: 1px solid #eee;
            display: flex; align-items: center; gap: 8px;
        }
        .config-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(320px, 1fr)); gap: 12px; }
        .config-field {
            padding: 14px 18px; background: #fafbfe;
            border-radius: 4px; border: 1px solid #eee;
        }
        .config-field label { display: block; font-size: 12px; font-weight: 600; color: #333; margin-bottom: 6px; }
        .config-field .input, .config-field .select { background: #fff; width: 100%; }
        .config-field .hint { font-size: 11px; color: #aaa; margin-top: 4px; font-family: ui-monospace, monospace; }

        .feature-card {
            background: #252c65;
            color: #fff; border-radius: 6px; padding: 28px 32px;
            position: relative; overflow: hidden; margin-bottom: 28px;
        }
        .feature-card h2 { font-size: 22px; font-weight: 700; margin-bottom: 6px; }
        .feature-card p { font-size: 14px; opacity: .85; line-height: 1.6; max-width: 500px; }
        .feature-card .feature-illustration {
            position: absolute; right: -20px; top: 50%; transform: translateY(-50%);
            font-size: 80px; opacity: .15;
        }

        .sim-frame-wrap {
            background: #111;
            border-radius: 6px;
            overflow: hidden;
            box-shadow: 0 4px 16px rgba(0,0,0,.2);
            height: calc(100vh - 160px);
            min-height: 600px;
            max-height: 780px;
        }
        .sim-frame-wrap iframe { width: 100%; height: 100%; border: none; display: block; }

        .avatar-stack { display: flex; align-items: center; }
        .avatar-stack .av {
            width: 32px; height: 32px; border-radius: 50%;
            border: 2px solid #fff; margin-left: -8px;
            display: flex; align-items: center; justify-content: center;
            font-size: 12px; font-weight: 600; color: #fff;
        }
        .avatar-stack .av:first-child { margin-left: 0; }

        .progress-bar {
            height: 6px; background: #eee; border-radius: 999px; overflow: hidden;
        }
        .progress-bar .fill {
            height: 100%; border-radius: 999px;
            background: #252c65;
            transition: width .3s;
        }

        @media (max-width: 768px) {
            .sidebar { width: 72px; }
            .sidebar .logo h1, .sidebar .logo p,
            .sidebar nav a span:not(.icon),
            .sidebar nav .nav-label { display: none; }
            .sidebar nav a { justify-content: center; padding: 12px; }
            .main { margin-left: 72px; }
            .grid-2 { grid-template-columns: 1fr; }
            .stat-row { grid-template-columns: repeat(auto-fill, minmax(160px, 1fr)); }
            .content { padding: 16px; }
        }
    </style>
</head>
<body>
<div class="wrapper">
    <aside class="sidebar">
            <div class="logo">
                <div class="logo-icon">
                    <svg viewBox="0 0 1804 1040" style="width:100%;height:100%;fill:#fff">
                        <polygon points="231.57 73.88 500.48 74.88 327.05 441.94 418 455.99 28.6 1011.65 162.76 579.14 55.64 562.59 231.57 73.88"/>
                        <path d="M1513.2,53.15c-137.64,0-268.2,59.77-351.33,153.94-34-96-125-153.94-246.33-153.94-110.48,0-213.47,52.52-284.22,135.83l34.89-114.1h-118L390.79,408.07,493,423.91,81.39,1011.23H379.94L546.05,467.89c22.15-72.44,92.54-125,170.41-125s115,56.14,90.69,135.83L643.8,1013H977.05l166.66-545.15c22.15-72.44,92.54-125,170.42-125s115,56.14,90.68,135.83L1241.47,1013h333.24l182.73-597.67C1822.32,203.47,1708.8,53.15,1513.2,53.15Z"/>
                    </svg>
                </div>
                <div>
                    <h1>Magetsi</h1>
                    <p>WhatsApp Admin</p>
                </div>
            </div>
        <nav>
            <a href="{{ route('admin.dashboard') }}" class="{{ request()->routeIs('admin.dashboard') ? 'active' : '' }}">
                <span class="icon"><svg viewBox="0 0 24 24"><rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/></svg></span>
                <span>Dashboard</span>
            </a>

            <div class="nav-label" style="margin-top:16px">Channels</div>
            <a href="{{ route('admin.agents') }}" class="{{ request()->routeIs('admin.agents*') ? 'active' : '' }}">
                <span class="icon"><svg viewBox="0 0 24 24"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg></span>
                <span>Agents</span>
            </a>
            <a href="{{ route('admin.customers') }}" class="{{ request()->routeIs('admin.customers*') ? 'active' : '' }}">
                <span class="icon"><svg viewBox="0 0 24 24"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg></span>
                <span>Customers</span>
            </a>

            <div class="nav-label" style="margin-top:16px">Operations</div>
            <a href="{{ route('admin.reports') }}" class="{{ request()->routeIs('admin.reports') ? 'active' : '' }}">
                <span class="icon"><svg viewBox="0 0 24 24"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/></svg></span>
                <span>Reports</span>
            </a>
            <a href="{{ route('admin.simulator') }}" class="{{ request()->routeIs('admin.simulator') ? 'active' : '' }}">
                <span class="icon"><svg viewBox="0 0 24 24"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg></span>
                <span>Simulator</span>
            </a>

            <div class="nav-label" style="margin-top:16px">System</div>
            <a href="{{ route('admin.config') }}" class="{{ request()->routeIs('admin.config') ? 'active' : '' }}">
                <span class="icon"><svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1-2.83 2.83l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-4 0v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83-2.83l.06-.06A1.65 1.65 0 0 0 4.68 15a1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1 0-4h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 2.83-2.83l.06.06A1.65 1.65 0 0 0 9 4.68a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 4 0v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 2.83l-.06.06A1.65 1.65 0 0 0 19.4 9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 0 4h-.09a1.65 1.65 0 0 0-1.51 1z"/></svg></span>
                <span>Configuration</span>
            </a>
            <a href="{{ route('admin.users') }}" class="{{ request()->routeIs('admin.users') ? 'active' : '' }}">
                <span class="icon"><svg viewBox="0 0 24 24"><path d="M16 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M20 8v6"/><path d="M23 11h-6"/></svg></span>
                <span>Admin Users</span>
            </a>

            <div class="nav-label" style="margin-top:16px">Support</div>
            <a href="{{ route('admin.help') }}" class="{{ request()->routeIs('admin.help') ? 'active' : '' }}">
                <span class="icon"><svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><path d="M9.09 9a3 3 0 0 1 5.83 1c0 2-3 3-3 3"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg></span>
                <span>Help & Docs</span>
            </a>
        </nav>
        <div class="sidebar-footer">
            <span class="version">v1.0.0 · Laravel {{ app()->version() }}</span>
        </div>
    </aside>

    <div class="main">
        <header class="topbar">
            <div class="breadcrumb">
                Pages / <strong>@yield('title', 'Dashboard')</strong>
            </div>
            <div style="display:flex;align-items:center;gap:16px">
                @auth
                    <div class="user-menu" style="position:relative">
                        <button class="user-btn" type="button" style="display:flex;align-items:center;gap:8px;background:none;border:none;cursor:pointer;font-family:inherit;padding:4px 8px;border-radius:10px;transition:background .12s" onmouseover="this.style.background='#f0f2f8'" onmouseout="this.style.background='none'">
                            <div style="width:32px;height:32px;border-radius:50%;background:#252c65;display:flex;align-items:center;justify-content:center;color:#fff;font-size:13px;font-weight:600" id="userAvatar">A</div>
                            <span style="font-size:13px;font-weight:500;color:#344767" id="userName">{{ Auth::user()->name }}</span>
                            <span style="font-size:10px;color:#8392ab">▼</span>
                        </button>
                        <div class="user-dropdown">
                            <div style="padding:12px 16px;border-bottom:1px solid #f0f2f8;font-size:12px;color:#8392ab">
                                <div style="font-weight:600;color:#344767;font-size:13px">{{ Auth::user()->name }}</div>
                                <div>{{ Auth::user()->email }}</div>
                            </div>
                            <a href="{{ route('admin.logout') }}" onclick="event.preventDefault();document.getElementById('logout-form').submit()" style="display:flex;align-items:center;gap:8px;padding:10px 16px;font-size:13px;color:#67748e;text-decoration:none;transition:background .12s" onmouseover="this.style.background='#f8f9fe'" onmouseout="this.style.background='none'">
                                <span>🚪</span> Sign Out
                            </a>
                            <form id="logout-form" action="{{ route('admin.logout') }}" method="POST" style="display:none">@csrf</form>
                        </div>
                    </div>
                @endauth
                <span class="env-badge">{{ config('app.env') }}</span>
            </div>
        </header>

        <style>
            .user-dropdown { display: none; position: absolute; top: 100%; right: 0; margin-top: 6px; background: #fff; border-radius: 4px; box-shadow: 0 8px 32px rgba(0,0,0,.12); border: 1px solid #eee; min-width: 180px; z-index: 50; overflow: hidden; }
            .user-dropdown.open { display: block; }
        </style>
        <script>
            document.addEventListener('click', function(e) {
                var menu = document.querySelector('.user-menu');
                var dropdown = menu && menu.querySelector('.user-dropdown');
                if (!dropdown) return;
                var btn = menu.querySelector('.user-btn');
                if (btn && btn.contains(e.target)) {
                    dropdown.classList.toggle('open');
                } else if (!menu.contains(e.target)) {
                    dropdown.classList.remove('open');
                }
            });
        </script>

        <div class="content">
            @if (session('status'))
                <div class="alert alert-success">
                    <span style="font-size:16px">✓</span> {{ session('status') }}
                </div>
            @endif

            @yield('content')
        </div>
    </div>
</div>

@stack('scripts')
</body>
</html>
