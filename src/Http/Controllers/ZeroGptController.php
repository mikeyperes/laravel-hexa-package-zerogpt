<?php

namespace hexa_package_zerogpt\Http\Controllers;

use hexa_core\Http\Controllers\Controller;
use hexa_core\Models\Setting;
use hexa_core\Services\CredentialService;
use hexa_package_zerogpt\Services\ZeroGptService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * ZeroGptController — settings + raw test page + detect endpoint.
 */
class ZeroGptController extends Controller
{
    protected ZeroGptService $service;

    public function __construct(
        ZeroGptService $service,
        private readonly CredentialService $credentials,
    ) {
        $this->service = $service;
    }

    /**
     * Settings page.
     */
    public function settings(): View
    {
        return view('zerogpt::settings.index');
    }

    /**
     * Save settings.
     */
    public function saveSettings(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'api_key' => 'nullable|string|max:500',
            'enabled' => 'nullable|boolean',
            'debug_mode' => 'nullable|boolean',
        ]);

        if (isset($validated['api_key']) && ! empty($validated['api_key'])) {
            $this->credentials->store('zerogpt', 'api_key', $validated['api_key']);
            Setting::setValue('zerogpt_api_key', '');
        }
        Setting::setValue('zerogpt_enabled', $validated['enabled'] ?? true);
        Setting::setValue('zerogpt_debug_mode', $validated['debug_mode'] ?? false);

        hexaLog('zerogpt', 'settings_updated', 'ZeroGPT settings updated');

        return response()->json(['success' => true, 'message' => 'Settings saved.']);
    }

    /**
     * Test API connection.
     */
    public function testConnection(): JsonResponse
    {
        return response()->json($this->service->testConnection());
    }

    /**
     * Raw test page.
     */
    public function raw(): View
    {
        return view('zerogpt::raw.index');
    }

    /**
     * Detect AI content.
     */
    public function detect(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'text' => 'required|string|min:10',
        ]);

        return response()->json($this->service->detect($validated['text']));
    }
}
