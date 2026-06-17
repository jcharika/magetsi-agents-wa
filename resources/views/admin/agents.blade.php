@extends('admin.layout')

@section('title', 'Agents')

@section('content')
    <div class="card" style="padding: 20px 24px; margin-bottom: 20px">
        <div style="display: flex; align-items: center; gap: 14px">
            <div style="width:42px;height:42px;background:#252c65;border-radius:4px;display:flex;align-items:center;justify-content:center;font-size:20px;color:#fff">🤖</div>
            <div>
                <h3 style="font-size:15px;font-weight:600;color:#333">Agents</h3>
                <p style="font-size:13px;color:#888">Manage all registered WhatsApp agents</p>
            </div>
        </div>
    </div>

    <div class="card" style="padding: 20px 24px; margin-bottom: 20px">
        <form method="GET" action="{{ route('admin.agents') }}">
            <div style="display:flex;gap:10px;align-items:end">
                <input type="text" name="search" class="input" placeholder="Search name, phone or EcoCash…"
                       value="{{ request('search') }}" style="min-width:220px;flex:1">
                <button type="submit" class="btn btn-primary">Search</button>
                <a href="{{ route('admin.agents') }}" class="btn btn-secondary">Clear</a>
            </div>
        </form>
    </div>

    <div class="card" style="padding:0">
        @if ($agents->count())
            <div class="table-wrap">
                <table class="soft-table">
                    <thead>
                        <tr>
                            <th>
                                <a href="{{ request()->fullUrlWithQuery(['sort' => 'name', 'dir' => request('sort') === 'name' && request('dir') === 'asc' ? 'desc' : 'asc']) }}" style="color:inherit;text-decoration:none">
                                    Name {{ request('sort') === 'name' ? (request('dir') === 'asc' ? '↑' : '↓') : '' }}
                                </a>
                            </th>
                            <th>
                                <a href="{{ request()->fullUrlWithQuery(['sort' => 'phone', 'dir' => request('sort') === 'phone' && request('dir') === 'asc' ? 'desc' : 'asc']) }}" style="color:inherit;text-decoration:none">
                                    Phone {{ request('sort') === 'phone' ? (request('dir') === 'asc' ? '↑' : '↓') : '' }}
                                </a>
                            </th>
                            <th>EcoCash</th>
                            <th>Status</th>
                            <th>
                                <a href="{{ request()->fullUrlWithQuery(['sort' => 'transactions_count', 'dir' => request('sort') === 'transactions_count' && request('dir') === 'asc' ? 'desc' : 'asc']) }}" style="color:inherit;text-decoration:none">
                                    Transactions {{ request('sort') === 'transactions_count' ? (request('dir') === 'asc' ? '↑' : '↓') : '' }}
                                </a>
                            </th>
                            <th>
                                <a href="{{ request()->fullUrlWithQuery(['sort' => 'transactions_sum_amount', 'dir' => request('sort') === 'transactions_sum_amount' && request('dir') === 'asc' ? 'desc' : 'asc']) }}" style="color:inherit;text-decoration:none">
                                    Revenue {{ request('sort') === 'transactions_sum_amount' ? (request('dir') === 'asc' ? '↑' : '↓') : '' }}
                                </a>
                            </th>
                            <th>Last Activity</th>
                            <th>Performance</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($agents as $agent)
                            <tr>
                                <td>
                                    <a href="{{ route('admin.agents.detail', $agent) }}" style="font-weight:600;color:#252c65;text-decoration:none">
                                        {{ $agent->name ?? '—' }}
                                    </a>
                                </td>
                                <td>{{ $agent->phone }}</td>
                                <td>{{ $agent->ecocash_number ?? '—' }}</td>
                                <td>
                                    <span class="badge {{ $agent->onboarded ? 'badge-success' : 'badge-warning' }}">
                                        {{ $agent->onboarded ? 'Onboarded' : 'Pending' }}
                                    </span>
                                    @if ($agent->blocked)
                                        <span class="badge badge-danger" style="margin-left:4px">Blocked</span>
                                    @endif
                                </td>
                                <td>{{ $agent->transactions_count }}</td>
                                <td style="font-weight:600;color:#344767">
                                    {{ number_format($agent->transactions_sum_amount ?? 0, 2) }}
                                </td>
                                <td style="font-size:12px;white-space:nowrap">
                                    {{ $agent->transactions()->latest()->value('created_at')?->diffForHumans() ?? '—' }}
                                </td>
                                <td>
                                    @php
                                        $total = $agent->transactions_count;
                                        $completed = $agent->completed_transactions_count;
                                        $rate = $total > 0 ? round(($completed / $total) * 100) : null;
                                    @endphp
                                    @if ($rate !== null)
                                        <span class="badge" style="background:{{ $rate >= 80 ? '#e2f9ed' : ($rate >= 50 ? '#fef3e2' : '#fce4e8') }};color:{{ $rate >= 80 ? '#1a966e' : ($rate >= 50 ? '#c87a1c' : '#c72a48') }}">
                                            {{ $rate }}%
                                        </span>
                                    @else
                                        <span style="color:#bbb">—</span>
                                    @endif
                                </td>
                                <td>
                                    <form method="POST" action="{{ route('admin.agents.toggle-block', $agent) }}" style="display:inline">
                                        @csrf
                                        <button type="submit" class="btn {{ $agent->blocked ? 'btn-secondary' : 'btn-outline' }}"
                                                style="padding:4px 12px;font-size:12px"
                                                onclick="return confirm('{{ $agent->blocked ? 'Unblock' : 'Block' }} {{ $agent->name }}?')">
                                            {{ $agent->blocked ? 'Unblock' : 'Block' }}
                                        </button>
                                    </form>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <div class="card-footer" style="display:flex;justify-content:space-between;align-items:center">
                <span>Showing {{ $agents->firstItem() }}–{{ $agents->lastItem() }} of {{ $agents->total() }}</span>
                <div class="pagination">
                    @if ($agents->onFirstPage())
                        <span>←</span>
                    @else
                        <a href="{{ $agents->previousPageUrl() }}">←</a>
                    @endif
                    @foreach ($agents->getUrlRange(max(1, $agents->currentPage() - 2), min($agents->lastPage(), $agents->currentPage() + 2)) as $page => $url)
                        <a href="{{ $url }}" class="{{ $page === $agents->currentPage() ? 'active' : '' }}">{{ $page }}</a>
                    @endforeach
                    @if ($agents->hasMorePages())
                        <a href="{{ $agents->nextPageUrl() }}">→</a>
                    @else
                        <span>→</span>
                    @endif
                </div>
            </div>
        @else
            <div class="card-body">
                <div class="empty-state"><p>No agents registered yet.</p></div>
            </div>
        @endif
    </div>
@endsection
