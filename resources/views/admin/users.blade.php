@extends('admin.layout')

@section('title', 'Admin Users')

@section('content')
    <div class="card" style="padding: 20px 24px; margin-bottom: 20px">
        <div style="display: flex; align-items: center; gap: 14px">
            <div style="width:42px;height:42px;background:#252c65;border-radius:4px;display:flex;align-items:center;justify-content:center;font-size:20px;color:#fff">👥</div>
            <div>
                <h3 style="font-size:15px;font-weight:600;color:#333">Admin Users</h3>
                <p style="font-size:13px;color:#888">Manage who has access to the admin panel</p>
            </div>
        </div>
    </div>

    <div class="card" style="padding:0">
        @if ($users->count())
            <div class="table-wrap">
                <table class="soft-table">
                    <thead>
                        <tr>
                            <th>Name</th>
                            <th>Email</th>
                            <th>Created</th>
                            <th>Last Updated</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($users as $user)
                            <tr>
                                <td style="font-weight:600;color:#344767">{{ $user->name }}</td>
                                <td>{{ $user->email }}</td>
                                <td style="font-size:12px;white-space:nowrap">{{ $user->created_at->format('d M Y H:i') }}</td>
                                <td style="font-size:12px;white-space:nowrap">{{ $user->updated_at->format('d M Y H:i') }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <div class="card-footer" style="display:flex;justify-content:space-between;align-items:center">
                <span>Showing {{ $users->firstItem() }}–{{ $users->lastItem() }} of {{ $users->total() }}</span>
                <div class="pagination">
                    @if ($users->onFirstPage())
                        <span>←</span>
                    @else
                        <a href="{{ $users->previousPageUrl() }}">←</a>
                    @endif
                    @foreach ($users->getUrlRange(max(1, $users->currentPage() - 2), min($users->lastPage(), $users->currentPage() + 2)) as $page => $url)
                        <a href="{{ $url }}" class="{{ $page === $users->currentPage() ? 'active' : '' }}">{{ $page }}</a>
                    @endforeach
                    @if ($users->hasMorePages())
                        <a href="{{ $users->nextPageUrl() }}">→</a>
                    @else
                        <span>→</span>
                    @endif
                </div>
            </div>
        @else
            <div class="card-body">
                <div class="empty-state"><p>No admin users found.</p></div>
            </div>
        @endif
    </div>
@endsection
