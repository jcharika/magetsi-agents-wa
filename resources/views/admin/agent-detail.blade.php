@extends('admin.layout')

@section('title', 'Agent: ' . ($agent->name ?? '—'))

@section('content')
    <div class="card" style="padding: 20px 24px; margin-bottom: 20px">
        <div style="display: flex; align-items: center; gap: 14px">
            <div style="width:42px;height:42px;background:#252c65;border-radius:4px;display:flex;align-items:center;justify-content:center;font-size:20px;color:#fff">👤</div>
            <div style="flex:1">
                <div style="display:flex;align-items:center;gap:12px">
                    <h3 style="font-size:15px;font-weight:600;color:#333">Agent Details</h3>
                    <a href="{{ route('admin.agents') }}" class="btn btn-outline" style="padding:4px 12px;font-size:12px">← Back to Agents</a>
                </div>
                <p style="font-size:13px;color:#888;margin-top:2px">Profile and transaction history for <strong style="color:#333">{{ $agent->name ?? 'Unnamed' }}</strong></p>
            </div>
        </div>
    </div>

    <div style="display:grid;grid-template-columns:1fr 2fr;gap:24px">
        <div>
            <div class="card" style="padding:24px">
                <div style="display:flex;flex-direction:column;align-items:center;text-align:center">
                    <div style="width:64px;height:64px;border-radius:6px;background:#252c65;display:flex;align-items:center;justify-content:center;font-size:28px;color:#fff;margin-bottom:12px">
                        {{ strtoupper(substr($agent->name ?? '?', 0, 1)) }}
                    </div>
                    <h3 style="font-size:17px;font-weight:700;color:#344767">{{ $agent->name ?? 'Unnamed' }}</h3>
                    <p style="font-size:13px;color:#8392ab">{{ $agent->phone }}</p>
                    <div style="margin-top:12px;display:flex;gap:6px;flex-wrap:wrap;justify-content:center">
                        <span class="badge {{ $agent->onboarded ? 'badge-success' : 'badge-warning' }}">
                            {{ $agent->onboarded ? 'Onboarded' : 'Pending' }}
                        </span>
                        @if ($agent->blocked)
                            <span class="badge badge-danger">Blocked</span>
                        @endif
                    </div>
                </div>

                <hr style="border:none;border-top:1px solid #f0f2f8;margin:20px 0">

                <div style="font-size:13px">
                    <div style="display:flex;justify-content:space-between;padding:6px 0">
                        <span style="color:#8392ab">WA ID</span>
                        <span style="color:#344767;font-family:ui-monospace,monospace">{{ $agent->wa_id }}</span>
                    </div>
                    <div style="display:flex;justify-content:space-between;padding:6px 0">
                        <span style="color:#8392ab">EcoCash</span>
                        <span style="color:#344767">{{ $agent->ecocash_number ?? '—' }}</span>
                    </div>
                    <div style="display:flex;justify-content:space-between;padding:6px 0">
                        <span style="color:#8392ab">Created</span>
                        <span style="color:#344767;white-space:nowrap">{{ $agent->created_at->format('d M Y') }}</span>
                    </div>
                </div>

                <form method="POST" action="{{ route('admin.agents.toggle-block', $agent) }}" style="margin-top:16px">
                    @csrf
                    <button type="submit" class="btn {{ $agent->blocked ? 'btn-secondary' : 'btn-outline' }}" style="width:100%;justify-content:center"
                            onclick="return confirm('{{ $agent->blocked ? 'Unblock' : 'Block' }} {{ $agent->name }}?')">
                        {{ $agent->blocked ? '🔓 Unblock Agent' : '🔒 Block Agent' }}
                    </button>
                </form>
            </div>
        </div>

        <div>
            <div class="stat-row" style="grid-template-columns:repeat(2,1fr);margin-bottom:24px">
                <div class="card" style="padding:16px 20px;text-align:center">
                    <p style="font-size:12px;color:#8392ab;font-weight:600;text-transform:uppercase;letter-spacing:.03em">Transactions</p>
                    <p style="font-size:28px;font-weight:700;color:#344767;margin-top:4px">{{ $stats['total_transactions'] }}</p>
                </div>
                <div class="card" style="padding:16px 20px;text-align:center">
                    <p style="font-size:12px;color:#8392ab;font-weight:600;text-transform:uppercase;letter-spacing:.03em">Completed</p>
                    <p style="font-size:28px;font-weight:700;color:#2dce89;margin-top:4px">{{ $stats['completed_transactions'] }}</p>
                </div>
                <div class="card" style="padding:16px 20px;text-align:center">
                    <p style="font-size:12px;color:#8392ab;font-weight:600;text-transform:uppercase;letter-spacing:.03em">Revenue</p>
                    <p style="font-size:28px;font-weight:700;color:#252c65;margin-top:4px">{{ number_format($stats['total_revenue'], 2) }}</p>
                </div>
                <div class="card" style="padding:16px 20px;text-align:center">
                    <p style="font-size:12px;color:#8392ab;font-weight:600;text-transform:uppercase;letter-spacing:.03em">Last Transaction</p>
                    <p style="font-size:14px;font-weight:600;color:#344767;margin-top:4px">
                        {{ $stats['last_transaction_at']?->diffForHumans() ?? '—' }}
                    </p>
                </div>
            </div>

            <div class="card" style="padding:0">
                <div class="card-header"><h3>Transactions</h3></div>
                @if ($transactions->count())
                    <div class="table-wrap">
                        <table class="soft-table">
                            <thead>
                                <tr>
                                    <th>Date</th>
                                    <th>Meter</th>
                                    <th>Customer</th>
                                    <th>Amount</th>
                                    <th>Status</th>
                                    <th>Reference</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($transactions as $txn)
                                    <tr>
                                        <td style="font-size:12px;white-space:nowrap">{{ $txn->created_at->format('d M H:i') }}</td>
                                        <td style="font-family:ui-monospace,monospace;font-size:12px;color:#252c65">{{ $txn->meter_number }}</td>
                                        <td>{{ $txn->customer_name ?? '—' }}</td>
                                        <td style="font-weight:600;color:#344767">{{ number_format($txn->amount, 2) }}</td>
                                        <td>
                                            <span class="badge badge-{{ $txn->status === 'completed' ? 'success' : ($txn->status === 'failed' ? 'danger' : 'warning') }}">
                                                {{ ucfirst($txn->status) }}
                                            </span>
                                        </td>
                                        <td style="font-family:ui-monospace,monospace;font-size:12px">{{ $txn->reference ?? '—' }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                    <div class="card-footer" style="display:flex;justify-content:space-between;align-items:center">
                        <span>Page {{ $transactions->currentPage() }} of {{ $transactions->lastPage() }}</span>
                        <div class="pagination">
                            @if ($transactions->onFirstPage())
                                <span>←</span>
                            @else
                                <a href="{{ $transactions->previousPageUrl() }}">←</a>
                            @endif
                            @foreach ($transactions->getUrlRange(max(1, $transactions->currentPage() - 2), min($transactions->lastPage(), $transactions->currentPage() + 2)) as $page => $url)
                                <a href="{{ $url }}" class="{{ $page === $transactions->currentPage() ? 'active' : '' }}">{{ $page }}</a>
                            @endforeach
                            @if ($transactions->hasMorePages())
                                <a href="{{ $transactions->nextPageUrl() }}">→</a>
                            @else
                                <span>→</span>
                            @endif
                        </div>
                    </div>
                @else
                    <div class="card-body">
                        <div class="empty-state"><p>No transactions from this agent.</p></div>
                    </div>
                @endif
            </div>
        </div>
    </div>
@endsection
