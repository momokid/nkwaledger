<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UpdateMarketplaceSettingRequest;
use App\Services\SettingsService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class MarketplaceSettingController extends Controller
{
    public function __construct(private readonly SettingsService $settings) {}

    public function index(Request $request): Response
    {
        return Inertia::render('Admin/Marketplace/Settings/Index', [
            'settings' => collect(SettingsService::DEFAULTS)
                ->map(fn($default, $key) => [
                    'key' => $key,
                    'value' => $this->settings->get($key),
                    'default' => $default,
                ])
                ->values(),
        ]);
    }

    public function update(UpdateMarketplaceSettingRequest $request, string $key): RedirectResponse
    {
        if (! array_key_exists($key, SettingsService::DEFAULTS)) {
            throw ValidationException::withMessages(['key' => 'Unknown setting.']);
        }

        $this->settings->set($key, $request->validated()['value'], $request->user());

        return back()->with('success', 'Setting updated.');
    }
}
