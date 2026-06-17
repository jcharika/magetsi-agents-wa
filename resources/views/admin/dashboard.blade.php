@extends('admin.layout')

@section('title', 'Dashboard')

@section('content')
    <div class="card" style="padding: 20px 24px; margin-bottom: 20px">
        <div style="display: flex; align-items: center; gap: 14px">
            <div style="width:42px;height:42px;background:#252c65;border-radius:4px;display:flex;align-items:center;justify-content:center;font-size:20px;color:#fff">📊</div>
            <div>
                <h3 style="font-size:15px;font-weight:600;color:#333">Dashboard</h3>
                <p style="font-size:13px;color:#888">Overview of your chatbot platform</p>
            </div>
        </div>
    </div>

    <div class="feature-card">
        <h2>Welcome back, Admin ⚡</h2>
        <p>Your chatbot processed <strong>{{ $totalTransactions }}</strong> transactions across <strong>{{ $totalAgents }}</strong> agents. Keep up the great work!</p>
        <div class="feature-illustration">🚀</div>
    </div>

    <div class="stat-row">
        <div class="card stat-card gradient-primary">
            <div class="stat-icon bg-white-alpha">🤖</div>
            <div class="stat-body">
                <h3>{{ $totalAgents }}</h3>
                <p>Total Agents</p>
            </div>
            <span class="stat-trend" style="background:rgba(255,255,255,.2)">{{ $onboardedAgents }} onboarded</span>
        </div>

        <div class="card stat-card gradient-success">
            <div class="stat-icon bg-white-alpha">📊</div>
            <div class="stat-body">
                <h3>{{ $totalTransactions }}</h3>
                <p>Total Transactions</p>
            </div>
            <span class="stat-trend" style="background:rgba(255,255,255,.2)">+{{ $completedTransactions }} done</span>
        </div>

        <div class="card stat-card gradient-warning">
            <div class="stat-icon bg-white-alpha">📈</div>
            <div class="stat-body">
                <h3>{{ $successRate }}%</h3>
                <p>Success Rate</p>
            </div>
            <span class="stat-trend" style="background:rgba(255,255,255,.2)">
                {{ $failedTransactions }} failed
            </span>
        </div>

        <div class="card stat-card gradient-info">
            <div class="stat-icon bg-white-alpha">💰</div>
            <div class="stat-body">
                <h3>{{ number_format($totalRevenue, 2) }}</h3>
                <p>Total Revenue</p>
            </div>
            <span class="stat-trend" style="background:rgba(255,255,255,.2)">{{ $pendingTransactions }} pending</span>
        </div>
    </div>

    <div class="grid-2">
        <div class="card">
            <div class="card-header">
                <h3>Recent Transactions</h3>
                <a href="{{ route('admin.reports') }}" class="btn btn-outline" style="padding:6px 14px;font-size:12px">View All</a>
            </div>
            @if ($recentTransactions->count())
                <div class="table-wrap">
                    <table class="soft-table">
                        <thead>
                            <tr>
                                <th>Agent</th>
                                <th>Meter</th>
                                <th>
                                    <a href="{{ request()->fullUrlWithQuery(['txn_sort' => 'amount', 'txn_dir' => request('txn_sort') === 'amount' && request('txn_dir') === 'asc' ? 'desc' : 'asc']) }}" style="color:inherit;text-decoration:none">
                                        Amount {{ request('txn_sort') === 'amount' ? (request('txn_dir') === 'asc' ? '↑' : '↓') : '' }}
                                    </a>
                                </th>
                                <th>
                                    <a href="{{ request()->fullUrlWithQuery(['txn_sort' => 'status', 'txn_dir' => request('txn_sort') === 'status' && request('txn_dir') === 'asc' ? 'desc' : 'asc']) }}" style="color:inherit;text-decoration:none">
                                        Status {{ request('txn_sort') === 'status' ? (request('txn_dir') === 'asc' ? '↑' : '↓') : '' }}
                                    </a>
                                </th>
                                <th>
                                    <a href="{{ request()->fullUrlWithQuery(['txn_sort' => 'created_at', 'txn_dir' => request('txn_sort') === 'created_at' && request('txn_dir') === 'asc' ? 'desc' : 'asc']) }}" style="color:inherit;text-decoration:none">
                                        Date {{ request('txn_sort') === 'created_at' ? (request('txn_dir') === 'asc' ? '↑' : '↓') : '' }}
                                    </a>
                                </th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($recentTransactions as $txn)
                                <tr>
                                    <td style="font-weight:500;color:#344767">{{ $txn->agent?->name ?? '—' }}</td>
                                    <td style="font-family:ui-monospace,monospace">{{ $txn->meter_number }}</td>
                                    <td style="font-weight:600;color:#344767">{{ number_format($txn->amount, 2) }}</td>
                                    <td>
                                        <span class="badge badge-{{ $txn->status === 'completed' ? 'success' : ($txn->status === 'failed' ? 'danger' : 'warning') }}">
                                            {{ ucfirst($txn->status) }}
                                        </span>
                                    </td>
                                    <td style="font-size:12px;white-space:nowrap">{{ $txn->created_at->format('d M H:i') }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @else
                <div class="card-body">
                    <div class="empty-state"><p>No transactions yet</p></div>
                </div>
            @endif
        </div>

        <div class="card">
            <div class="card-header">
                <h3>Recent Agents</h3>
            </div>
            @if ($recentAgents->count())
                <div class="table-wrap">
                    <table class="soft-table">
                        <thead>
                            <tr>
                                <th>
                                    <a href="{{ request()->fullUrlWithQuery(['agent_sort' => 'name', 'agent_dir' => request('agent_sort') === 'name' && request('agent_dir') === 'asc' ? 'desc' : 'asc']) }}" style="color:inherit;text-decoration:none">
                                        Name {{ request('agent_sort') === 'name' ? (request('agent_dir') === 'asc' ? '↑' : '↓') : '' }}
                                    </a>
                                </th>
                                <th>Phone</th>
                                <th>EcoCash</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($recentAgents as $agent)
                                <tr>
                                    <td style="font-weight:500;color:#344767">{{ $agent->name ?? '—' }}</td>
                                    <td>{{ $agent->phone }}</td>
                                    <td>{{ $agent->ecocash_number ?? '—' }}</td>
                                    <td>
                                        <span class="badge {{ $agent->onboarded ? 'badge-success' : 'badge-warning' }}">
                                            {{ $agent->onboarded ? 'Onboarded' : 'Pending' }}
                                        </span>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @else
                <div class="card-body">
                    <div class="empty-state"><p>No agents registered yet</p></div>
                </div>
            @endif
        </div>
    </div>
@endsection
