@extends('admin.layout')

@section('title', 'Simulator')

@section('content')
    <div class="card" style="padding: 20px 24px; margin-bottom: 20px">
        <div style="display: flex; align-items: center; gap: 14px">
            <div style="width:42px;height:42px;background:#252c65;border-radius:4px;display:flex;align-items:center;justify-content:center;font-size:20px;color:#fff">💬</div>
            <div>
                <h3 style="font-size:15px;font-weight:600;color:#333">WhatsApp Chat Simulator</h3>
                <p style="font-size:13px;color:#888">Test the bot conversation flow without a real WhatsApp account</p>
            </div>
        </div>
    </div>

    <div class="sim-frame-wrap">
        <iframe src="{{ url('/') }}" title="WhatsApp Simulator" loading="eager" sandbox="allow-scripts allow-forms allow-same-origin"></iframe>
    </div>
@endsection
