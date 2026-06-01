<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Sign In — Magetsi WhatsApp</title>
    <link rel="icon" href="https://magetsi.co.zw/img/Magetsi%20Logo-08.svg">
    <link href="https://fonts.googleapis.com/css2?family=Rubik:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    @if (file_exists(public_path('build/manifest.json')) || file_exists(public_path('hot')))
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    @endif
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'Rubik', system-ui, -apple-system, sans-serif;
            background: #f5f5f5;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 24px;
        }
        .login-wrap {
            width: 100%;
            max-width: 420px;
        }
        .login-card {
            background: #fff;
            border-radius: 6px;
            box-shadow: 0 1px 4px rgba(0,0,0,.08);
            overflow: hidden;
        }
        .login-header {
            background: #252c65;
            padding: 32px 32px 28px;
            text-align: center;
            color: #fff;
        }
        .login-header .logo-icon {
            width: 56px; height: 56px;
            background: rgba(255,255,255,.15);
            border-radius: 6px;
            display: flex; align-items: center; justify-content: center;
            padding: 10px; margin: 0 auto 12px;
        }
        .login-header h1 { font-size: 22px; font-weight: 700; }
        .login-header p { font-size: 14px; opacity: .85; margin-top: 4px; }
        .login-body { padding: 32px; }
        .field-group { margin-bottom: 20px; }
        .field-group label {
            display: block; font-size: 12px; font-weight: 600;
            color: #333; margin-bottom: 6px; text-transform: uppercase;
            letter-spacing: .03em;
        }
        .field-group .input-wrap {
            position: relative;
        }
        .field-group .input-wrap .input-icon {
            position: absolute; left: 14px; top: 50%; transform: translateY(-50%);
            color: #aaa; font-size: 16px;
        }
        .field-group input {
            width: 100%; padding: 12px 14px 12px 42px;
            border: 1px solid #ddd; border-radius: 4px;
            font-size: 14px; font-family: inherit; outline: none;
            color: #333; transition: all .15s;
            background: #fff;
        }
        .field-group input:focus {
            border-color: #f05127;
            box-shadow: 0 0 0 3px rgba(240,81,39,.1);
        }
        .field-group input::placeholder { color: #aaa; }
        .login-btn {
            width: 100%; padding: 12px;
            border: none; border-radius: 4px;
            font-size: 14px; font-weight: 600; font-family: inherit;
            background: #252c65;
            color: #fff; cursor: pointer;
            transition: all .15s;
        }
        .login-btn:hover { background: #1e2555; }
        .login-btn:active { transform: translateY(0); }
        .login-footer {
            text-align: center; padding: 20px 32px;
            border-top: 1px solid #eee;
            font-size: 12px; color: #888;
        }
        .login-footer a { color: #252c65; text-decoration: none; font-weight: 500; }
        .login-footer a:hover { text-decoration: underline; }
        .alert {
            padding: 12px 16px; border-radius: 4px; font-size: 13px;
            margin-bottom: 20px; text-align: center;
        }
        .alert-error {
            background: #fce4e8; color: #c72a48; border: 1px solid #f8ced6;
        }
        .alert-success {
            background: #e2f9ed; color: #1a966e; border: 1px solid #b8f0cf;
        }
        .error-text { font-size: 12px; color: #f5365c; margin-top: 4px; }
    </style>
</head>
<body>
    <div class="login-wrap">
        <div class="login-card">
            <div class="login-header">
                <div class="logo-icon">
                    <svg viewBox="0 0 1804 1040" style="width:100%;height:100%;fill:#fff">
                        <polygon points="231.57 73.88 500.48 74.88 327.05 441.94 418 455.99 28.6 1011.65 162.76 579.14 55.64 562.59 231.57 73.88"/>
                        <path d="M1513.2,53.15c-137.64,0-268.2,59.77-351.33,153.94-34-96-125-153.94-246.33-153.94-110.48,0-213.47,52.52-284.22,135.83l34.89-114.1h-118L390.79,408.07,493,423.91,81.39,1011.23H379.94L546.05,467.89c22.15-72.44,92.54-125,170.41-125s115,56.14,90.69,135.83L643.8,1013H977.05l166.66-545.15c22.15-72.44,92.54-125,170.42-125s115,56.14,90.68,135.83L1241.47,1013h333.24l182.73-597.67C1822.32,203.47,1708.8,53.15,1513.2,53.15Z"/>
                    </svg>
                </div>
                <h1>Magetsi WhatsApp</h1>
                <p>Sign in to the admin panel</p>
            </div>

            <div class="login-body">
                @if ($errors->any())
                    <div class="alert alert-error">
                        {{ $errors->first('email') ?: 'Invalid credentials. Please try again.' }}
                    </div>
                @endif

                @if (session('status'))
                    <div class="alert alert-success">{{ session('status') }}</div>
                @endif

                <form method="POST" action="{{ route('admin.login.submit') }}">
                    @csrf

                    <div class="field-group">
                        <label for="email">Email</label>
                        <div class="input-wrap">
                            <span class="input-icon">✉</span>
                            <input type="email" name="email" id="email"
                                   value="{{ old('email') }}" placeholder="admin@magetsi.co.zw"
                                   required autofocus autocomplete="email">
                        </div>
                    </div>

                    <div class="field-group">
                        <label for="password">Password</label>
                        <div class="input-wrap">
                            <span class="input-icon">🔒</span>
                            <input type="password" name="password" id="password"
                                   placeholder="••••••••" required autocomplete="current-password">
                        </div>
                    </div>

                    <div style="margin-bottom:20px;display:flex;align-items:center;gap:8px">
                        <input type="checkbox" name="remember" id="remember"
                               style="width:16px;height:16px;accent-color:#f05127">
                        <label for="remember" style="font-size:13px;color:#67748e;cursor:pointer">Remember me</label>
                    </div>

                    <button type="submit" class="login-btn">Sign In</button>
                </form>
            </div>

            <div class="login-footer">
                &copy; {{ date('Y') }} Magetsi WhatsApp · v1.0.0
            </div>
        </div>
    </div>
</body>
</html>
