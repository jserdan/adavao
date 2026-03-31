@extends('layouts.app')

@section('title', 'Decryption Tester')

@section('styles')
<style>
    .tester-card {
        background: #ffffff;
        border-radius: 12px;
        box-shadow: 0 2px 8px rgba(0, 0, 0, 0.08);
        padding: 1.5rem;
        margin-bottom: 1rem;
    }

    .tester-title {
        margin: 0 0 0.35rem;
        font-size: 1.25rem;
        color: #111827;
    }

    .tester-subtitle {
        margin: 0 0 1.25rem;
        color: #6b7280;
        font-size: 0.95rem;
    }

    .form-group {
        margin-bottom: 1rem;
    }

    .form-label {
        display: block;
        margin-bottom: 0.35rem;
        font-weight: 600;
        color: #374151;
        font-size: 0.9rem;
    }

    .form-help {
        margin: 0.3rem 0 0;
        color: #6b7280;
        font-size: 0.8rem;
    }

    .form-input,
    .form-textarea {
        width: 100%;
        border: 1px solid #d1d5db;
        border-radius: 8px;
        padding: 0.7rem 0.85rem;
        font-size: 0.95rem;
        color: #111827;
    }

    .form-textarea {
        min-height: 180px;
        resize: vertical;
        font-family: Consolas, Monaco, 'Courier New', monospace;
    }

    .form-input:focus,
    .form-textarea:focus {
        outline: none;
        border-color: #2563eb;
        box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.12);
    }

    .action-row {
        display: flex;
        gap: 0.75rem;
        align-items: center;
        flex-wrap: wrap;
    }

    .btn-primary {
        background: #2563eb;
        border: 0;
        color: #fff;
        border-radius: 8px;
        padding: 0.65rem 1rem;
        font-size: 0.9rem;
        font-weight: 600;
        cursor: pointer;
    }

    .btn-primary:hover {
        background: #1d4ed8;
    }

    .alert {
        border-radius: 8px;
        padding: 0.8rem 0.95rem;
        margin-bottom: 1rem;
        font-size: 0.9rem;
    }

    .alert-danger {
        background: #fef2f2;
        border: 1px solid #fecaca;
        color: #991b1b;
    }

    .result-box {
        background: #f8fafc;
        border: 1px solid #e2e8f0;
        border-radius: 8px;
        padding: 1rem;
    }

    .result-meta {
        margin-bottom: 0.65rem;
        color: #475569;
        font-size: 0.85rem;
    }

    .result-text {
        margin: 0;
        white-space: pre-wrap;
        word-break: break-word;
        font-family: Consolas, Monaco, 'Courier New', monospace;
        color: #0f172a;
        font-size: 0.92rem;
    }
</style>
@endsection

@section('content')
<div class="tester-card">
    <h1 class="tester-title">Decryption Tester</h1>
    <p class="tester-subtitle">Paste encrypted DB value and decrypt it using a supplied APP_KEY or this server's current APP_KEY.</p>

    @if($errors->any())
        <div class="alert alert-danger">
            {{ $errors->first() }}
        </div>
    @endif

    @if(session('decryption_error'))
        <div class="alert alert-danger">
            {{ session('decryption_error') }}
        </div>
    @endif

    <form method="POST" action="{{ route('decryption-tester.decrypt') }}">
        @csrf

        <div class="form-group">
            <label for="encrypted_value" class="form-label">Encrypted Value</label>
            <textarea id="encrypted_value" name="encrypted_value" class="form-textarea" required>{{ old('encrypted_value') }}</textarea>
            <p class="form-help">Supports Laravel payload strings and Node.js base64(iv + ciphertext) values.</p>
        </div>

        <div class="form-group">
            <label for="app_key" class="form-label">APP_KEY (optional)</label>
            <input id="app_key" name="app_key" type="text" class="form-input" value="{{ old('app_key') }}" placeholder="base64:... or 32-byte key">
            <p class="form-help">Leave empty to use the current AdminSide APP_KEY.</p>
        </div>

        <div class="action-row">
            <button type="submit" class="btn-primary">Decrypt Value</button>
        </div>
    </form>
</div>

@if(session('decryption_result'))
    @php($result = session('decryption_result'))
    <div class="tester-card">
        <h2 class="tester-title" style="font-size: 1.05rem;">Decryption Result</h2>
        <div class="result-box">
            <div class="result-meta">
                Layers decrypted: {{ $result['layers'] }} |
                Formats used: {{ implode(' -> ', $result['formats']) }} |
                Key source: {{ $result['used_custom_key'] ? 'Provided APP_KEY' : 'Server APP_KEY' }}
            </div>
            <pre class="result-text">{{ $result['decrypted_text'] }}</pre>
        </div>
    </div>
@endif
@endsection