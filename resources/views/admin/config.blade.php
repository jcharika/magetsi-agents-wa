@extends('admin.layout')

@section('title', 'Configuration')

@section('content')
    <div class="card" style="padding: 20px 24px; margin-bottom: 20px">
        <div style="display: flex; align-items: center; gap: 14px">
            <div style="width:42px;height:42px;background:#252c65;border-radius:4px;display:flex;align-items:center;justify-content:center;font-size:20px;color:#fff">⚙️</div>
            <div>
                <h3 style="font-size:15px;font-weight:600;color:#333">Configuration</h3>
                <p style="font-size:13px;color:#888">Manage environment variables — changes are written to <code style="background:#f5f5f5;padding:2px 6px;border-radius:4px;font-size:12px">.env</code></p>
            </div>
        </div>
    </div>

    <div class="card" style="padding: 28px 32px">
        <form method="POST" action="{{ route('admin.config.update') }}">
            @csrf

            @foreach ($groups as $groupName => $fields)
                <div class="config-group">
                    <h3>{{ $groupName }}</h3>
                    <div class="config-grid">
                        @foreach ($fields as $key => $field)
                            @php
                                $currentValue = $parsed[$key] ?? env($key, '');
                            @endphp
                            <div class="config-field">
                                <label for="{{ $key }}">{{ $field['label'] }}</label>

                                @if ($field['type'] === 'select')
                                    <select name="{{ $key }}" id="{{ $key }}" class="select">
                                        @foreach ($field['options'] as $option)
                                            <option value="{{ $option }}" {{ $currentValue === $option ? 'selected' : '' }}>
                                                {{ $option }}
                                            </option>
                                        @endforeach
                                    </select>
                                @elseif ($field['type'] === 'password')
                                    <input type="password" name="{{ $key }}" id="{{ $key }}"
                                           class="input" value="{{ $currentValue }}" autocomplete="off">
                                @elseif ($field['type'] === 'number')
                                    <input type="number" name="{{ $key }}" id="{{ $key }}"
                                           class="input" value="{{ $currentValue }}">
                                @else
                                    <input type="text" name="{{ $key }}" id="{{ $key }}"
                                           class="input" value="{{ $currentValue }}">
                                @endif

                                <div class="hint">{{ $key }}</div>
                            </div>
                        @endforeach
                    </div>
                </div>
            @endforeach

            <div style="margin-top: 24px; padding-top: 24px; border-top: 1px solid #f0f2f8; display: flex; align-items: center; gap: 16px">
                <button type="submit" class="btn btn-primary">Save Configuration</button>
                <span style="font-size: 13px; color: #8392ab">Changes are written to <code style="background:#f0f2f8;padding:2px 6px;border-radius:6px">.env</code></span>
            </div>
        </form>
    </div>
@endsection
