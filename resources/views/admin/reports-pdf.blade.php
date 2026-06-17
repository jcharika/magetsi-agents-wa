<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<title>Transaction Reports</title>
<style>
    body { font-family: DejaVu Sans, sans-serif; font-size: 9px; color: #333; }
    h1 { font-size: 16px; color: #252c65; margin-bottom: 4px; }
    .subtitle { color: #888; font-size: 10px; margin-bottom: 16px; }
    table { width: 100%; border-collapse: collapse; }
    th { background: #252c65; color: #fff; padding: 6px 8px; text-align: left; font-size: 8px; text-transform: uppercase; letter-spacing: 0.5px; }
    td { padding: 5px 8px; border-bottom: 1px solid #eee; }
    tr:nth-child(even) td { background: #f9f9f9; }
    .status { padding: 2px 6px; border-radius: 3px; font-size: 8px; font-weight: 600; display: inline-block; }
    .status-completed { background: #e6f7e6; color: #1a7d1a; }
    .status-failed { background: #fde8e8; color: #c41e1e; }
    .status-pending { background: #fff3cd; color: #856404; }
    .text-muted { color: #999; }
    .fw-600 { font-weight: 600; }
    .mono { font-family: DejaVu Sans Mono, monospace; font-size: 8px; }
</style>
</head>
<body>
    <h1>Transaction Reports</h1>
    <div class="subtitle">Generated {{ now()->format('d M Y H:i') }} &middot; {{ $transactions->count() }} records</div>
    <table>
        <thead>
            <tr>
                <th>Date</th>
                <th>Agent</th>
                <th>Meter</th>
                <th>Customer</th>
                <th>Amount</th>
                <th>Currency</th>
                <th>EcoCash</th>
                <th>Reference</th>
                <th>Token</th>
                <th>Status</th>
                <th>Failure Reason</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($transactions as $txn)
                @php $reason = $txn->failureReason(); @endphp
                <tr>
                    <td>{{ $txn->created_at->format('d M Y H:i') }}</td>
                    <td class="fw-600">{{ $txn->agent?->name ?? ($txn->customer?->name ?? '—') }}</td>
                    <td class="mono">{{ $txn->meter_number }}</td>
                    <td>{{ $txn->customer_name ?? '—' }}</td>
                    <td class="fw-600">{{ number_format((float) $txn->amount, 2) }}</td>
                    <td>{{ $txn->currency }}</td>
                    <td>{{ $txn->ecocash_number ?? '—' }}</td>
                    <td class="mono">{{ $txn->reference ?? '—' }}</td>
                    <td class="mono">{{ $txn->token ?? '—' }}</td>
                    <td><span class="status status-{{ $txn->status }}">{{ ucfirst($txn->status) }}</span></td>
                    <td style="color:#c41e1e">{{ $reason ?? '—' }}</td>
                </tr>
            @empty
                <tr><td colspan="11" style="text-align:center;padding:20px;color:#888">No records found.</td></tr>
            @endforelse
        </tbody>
    </table>
</body>
</html>
