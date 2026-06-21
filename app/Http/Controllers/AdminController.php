<?php

namespace App\Http\Controllers;

use App\Models\Agent;
use App\Models\Customer;
use App\Models\Transaction;
use App\Models\User;
use Dompdf\Dompdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;

class AdminController extends Controller
{
    public function dashboard()
    {
        $totalAgents = Agent::count();
        $onboardedAgents = Agent::where('onboarded', true)->count();
        $totalTransactions = Transaction::count();
        $completedTransactions = Transaction::where('status', 'completed')->count();
        $failedTransactions = Transaction::where('status', 'failed')->count();
        $pendingTransactions = Transaction::where('status', 'pending')->count();
        $successRate = $totalTransactions > 0
            ? round(($completedTransactions / $totalTransactions) * 100, 1)
            : 0;
        $totalRevenue = Transaction::where('status', 'completed')
            ->sum('amount');

        $txnSort = in_array(request('txn_sort'), ['amount', 'status', 'created_at']) ? request('txn_sort') : 'created_at';
        $txnDir = request('txn_dir') === 'asc' ? 'asc' : 'desc';
        $recentTransactions = Transaction::with('agent')
            ->orderBy($txnSort, $txnDir)
            ->take(10)
            ->get();

        $agentSort = in_array(request('agent_sort'), ['name', 'created_at']) ? request('agent_sort') : 'created_at';
        $agentDir = request('agent_dir') === 'asc' ? 'asc' : 'desc';
        $recentAgents = Agent::orderBy($agentSort, $agentDir)->take(5)->get();

        return view('admin.dashboard', compact(
            'totalAgents', 'onboardedAgents',
            'totalTransactions', 'completedTransactions',
            'failedTransactions', 'pendingTransactions',
            'successRate', 'totalRevenue',
            'recentTransactions', 'recentAgents'
        ));
    }

