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

    <div class="card" style="padding:0">
        @if ($agents->count())
            <div class="table-wrap">
                <table class="soft-table">
                    <thead>
                        <tr>
                            <th>Name</th>
                            <th>Phone</th>
                            <th>EcoCash</th>
                            <th>Status</th>
                            <th>Transactions</th>
                            <th>Revenue</th>
                            <th>Last Activity</th>
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
