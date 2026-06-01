@extends('admin.layout')

@section('title', 'Help & Documentation')

@section('content')
    <div class="card" style="padding: 28px 32px; margin-bottom: 24px">
        <div style="display: flex; align-items: center; gap: 14px">
            <div style="width:42px;height:42px;background:#252c65;border-radius:4px;display:flex;align-items:center;justify-content:center;font-size:20px;color:#fff">📖</div>
            <div>
                <h3 style="font-size:15px;font-weight:600;color:#333">Documentation</h3>
                <p style="font-size:13px;color:#888;margin-top:2px">Loaded from <code style="background:#f5f5f5;padding:2px 6px;border-radius:4px;font-size:12px">docs/*.md</code></p>
            </div>
        </div>
    </div>

    @if (count($files) > 0)
        <div class="card" style="padding: 32px">
            <div class="help-nav">
                @foreach ($files as $i => $file)
                    <a href="#doc-{{ $file['name'] }}" class="{{ $i === 0 ? 'active' : '' }}"
                       onclick="event.preventDefault();document.getElementById('doc-{{ $file['name'] }}').scrollIntoView({behavior:'smooth'});this.parentElement.querySelectorAll('a').forEach(a=>a.classList.remove('active'));this.classList.add('active')">
                        {{ $file['title'] }}
                    </a>
                @endforeach
            </div>

            @foreach ($files as $file)
                <div id="doc-{{ $file['name'] }}" class="help-content" style="margin-top: {{ $loop->first ? '0' : '48px' }}">
                    {!! $file['html'] !!}
                </div>
            @endforeach
        </div>
    @else
        <div class="card" style="padding: 48px">
            <div class="empty-state">
                <p>No documentation files found in <code>docs/</code>.</p>
            </div>
        </div>
    @endif
@endsection
