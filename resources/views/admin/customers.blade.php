@extends('admin.layout')

@section('title', 'Customers')

@section('content')
    <div class="card" style="padding: 20px 24px; margin-bottom: 20px">
        <div style="display: flex; align-items: center; gap: 14px">
            <div style="width:42px;height:42px;background:#252c65;border-radius:4px;display:flex;align-items:center;justify-content:center;font-size:20px;color:#fff">👥</div>
            <div>
                <h3 style="font-size:15px;font-weight:600;color:#333">Customers</h3>
                <p style="font-size:13px;color:#888">Manage all WhatsApp customers using the customer chatbot</p>
            </div>
        </div>
    </div>

    <div class="card" style="padding:0">
        @if ($customers->count())
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
                            <th>
                                <a href="{{ request()->fullUrlWithQuery(['sort' => 'transactions_count', 'dir' => request('sort') === 'transactions_count' && request('dir') === 'asc' ? 'desc' : 'asc']) }}" style="color:inherit;text-decoration:none">
                                    Transactions {{ request('sort') === 'transactions_count' ? (request('dir') === 'asc' ? '↑' : '↓') : '' }}
                                </a>
                            </th>
                            <th>
                                <a href="{{ request()->fullUrlWithQuery(['sort' => 'created_at', 'dir' => request('sort') === 'created_at' && request('dir') === 'asc' ? 'desc' : 'asc']) }}" style="color:inherit;text-decoration:none">
                                    Created {{ request('sort') === 'created_at' ? (request('dir') === 'asc' ? '↑' : '↓') : '' }}
                                </a>
                            </th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($customers as $customer)
                            <tr>
                                <td>
                                    <a href="{{ route('admin.customers.detail', $customer) }}" style="font-weight:600;color:#252c65;text-decoration:none">
                                        {{ $customer->name ?? '—' }}
                                    </a>
                                </td>
                                <td>{{ $customer->phone }}</td>
                                <td>{{ $customer->transactions_count }}</td>
                                <td style="font-size:12px;white-space:nowrap">{{ $customer->created_at->format('d M Y H:i') }}</td>
                                <td>
                                    <a href="{{ route('admin.customers.detail', $customer) }}" class="btn btn-outline" style="padding:4px 12px;font-size:12px">View</a>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <div class="card-footer" style="display:flex;justify-content:space-between;align-items:center">
                <span>Showing {{ $customers->firstItem() }}–{{ $customers->lastItem() }} of {{ $customers->total() }}</span>
                <div class="pagination">
                    @if ($customers->onFirstPage())
                        <span>←</span>
                    @else
                        <a href="{{ $customers->previousPageUrl() }}">←</a>
                    @endif
                    @foreach ($customers->getUrlRange(max(1, $customers->currentPage() - 2), min($customers->lastPage(), $customers->currentPage() + 2)) as $page => $url)
                        <a href="{{ $url }}" class="{{ $page === $customers->currentPage() ? 'active' : '' }}">{{ $page }}</a>
                    @endforeach
                    @if ($customers->hasMorePages())
                        <a href="{{ $customers->nextPageUrl() }}">→</a>
                    @else
                        <span>→</span>
                    @endif
                </div>
            </div>
        @else
            <div class="card-body">
                <div class="empty-state"><p>No customers registered yet.</p></div>
            </div>
        @endif
    </div>
@endsection