    public function config()
    {
        $envPath = base_path('.env');
        $envContent = File::exists($envPath) ? File::get($envPath) : '';

        $parsed = $this->parseEnv($envContent);

        $tab = request('tab', 'global');

        $globalGroups = [
            'App' => [
                'APP_NAME' => ['label' => 'App Name', 'type' => 'text'],
                'APP_ENV' => ['label' => 'Environment', 'type' => 'select', 'options' => ['local', 'production', 'testing']],
                'APP_DEBUG' => ['label' => 'Debug Mode', 'type' => 'select', 'options' => ['true', 'false']],
                'APP_URL' => ['label' => 'App URL', 'type' => 'text'],
            ],
            'New Backend' => [
                'MAGETSI_BACKEND' => ['label' => 'Backend', 'type' => 'select', 'options' => ['legacy', 'new']],
                'MAGETSI_API_URL' => ['label' => 'API URL', 'type' => 'text'],
                'MAGETSI_CHANNEL' => ['label' => 'Channel', 'type' => 'text'],
                'MAGETSI_API_TIMEOUT' => ['label' => 'API Timeout (s)', 'type' => 'number'],
            ],
            'Legacy Backend' => [
                'MAGETSI_LEGACY_URL' => ['label' => 'Legacy URL', 'type' => 'text'],
                'MAGETSI_LEGACY_TOKEN' => ['label' => 'Legacy Token', 'type' => 'password'],
                'MAGETSI_LEGACY_EMAIL' => ['label' => 'Legacy Email', 'type' => 'text'],
                'MAGETSI_LEGACY_POLL_ATTEMPTS' => ['label' => 'Poll Attempts', 'type' => 'number'],
                'MAGETSI_LEGACY_POLL_INTERVAL' => ['label' => 'Poll Interval (ms)', 'type' => 'number'],
            ],
        ];

        $agentGroups = [
            'WhatsApp Cloud API' => [
                'WHATSAPP_TOKEN' => ['label' => 'Access Token', 'type' => 'password'],
                'WHATSAPP_PHONE_NUMBER_ID' => ['label' => 'Phone Number ID', 'type' => 'text'],
                'WHATSAPP_VERIFY_TOKEN' => ['label' => 'Verify Token', 'type' => 'text'],
                'WHATSAPP_BUSINESS_ACCOUNT_ID' => ['label' => 'Business Account ID', 'type' => 'text'],
            ],
            'WhatsApp Flows' => [
                'WHATSAPP_BUY_ZESA_FLOW_ID' => ['label' => 'Buy ZESA Flow ID', 'type' => 'text'],
                'WHATSAPP_SETTINGS_FLOW_ID' => ['label' => 'Settings Flow ID', 'type' => 'text'],
                'WHATSAPP_FLOW_MODE' => ['label' => 'Flow Mode', 'type' => 'select', 'options' => ['interactive', 'template']],
                'WHATSAPP_BUY_ZESA_TEMPLATE' => ['label' => 'Buy ZESA Template', 'type' => 'text'],
                'WHATSAPP_SETTINGS_TEMPLATE' => ['label' => 'Settings Template', 'type' => 'text'],
                'WHATSAPP_TEMPLATE_LANGUAGE' => ['label' => 'Template Language', 'type' => 'text'],
                'META_APP_SECRET' => ['label' => 'Meta App Secret', 'type' => 'password'],
                'WHATSAPP_FLOW_PRIVATE_KEY_PATH' => ['label' => 'Private Key Path', 'type' => 'text'],
            ],
        ];

        $customerGroups = [
            'WhatsApp Cloud API' => [
                'WHATSAPP_CUSTOMER_TOKEN' => ['label' => 'Access Token', 'type' => 'password'],
                'WHATSAPP_CUSTOMER_PHONE_NUMBER_ID' => ['label' => 'Phone Number ID', 'type' => 'text'],
                'WHATSAPP_CUSTOMER_VERIFY_TOKEN' => ['label' => 'Verify Token', 'type' => 'text'],
                'WHATSAPP_CUSTOMER_BUSINESS_ACCOUNT_ID' => ['label' => 'Business Account ID', 'type' => 'text'],
            ],
            'WhatsApp Flows' => [
                'WHATSAPP_CUSTOMER_FLOW_ID' => ['label' => 'Customer Flow ID', 'type' => 'text'],
                'WHATSAPP_CUSTOMER_FLOW_MODE' => ['label' => 'Flow Mode', 'type' => 'select', 'options' => ['interactive']],
                'WHATSAPP_CUSTOMER_APP_SECRET' => ['label' => 'Meta App Secret', 'type' => 'password'],
            ],
            'Services' => [
                'WHATSAPP_CUSTOMER_SERVICE_ZESA' => ['label' => 'ZESA Tokens', 'type' => 'select', 'options' => ['true', 'false']],
                'WHATSAPP_CUSTOMER_SERVICE_AIRTIME' => ['label' => 'Airtime', 'type' => 'select', 'options' => ['true', 'false']],
                'WHATSAPP_CUSTOMER_SERVICE_BUNDLES' => ['label' => 'Data Bundles', 'type' => 'select', 'options' => ['true', 'false']],
                'WHATSAPP_CUSTOMER_SERVICE_TELONE' => ['label' => 'TelOne WiFi', 'type' => 'select', 'options' => ['true', 'false']],
                'WHATSAPP_CUSTOMER_SERVICE_BILLERS' => ['label' => 'Billers', 'type' => 'select', 'options' => ['true', 'false']],
                'WHATSAPP_CUSTOMER_SERVICE_SUPPORT' => ['label' => 'Support', 'type' => 'select', 'options' => ['true', 'false']],
            ],
        ];

        return view('admin.config', compact('globalGroups', 'agentGroups', 'customerGroups', 'parsed', 'tab'));
    }

