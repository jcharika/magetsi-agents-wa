<?php

namespace App\Http\Controllers;

use App\Models\Agent;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Http\Request;
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
        $recentTransactions = Transaction::with('agent')
            ->latest()
            ->take(10)
            ->get();
        $recentAgents = Agent::latest()->take(5)->get();

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

        $groups = [
            'App' => [
                'APP_NAME' => ['label' => 'App Name', 'type' => 'text'],
                'APP_ENV' => ['label' => 'Environment', 'type' => 'select', 'options' => ['local', 'production', 'testing']],
                'APP_DEBUG' => ['label' => 'Debug Mode', 'type' => 'select', 'options' => ['true', 'false']],
                'APP_URL' => ['label' => 'App URL', 'type' => 'text'],
            ],
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

        $parsed = $this->parseEnv($envContent);

        return view('admin.config', compact('groups', 'parsed'));
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

        return redirect()->route('admin.config')
            ->with('status', $result ? 'Configuration saved successfully.' : 'Failed to save configuration.');
    }

    public function reports(Request $request)
    {
        $query = Transaction::with('agent');

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

        if ($request->filled('date_from')) {
            $query->whereDate('created_at', '>=', $request->date_from);
        }

        if ($request->filled('date_to')) {
            $query->whereDate('created_at', '<=', $request->date_to);
        }

        $sortField = $request->get('sort', 'created_at');
        $sortDir = $request->get('dir', 'desc');
        $allowedSorts = ['created_at', 'amount', 'status', 'customer_name', 'meter_number'];

        if (in_array($sortField, $allowedSorts)) {
            $query->orderBy($sortField, $sortDir === 'asc' ? 'asc' : 'desc');
        }

        $transactions = $query->paginate(20)->withQueryString();

        return view('admin.reports', compact('transactions'));
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
        $agents = Agent::withCount('transactions')
            ->withSum('transactions', 'amount')
            ->latest()
            ->paginate(20);

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

        $transactions = $agent->transactions()->latest()->paginate(20);

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
        $users = User::latest()->paginate(20);
        return view('admin.users', compact('users'));
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
