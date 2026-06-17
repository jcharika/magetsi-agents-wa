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
            <div class="filter-bar" style="display:flex;flex-wrap:wrap;gap:10px;align-items:end">
                <input type="text" name="search" class="input" placeholder="Search meter, name, reference…"
                       value="{{ request('search') }}" style="min-width:160px;flex:1">
                <select name="status" class="select">
                    <option value="">All Status</option>
                    <option value="completed" {{ request('status') === 'completed' ? 'selected' : '' }}>Completed</option>
                    <option value="pending" {{ request('status') === 'pending' ? 'selected' : '' }}>Pending</option>
                    <option value="failed" {{ request('status') === 'failed' ? 'selected' : '' }}>Failed</option>
                </select>
                <select name="product_id" class="select">
                    <option value="">All Product</option>
                    @foreach ($products as $p)
                        <option value="{{ $p }}" {{ request('product_id') === $p ? 'selected' : '' }}>{{ ucfirst($p) }}</option>
                    @endforeach
                </select>
                <select name="handler" class="select">
                    <option value="">All Handler</option>
                    @foreach ($handlers as $h)
                        <option value="{{ $h }}" {{ request('handler') === $h ? 'selected' : '' }}>{{ $h }}</option>
                    @endforeach
                </select>
                <select name="currency" class="select">
                    <option value="">All Currency</option>
                    @foreach ($currencies as $c)
                        <option value="{{ $c }}" {{ request('currency') === $c ? 'selected' : '' }}>{{ $c }}</option>
                    @endforeach
                </select>
                <select name="agent_id" class="select">
                    <option value="">All Agents</option>
                    @foreach ($agents as $a)
                        <option value="{{ $a->id }}" {{ request('agent_id') == $a->id ? 'selected' : '' }}>{{ $a->name }}</option>
                    @endforeach
                </select>
                <select name="customer_id" class="select">
                    <option value="">All Customers</option>
                    @foreach ($customers as $c)
                        <option value="{{ $c->id }}" {{ request('customer_id') == $c->id ? 'selected' : '' }}>{{ $c->name }}</option>
                    @endforeach
                </select>
                <input type="number" name="amount_min" class="input" placeholder="Min amount"
                       value="{{ request('amount_min') }}" style="width:110px">
                <input type="number" name="amount_max" class="input" placeholder="Max amount"
                       value="{{ request('amount_max') }}" style="width:110px">
                <input type="date" name="date_from" class="input" style="min-width:130px"
                       value="{{ request('date_from') }}" placeholder="From">
                <input type="date" name="date_to" class="input" style="min-width:130px"
                       value="{{ request('date_to') }}" placeholder="To">
                <div style="display:flex;gap:6px;align-items:center">
                    <button type="submit" class="btn btn-primary">Filter</button>
                    <a href="{{ route('admin.reports') }}" class="btn btn-secondary">Clear</a>
                </div>
            </div>
        </form>
    </div>

    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:14px">
        <div></div>
        <div style="display:flex;gap:8px">
            <a href="{{ route('admin.reports.export.csv', request()->query()) }}" class="btn btn-outline" style="font-size:13px">
                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
                Excel
            </a>
            <a href="{{ route('admin.reports.export.pdf', request()->query()) }}" class="btn btn-outline" style="font-size:13px">
                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/><polyline points="10 9 9 9 8 9"/></svg>
                PDF
            </a>
        </div>
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
                            <th>Agent / Customer</th>
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
                            <th>Failure Reason</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($transactions as $txn)
                            @php $reason = $txn->failureReason(); @endphp
                            <tr>
                                <td style="white-space:nowrap;font-size:12px">
                                    @if ($txn->agent)
                                        <span style="font-weight:500;color:#344767">{{ $txn->agent->name }}</span>
                                        <span style="color:#8392ab;font-size:11px">(Agent)</span>
                                    @elseif ($txn->customer)
                                        <span style="font-weight:500;color:#344767">{{ $txn->customer->name }}</span>
                                        <span style="color:#8392ab;font-size:11px">(Customer)</span>
                                    @else
                                        <span style="color:#8392ab">—</span>
                                    @endif
                                </td>
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
                                <td style="font-size:12px;max-width:160px;overflow:hidden;text-overflow:ellipsis;color:{{ $reason ? '#c72a48' : '#8392ab' }}">
                                    {{ $reason ?? '—' }}
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