    public function configUpdate(Request $request)
    {
        $envPath = base_path('.env');
        $content = File::exists($envPath) ? File::get($envPath) : '';

        $keys = [
            'APP_NAME', 'APP_ENV', 'APP_DEBUG', 'APP_URL',
            'WHATSAPP_TOKEN', 'WHATSAPP_PHONE_NUMBER_ID', 'WHATSAPP_VERIFY_TOKEN', 'WHATSAPP_BUSINESS_ACCOUNT_ID',
            'WHATSAPP_BUY_ZESA_FLOW_ID', 'WHATSAPP_SETTINGS_FLOW_ID', 'WHATSAPP_FLOW_MODE',
            'WHATSAPP_BUY_ZESA_TEMPLATE', 'WHATSAPP_SETTINGS_TEMPLATE', 'WHATSAPP_TEMPLATE_LANGUAGE',
            'META_APP_SECRET', 'WHATSAPP_FLOW_PRIVATE_KEY_PATH',
            'MAGETSI_BACKEND', 'MAGETSI_API_URL', 'MAGETSI_CHANNEL', 'MAGETSI_API_TIMEOUT',
            'MAGETSI_LEGACY_URL', 'MAGETSI_LEGACY_TOKEN', 'MAGETSI_LEGACY_EMAIL',
            'MAGETSI_LEGACY_POLL_ATTEMPTS', 'MAGETSI_LEGACY_POLL_INTERVAL',
            'WHATSAPP_CUSTOMER_TOKEN', 'WHATSAPP_CUSTOMER_PHONE_NUMBER_ID', 'WHATSAPP_CUSTOMER_VERIFY_TOKEN',
            'WHATSAPP_CUSTOMER_BUSINESS_ACCOUNT_ID',
            'WHATSAPP_CUSTOMER_FLOW_ID',
            'WHATSAPP_CUSTOMER_FLOW_MODE', 'WHATSAPP_CUSTOMER_APP_SECRET',
            'WHATSAPP_CUSTOMER_SERVICE_ZESA', 'WHATSAPP_CUSTOMER_SERVICE_AIRTIME', 'WHATSAPP_CUSTOMER_SERVICE_BUNDLES',
            'WHATSAPP_CUSTOMER_SERVICE_TELONE', 'WHATSAPP_CUSTOMER_SERVICE_BILLERS', 'WHATSAPP_CUSTOMER_SERVICE_SUPPORT',
        ];

        $lines = explode("\n", $content);

        foreach ($keys as $key) {
            $value = $request->input($key);
            if ($value === null) continue;

            if (preg_match('/[\s#\\\\\'"]/', $value) || $value === '') {
                $value = '"' . str_replace(['\\', '"'], ['\\\\', '\\"'], $value) . '"';
            }

            $found = false;
            foreach ($lines as &$line) {
                if (preg_match('/^' . preg_quote($key, '/') . '=/', $line)) {
                    $line = $key . '=' . $value;
                    $found = true;
                    break;
                }
            }

            if (!$found) {
                $lines[] = $key . '=' . $value;
            }
        }

        $result = File::put($envPath, implode("\n", $lines));

        if (function_exists('opcache_reset')) {
            opcache_reset();
        }

        Artisan::call('optimize');

        return redirect()->route('admin.config')
            ->with('status', $result ? 'Configuration saved successfully.' : 'Failed to save configuration.');
    }

