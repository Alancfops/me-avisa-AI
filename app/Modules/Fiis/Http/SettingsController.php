<?php

namespace App\Modules\Fiis\Http;

use App\Modules\Fiis\Application\BrapiCredentials;
use App\Modules\Fiis\Application\BrapiKeyRefused;
use App\Modules\Fiis\Application\InvalidBrapiKey;
use App\Modules\Fiis\Application\SaveBrapiKey;
use App\Modules\Fiis\Application\SourceUnavailable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

final class SettingsController
{
    public function show(BrapiCredentials $credentials): Response
    {
        return Inertia::render('settings/appearance', [
            'brapi' => ['source' => $credentials->source()],
        ]);
    }

    public function saveBrapi(Request $request, SaveBrapiKey $save): RedirectResponse
    {
        try {
            $save->handle((string) $request->string('token'));
        } catch (InvalidBrapiKey $failure) {
            return back()->withErrors(['token' => $failure->getMessage()]);
        } catch (BrapiKeyRefused) {
            return back()->withErrors(['token' => 'A brapi recusou essa chave. Copie de novo o token do painel da brapi.']);
        } catch (SourceUnavailable) {
            return back()->withErrors(['token' => 'Não conseguimos falar com a brapi para conferir a chave. Confira a internet e tente de novo.']);
        }

        return back();
    }

    public function forgetBrapi(BrapiCredentials $credentials): RedirectResponse
    {
        $credentials->forget();

        return back();
    }
}
