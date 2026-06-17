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
                            <th>
                                <a href="{{ request()->fullUrlWithQuery(['sort' => 'name', 'dir' => request('sort') === 'name' && request('dir') === 'asc' ? 'desc' : 'asc']) }}" style="color:inherit;text-decoration:none">
                                    Name {{ request('sort') === 'name' ? (request('dir') === 'asc' ? '↑' : '↓') : '' }}
                                </a>
                            </th>
                            <th>
                                <a href="{{ request()->fullUrlWithQuery(['sort' => 'email', 'dir' => request('sort') === 'email' && request('dir') === 'asc' ? 'desc' : 'asc']) }}" style="color:inherit;text-decoration:none">
                                    Email {{ request('sort') === 'email' ? (request('dir') === 'asc' ? '↑' : '↓') : '' }}
                                </a>
                            </th>
                            <th>Status</th>
                            <th>
                                <a href="{{ request()->fullUrlWithQuery(['sort' => 'created_at', 'dir' => request('sort') === 'created_at' && request('dir') === 'asc' ? 'desc' : 'asc']) }}" style="color:inherit;text-decoration:none">
                                    Created {{ request('sort') === 'created_at' ? (request('dir') === 'asc' ? '↑' : '↓') : '' }}
                                </a>
                            </th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($users as $user)
                            <tr>
                                <td style="font-weight:600;color:#344767">{{ $user->name }}</td>
                                <td>{{ $user->email }}</td>
                                <td>
                                    <span class="badge {{ $user->blocked ? 'badge-danger' : 'badge-success' }}">
                                        {{ $user->blocked ? 'Blocked' : 'Active' }}
                                    </span>
                                </td>
                                <td style="font-size:12px;white-space:nowrap">{{ $user->created_at->format('d M Y H:i') }}</td>
                                <td style="white-space:nowrap">
                                    <button class="btn btn-outline" style="padding:4px 12px;font-size:12px" onclick="openEdit({{ $user->id }}, '{{ addslashes($user->name) }}', '{{ $user->email }}')">Edit</button>
                                    <button class="btn btn-outline" style="padding:4px 12px;font-size:12px" onclick="openPassword({{ $user->id }}, '{{ addslashes($user->name) }}')">Password</button>
                                    @if ($user->id !== auth()->id())
                                        <form method="POST" action="{{ route('admin.users.toggle-block', $user) }}" style="display:inline">
                                            @csrf
                                            <button type="submit" class="btn {{ $user->blocked ? 'btn-secondary' : 'btn-outline' }}" style="padding:4px 12px;font-size:12px"
                                                    onclick="return confirm('{{ $user->blocked ? 'Unblock' : 'Block' }} {{ $user->name }}?')">
                                                {{ $user->blocked ? 'Unblock' : 'Block' }}
                                            </button>
                                        </form>
                                    @endif
                                </td>
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

    {{-- Edit Modal --}}
    <div id="editModal" class="modal-overlay" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.4);z-index:1000;align-items:center;justify-content:center">
        <div class="card" style="width:420px;padding:24px">
            <h3 style="font-size:15px;font-weight:600;color:#333;margin-bottom:16px">Edit User</h3>
            <form id="editForm" method="POST">
                @csrf
                <div style="margin-bottom:12px">
                    <label for="edit_name" style="display:block;font-size:13px;font-weight:500;color:#555;margin-bottom:4px">Name</label>
                    <input type="text" name="name" id="edit_name" class="input" required>
                </div>
                <div style="margin-bottom:16px">
                    <label for="edit_email" style="display:block;font-size:13px;font-weight:500;color:#555;margin-bottom:4px">Email</label>
                    <input type="email" name="email" id="edit_email" class="input" required>
                </div>
                <div style="display:flex;gap:8px;justify-content:end">
                    <button type="button" class="btn btn-secondary" onclick="closeEdit()">Cancel</button>
                    <button type="submit" class="btn btn-primary">Save</button>
                </div>
            </form>
        </div>
    </div>

    {{-- Password Modal --}}
    <div id="passwordModal" class="modal-overlay" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.4);z-index:1000;align-items:center;justify-content:center">
        <div class="card" style="width:420px;padding:24px">
            <h3 style="font-size:15px;font-weight:600;color:#333;margin-bottom:4px">Change Password</h3>
            <p style="font-size:13px;color:#888;margin-bottom:16px" id="passwordModalUser"></p>
            <form id="passwordForm" method="POST">
                @csrf
                <div style="margin-bottom:12px">
                    <label for="password" style="display:block;font-size:13px;font-weight:500;color:#555;margin-bottom:4px">New Password</label>
                    <input type="password" name="password" id="password" class="input" required minlength="8">
                </div>
                <div style="margin-bottom:16px">
                    <label for="password_confirmation" style="display:block;font-size:13px;font-weight:500;color:#555;margin-bottom:4px">Confirm Password</label>
                    <input type="password" name="password_confirmation" id="password_confirmation" class="input" required>
                </div>
                <div style="display:flex;gap:8px;justify-content:end">
                    <button type="button" class="btn btn-secondary" onclick="closePassword()">Cancel</button>
                    <button type="submit" class="btn btn-primary">Update Password</button>
                </div>
            </form>
        </div>
    </div>

    <script>
    function openEdit(id, name, email) {
        document.getElementById('editForm').action = '/admin/users/' + id + '/update';
        document.getElementById('edit_name').value = name;
        document.getElementById('edit_email').value = email;
        document.getElementById('editModal').style.display = 'flex';
    }
    function closeEdit() {
        document.getElementById('editModal').style.display = 'none';
    }
    function openPassword(id, name) {
        document.getElementById('passwordForm').action = '/admin/users/' + id + '/password';
        document.getElementById('passwordModalUser').textContent = 'Set a new password for ' + name;
        document.getElementById('password').value = '';
        document.getElementById('password_confirmation').value = '';
        document.getElementById('passwordModal').style.display = 'flex';
    }
    function closePassword() {
        document.getElementById('passwordModal').style.display = 'none';
    }
    document.getElementById('editModal').addEventListener('click', function(e) {
        if (e.target === this) closeEdit();
    });
    document.getElementById('passwordModal').addEventListener('click', function(e) {
        if (e.target === this) closePassword();
    });
    </script>
@endsection