    protected function buildReportsQuery(Request $request)
    {
        $query = Transaction::with('agent', 'customer');

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('meter_number', 'like', "%{$search}%")
                  ->orWhere('customer_name', 'like', "%{$search}%")
                  ->orWhere('reference', 'like', "%{$search}%")
                  ->orWhere('ecocash_number', 'like', "%{$search}%");
            });
        }

        if ($request->filled('product_id')) {
            $query->where('product_id', $request->product_id);
        }

        if ($request->filled('handler')) {
            $query->where('handler', $request->handler);
        }

        if ($request->filled('agent_id')) {
            $query->where('agent_id', $request->agent_id);
        }

        if ($request->filled('customer_id')) {
            $query->where('customer_id', $request->customer_id);
        }

        if ($request->filled('currency')) {
            $query->where('currency', $request->currency);
        }

        if ($request->filled('amount_min')) {
            $query->where('amount', '>=', (float) $request->amount_min);
        }

        if ($request->filled('amount_max')) {
            $query->where('amount', '<=', (float) $request->amount_max);
        }

        if ($request->filled('date_from')) {
            $query->whereDate('created_at', '>=', $request->date_from);
        }

        if ($request->filled('date_to')) {
            $query->whereDate('created_at', '<=', $request->date_to);
        }

        return $query;
    }

    public function reports(Request $request)
    {
        $query = $this->buildReportsQuery($request);

        $sortField = $request->get('sort', 'created_at');
        $sortDir = $request->get('dir', 'desc');
        $allowedSorts = ['created_at', 'amount', 'status', 'customer_name', 'meter_number'];

        if (in_array($sortField, $allowedSorts)) {
            $query->orderBy($sortField, $sortDir === 'asc' ? 'asc' : 'desc');
        }

        $transactions = $query->paginate(20)->withQueryString();

        $agents = Agent::orderBy('name')->get(['id', 'name']);
        $customers = Customer::orderBy('name')->get(['id', 'name']);
        $products = Transaction::select('product_id')->distinct()->whereNotNull('product_id')->pluck('product_id');
        $handlers = Transaction::select('handler')->distinct()->whereNotNull('handler')->pluck('handler');
        $currencies = Transaction::select('currency')->distinct()->whereNotNull('currency')->pluck('currency');

        return view('admin.reports', compact('transactions', 'agents', 'customers', 'products', 'handlers', 'currencies'));
    }

    public function reportsExportCsv(Request $request)
    {
        $query = $this->buildReportsQuery($request);
        $query->orderBy('created_at', 'desc');

        $transactions = $query->get();

        $filename = 'reports-' . now()->format('Y-m-d-His') . '.csv';

        $headers = [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ];

        $callback = function () use ($transactions) {
            $handle = fopen('php://output', 'w');

            // BOM for Excel UTF-8 support
            fwrite($handle, "\xEF\xBB\xBF");

            fputcsv($handle, [
                'Date', 'Agent', 'Customer Type', 'Meter', 'Customer', 'Amount', 'Currency',
                'EcoCash', 'Reference', 'Token', 'Status', 'Failure Reason',
            ]);

            foreach ($transactions as $txn) {
                fputcsv($handle, [
                    $txn->created_at->format('d M Y H:i'),
                    $txn->agent?->name ?? '—',
                    $txn->customer_id ? 'Customer' : ($txn->agent_id ? 'Agent' : '—'),
                    $txn->meter_number,
                    $txn->customer_name ?? '—',
                    number_format((float) $txn->amount, 2),
                    $txn->currency,
                    $txn->ecocash_number ?? '—',
                    $txn->reference ?? '—',
                    $txn->token ?? '—',
                    $txn->status,
                    $txn->status === 'failed' ? ($txn->failureReason() ?? '—') : '—',
                ]);
            }

            fclose($handle);
        };

        return response()->stream($callback, 200, $headers);
    }

    public function reportsExportPdf(Request $request)
    {
        $query = $this->buildReportsQuery($request);
        $query->orderBy('created_at', 'desc');

        $transactions = $query->get();

        $html = view('admin.reports-pdf', compact('transactions'))->render();

        $dompdf = new Dompdf;
        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4', 'landscape');
        $dompdf->render();

        $filename = 'reports-' . now()->format('Y-m-d-His') . '.pdf';

        return response($dompdf->output(), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ]);
    }

    public function help()
    {
        $docsPath = base_path('docs');
        $files = [];

        if (File::isDirectory($docsPath)) {
            $mdFiles = File::glob($docsPath . '/*.md');
            sort($mdFiles);

            foreach ($mdFiles as $path) {
                $name = pathinfo($path, PATHINFO_FILENAME);
                $content = File::get($path);
                $title = $this->extractTitle($content);
                $html = $this->markdownToHtml($content);

                $files[] = [
                    'name' => $name,
                    'title' => $title ?: ucfirst(str_replace('-', ' ', $name)),
                    'content' => $content,
                    'html' => $html,
                ];
            }
        }

        return view('admin.help', compact('files'));
    }

    public function simulator()
    {
        return view('admin.simulator');
    }

    public function agents()
    {
        $sortField = request('sort', 'created_at');
        $sortDir = request('dir', 'desc');
        $allowedSorts = ['name', 'phone', 'created_at', 'transactions_count', 'transactions_sum_amount'];

        $agents = Agent::withCount([
            'transactions',
            'transactions as completed_transactions_count' => fn($q) => $q->where('status', 'completed'),
            'transactions as failed_transactions_count' => fn($q) => $q->where('status', 'failed'),
        ])->withSum('transactions', 'amount');

        if ($search = request('search')) {
            $agents->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('phone', 'like', "%{$search}%")
                  ->orWhere('ecocash_number', 'like', "%{$search}%");
            });
        }

        if (in_array($sortField, $allowedSorts)) {
            $agents->orderBy($sortField, $sortDir === 'asc' ? 'asc' : 'desc');
        } else {
            $agents->latest();
        }

        $agents = $agents->paginate(20);

        return view('admin.agents', compact('agents'));
    }

    public function agentDetail(Agent $agent)
    {
        $agent->loadCount('transactions');
        $stats = [
            'total_transactions' => $agent->transactions()->count(),
            'completed_transactions' => $agent->transactions()->where('status', 'completed')->count(),
            'failed_transactions' => $agent->transactions()->where('status', 'failed')->count(),
            'total_revenue' => $agent->transactions()->where('status', 'completed')->sum('amount'),
            'last_transaction_at' => $agent->transactions()->latest()->value('created_at'),
        ];

        $sortField = request('sort', 'created_at');
        $sortDir = request('dir', 'desc');
        $allowedSorts = ['created_at', 'amount', 'status'];

        $txnQuery = $agent->transactions();
        if (in_array($sortField, $allowedSorts)) {
            $txnQuery->orderBy($sortField, $sortDir === 'asc' ? 'asc' : 'desc');
        } else {
            $txnQuery->latest();
        }
        $transactions = $txnQuery->paginate(20);

        return view('admin.agent-detail', compact('agent', 'stats', 'transactions'));
    }

    public function agentToggleBlock(Agent $agent)
    {
        $agent->update(['blocked' => !$agent->blocked]);

        return back()->with('status', $agent->blocked
            ? "Agent {$agent->name} has been blocked."
            : "Agent {$agent->name} has been unblocked.");
    }

    public function users()
    {
        $sortField = request('sort', 'created_at');
        $sortDir = request('dir', 'desc');
        $allowedSorts = ['name', 'email', 'created_at', 'updated_at'];

        $users = User::query();
        if (in_array($sortField, $allowedSorts)) {
            $users->orderBy($sortField, $sortDir === 'asc' ? 'asc' : 'desc');
        } else {
            $users->latest();
        }

        $users = $users->paginate(20);

        return view('admin.users', compact('users'));
    }

    public function userEdit(User $user)
    {
        return response()->json([
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'blocked' => $user->blocked,
        ]);
    }

    public function userUpdate(Request $request, User $user)
    {
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|max:255|unique:users,email,' . $user->id,
        ]);

        $user->update($data);

        return redirect()->route('admin.users')
            ->with('status', "User {$user->name} updated successfully.");
    }

    public function userPassword(Request $request, User $user)
    {
        $data = $request->validate([
            'password' => 'required|string|min:8|confirmed',
        ]);

        $user->update(['password' => $data['password']]);

        return redirect()->route('admin.users')
            ->with('status', "Password for {$user->name} updated successfully.");
    }

    public function userToggleBlock(User $user)
    {
        if ($user->id === auth()->id()) {
            return back()->with('status', 'You cannot block yourself.');
        }

        $user->update(['blocked' => !$user->blocked]);

        return back()->with('status', $user->blocked
            ? "User {$user->name} has been blocked."
            : "User {$user->name} has been unblocked.");
    }

    public function customers()
    {
        $sortField = request('sort', 'created_at');
        $sortDir = request('dir', 'desc');
        $allowedSorts = ['name', 'phone', 'created_at', 'transactions_count'];

        $customers = Customer::withCount('transactions');

        if (in_array($sortField, $allowedSorts)) {
            $customers->orderBy($sortField, $sortDir === 'asc' ? 'asc' : 'desc');
        } else {
            $customers->latest();
        }

        $customers = $customers->paginate(20);

        return view('admin.customers', compact('customers'));
    }

    public function customerDetail(Customer $customer)
    {
        $customer->loadCount('transactions');
        $stats = [
            'total_transactions' => $customer->transactions()->count(),
            'completed_transactions' => $customer->transactions()->where('status', 'completed')->count(),
            'failed_transactions' => $customer->transactions()->where('status', 'failed')->count(),
            'total_revenue' => $customer->transactions()->where('status', 'completed')->sum('amount'),
            'last_transaction_at' => $customer->transactions()->latest()->value('created_at'),
        ];

        $sortField = request('sort', 'created_at');
        $sortDir = request('dir', 'desc');
        $allowedSorts = ['created_at', 'amount', 'status'];

        $txnQuery = $customer->transactions();
        if (in_array($sortField, $allowedSorts)) {
            $txnQuery->orderBy($sortField, $sortDir === 'asc' ? 'asc' : 'desc');
        } else {
            $txnQuery->latest();
        }
        $transactions = $txnQuery->paginate(20);

        return view('admin.customer-detail', compact('customer', 'stats', 'transactions'));
    }

    private function parseEnv(string $content): array
    {
        $parsed = [];
        $lines = explode("\n", $content);

        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '' || str_starts_with($line, '#')) continue;

            if (preg_match('/^([A-Z_][A-Z0-9_]*)=(.*)$/', $line, $m)) {
                $value = $m[2];
                if (str_starts_with($value, '"') && str_ends_with($value, '"')) {
                    $value = substr($value, 1, -1);
                    $value = str_replace(['\\"', '\\\\'], ['"', '\\'], $value);
                }
                $parsed[$m[1]] = $value;
            }
        }

        return $parsed;
    }

    private function extractTitle(string $markdown): ?string
    {
        if (preg_match('/^#\s+(.+)$/m', $markdown, $m)) {
            return trim($m[1]);
        }
        return null;
    }

    private function markdownToHtml(string $markdown): string
    {
        $html = htmlspecialchars($markdown, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

        $html = preg_replace('/^### (.+)$/m', '<h3>$1</h3>', $html);
        $html = preg_replace('/^## (.+)$/m', '<h2>$1</h2>', $html);
        $html = preg_replace('/^# (.+)$/m', '<h1>$1</h1>', $html);
        $html = preg_replace('/\*\*(.+?)\*\*/', '<strong>$1</strong>', $html);
        $html = preg_replace('/`([^`]+)`/', '<code>$1</code>', $html);
        $html = preg_replace('/^\- (.+)$/m', '<li>$1</li>', $html);
        $html = preg_replace('/(<li>.*<\/li>\n?)+/s', '<ul>$0</ul>', $html);
        $html = preg_replace('/\|(.+)\|/', '<code>$1</code>', $html);

        return $html;
    }
}
