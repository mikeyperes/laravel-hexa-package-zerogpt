@extends('layouts.app')
@section('title', 'ZeroGPT Settings')
@section('header', 'ZeroGPT Settings')

@section('content')
<div class="max-w-2xl mx-auto space-y-6" x-data="zerogptSettings()">

    {{-- Setup Instructions --}}
    <div class="bg-blue-50 border border-blue-200 rounded-xl p-6">
        <h2 class="text-lg font-semibold text-blue-900 mb-2">Setup Instructions</h2>
        <ol class="list-decimal list-inside text-sm text-blue-800 space-y-1">
            <li>Create an account at <a href="https://www.zerogpt.com/my-account?register=true" target="_blank" class="underline inline-flex items-center gap-1">zerogpt.com <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"/></svg></a></li>
            <li>Subscribe to an API plan at <a href="https://www.zerogpt.com/pricing#api" target="_blank" class="underline inline-flex items-center gap-1">Pricing <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"/></svg></a> and add funds</li>
            <li>Copy your API key from the dashboard</li>
            <li>Paste below and click Test to verify</li>
        </ol>
        <p class="text-xs text-blue-600 mt-2">Pay-as-you-go: from $0.034/1000 words. No free API tier (free web UI only).</p>
    </div>

    {{-- API Key (core component) --}}
    <x-hexa-credential-field
        slug="zerogpt"
        key-name="api_key"
        label="ZeroGPT API Key"
        :test-url="route('zerogpt.test')"
        help="Get your API key from the ZeroGPT dashboard after subscribing to an API plan."
    />

    {{-- Settings --}}
    <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6 space-y-4">
        <h3 class="font-semibold text-gray-800">Settings</h3>

        <label class="flex items-center gap-2 cursor-pointer">
            <input type="checkbox" x-model="enabled" class="rounded border-gray-300 text-blue-600">
            <span class="text-sm text-gray-700">Enable ZeroGPT</span>
        </label>

        <label class="flex items-center gap-2 cursor-pointer">
            <input type="checkbox" x-model="debugMode" class="rounded border-gray-300 text-yellow-600">
            <span class="text-sm text-gray-700">Debug Mode <span class="text-gray-400">(sends only first 3 sentences to save API credits)</span></span>
        </label>

        <button @click="save()" :disabled="saving" class="bg-blue-600 text-white px-5 py-2 rounded-lg text-sm hover:bg-blue-700 disabled:opacity-50 inline-flex items-center gap-2">
            <svg x-show="saving" x-cloak class="w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/></svg>
            <span x-text="saving ? 'Saving...' : (saved ? 'Saved!' : 'Save Settings')"></span>
        </button>

        <div x-show="saveResult" x-cloak class="p-3 rounded-lg text-sm border" :class="saveSuccess ? 'bg-green-50 border-green-200 text-green-800' : 'bg-red-50 border-red-200 text-red-800'" x-text="saveResult"></div>
    </div>

    <div class="text-center">
        <a href="{{ route('zerogpt.raw') }}" class="text-sm text-blue-600 hover:underline">Open Raw Test Page &rarr;</a>
    </div>
</div>

@push('scripts')
<script>
function zerogptSettings() {
    const csrf = document.querySelector('meta[name="csrf-token"]')?.content;
    const headers = { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json' };
    return {
        enabled: {{ \hexa_core\Models\Setting::getValue('zerogpt_enabled', true) ? 'true' : 'false' }},
        debugMode: {{ \hexa_core\Models\Setting::getValue('zerogpt_debug_mode', false) ? 'true' : 'false' }},
        saving: false, saved: false, saveResult: '', saveSuccess: false,
        async save() {
            this.saving = true; this.saved = false; this.saveResult = '';
            try {
                const r = await fetch('{{ route("zerogpt.settings.save") }}', { method: 'POST', headers, body: JSON.stringify({ enabled: this.enabled, debug_mode: this.debugMode }) });
                const d = await r.json();
                this.saveSuccess = d.success;
                this.saveResult = d.message || (d.success ? 'Settings saved.' : 'Save failed.');
                this.saved = d.success;
                setTimeout(() => { this.saved = false; this.saveResult = ''; }, 3000);
            } catch(e) { this.saveSuccess = false; this.saveResult = 'Network error.'; }
            this.saving = false;
        },
    };
}
</script>
@endpush
@endsection
