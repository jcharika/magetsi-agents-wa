@extends('admin.layout')

@section('title', 'Reports')

@section('content')
    <div class="card" style="padding: 20px 24px; margin-bottom: 20px">
        <div style="display: flex; align-items: center; gap: 14px">
            <div style="width:42px;height:42px;background:#252c65;border-radius:4px;display:flex;align-items:center;justify-content:center;font-size:20px;color:#fff">📋</div>
            <div>
                <h3 style="font-size:15px;font-weight:600;color:#333">Reports</h3>
                <p style="font-size:13px;color:#888">View and filter all transaction records</p>
            </div>
        </div>
    </div>

    <div class="card" style="padding: 20px 24px; margin-bottom: 24px">
        <form method="GET" action="{{ route('admin.reports') }}">
            <div class="filter-bar">
                <input type="text" name="search" class="input" placeholder="Search meter, name, reference…"
                       value="{{ request('search') }}" style="min-width:180px">
                <select name="status" class="select">
                    <option value="">All Status</option>
                    <option value="completed" {{ request('status') === 'completed' ? 'selected' : '' }}>Completed</option>
                    <option value="pending" {{ request('status') === 'pending' ? 'selected' : '' }}>Pending</option>
                    <option value="failed" {{ request('status') === 'failed' ? 'selected' : '' }}>Failed</option>
                </select>
                <input type="date" name="date_from" class="input" style="min-width:140px"
                       value="{{ request('date_from') }}" placeholder="From">
                <input type="date" name="date_to" class="input" style="min-width:140px"
                       value="{{ request('date_to') }}" placeholder="To">
                <button type="submit" class="btn btn-primary">Filter</button>
                <a href="{{ route('admin.reports') }}" class="btn btn-secondary">Clear</a>
            </div>
        </form>
    </div>

    <div class="card" style="padding:0">
        @if ($transactions->count())
            <div class="table-wrap">
                <table class="soft-table">
                    <thead>
                        <tr>
                            <th>
                                <a href="{{ request()->fullUrlWithQuery(['sort' => 'created_at', 'dir' => request('sort') === 'created_at' && request('dir') === 'asc' ? 'desc' : 'asc']) }}">
                                    Date {{ request('sort') === 'created_at' ? (request('dir') === 'asc' ? '↑' : '↓') : '' }}
                                </a>
                            </th>
                            <th>Agent</th>
                            <th>
                                <a href="{{ request()->fullUrlWithQuery(['sort' => 'meter_number', 'dir' => request('sort') === 'meter_number' && request('dir') === 'asc' ? 'desc' : 'asc']) }}">
                                    Meter {{ request('sort') === 'meter_number' ? (request('dir') === 'asc' ? '↑' : '↓') : '' }}
                                </a>
                            </th>
                            <th>Customer</th>
                            <th>
                                <a href="{{ request()->fullUrlWithQuery(['sort' => 'amount', 'dir' => request('sort') === 'amount' && request('dir') === 'asc' ? 'desc' : 'asc']) }}">
                                    Amount {{ request('sort') === 'amount' ? (request('dir') === 'asc' ? '↑' : '↓') : '' }}
                                </a>
                            </th>
                            <th>EcoCash</th>
                            <th>Reference</th>
                            <th>
                                <a href="{{ request()->fullUrlWithQuery(['sort' => 'status', 'dir' => request('sort') === 'status' && request('dir') === 'asc' ? 'desc' : 'asc']) }}">
                                    Status {{ request('sort') === 'status' ? (request('dir') === 'asc' ? '↑' : '↓') : '' }}
                                </a>
                            </th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($transactions as $txn)
                            <tr>
                                <td style="white-space:nowrap;font-size:12px">{{ $txn->created_at->format('d M Y H:i') }}</td>
                                <td style="font-weight:500;color:#344767">{{ $txn->agent?->name ?? '—' }}</td>
                                <td style="font-family:ui-monospace,monospace;font-size:12px;color:#252c65">{{ $txn->meter_number }}</td>
                                <td>{{ $txn->customer_name ?? '—' }}</td>
                                <td style="font-weight:600;color:#344767">{{ number_format($txn->amount, 2) }} {{ $txn->currency }}</td>
                                <td style="font-size:12px">{{ $txn->ecocash_number ?? '—' }}</td>
                                <td style="font-family:ui-monospace,monospace;font-size:12px;max-width:120px;overflow:hidden;text-overflow:ellipsis">
                                    {{ $txn->reference ?? '—' }}
                                </td>
                                <td>
                                    <span class="badge badge-{{ $txn->status === 'completed' ? 'success' : ($txn->status === 'failed' ? 'danger' : 'warning') }}">
                                        {{ ucfirst($txn->status) }}
                                    </span>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <div class="card-footer" style="display:flex;justify-content:space-between;align-items:center">
                <span>Showing {{ $transactions->firstItem() }}–{{ $transactions->lastItem() }} of {{ $transactions->total() }}</span>
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
                <div class="empty-state">
                    <p>No transactions found matching your criteria.</p>
                    <a href="{{ route('admin.reports') }}" class="btn btn-outline" style="margin-top:16px;display:inline-flex">Clear Filters</a>
                </div>
            </div>
        @endif
    </div>
@endsection
