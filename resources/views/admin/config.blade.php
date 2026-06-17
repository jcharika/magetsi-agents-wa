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

    <div class="card" style="padding: 0 32px 28px">
        <div style="display:flex;gap:0;border-bottom:1px solid #f0f2f8;margin-bottom:24px">
            @php $tabs = ['global' => 'Global Configuration', 'agents' => 'Agent Configuration', 'customer' => 'Customer Configuration']; @endphp
            @foreach ($tabs as $key => $label)
                <a href="{{ route('admin.config', ['tab' => $key]) }}"
                   style="padding:14px 24px;font-size:13px;font-weight:600;text-decoration:none;border-bottom:2px solid {{ $tab === $key ? '#252c65' : 'transparent' }};color:{{ $tab === $key ? '#252c65' : '#8392ab' }};transition:all .15s">
                    {{ $label }}
                </a>
            @endforeach
        </div>

        <form method="POST" action="{{ route('admin.config.update') }}">
            @csrf
            <input type="hidden" name="tab" value="{{ $tab }}">

            @php
                $groups = match ($tab) {
                    'agents' => $agentGroups,
                    'customer' => $customerGroups,
                    default => $globalGroups,
                };
            @endphp

            @forelse ($groups as $groupName => $fields)
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
            @empty
                <p style="color:#8392ab;font-size:13px;text-align:center;padding:32px 0">
                    No configuration fields for this section.
                </p>
            @endforelse

            <div style="margin-top: 24px; padding-top: 24px; border-top: 1px solid #f0f2f8; display: flex; align-items: center; gap: 16px">
                <button type="submit" class="btn btn-primary">Save Configuration</button>
                <span style="font-size: 13px; color: #8392ab">Changes are written to <code style="background:#f0f2f8;padding:2px 6px;border-radius:6px">.env</code></span>
            </div>
        </form>
    </div>
@endsection