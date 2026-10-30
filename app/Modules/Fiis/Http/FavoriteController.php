<?php

namespace App\Modules\Fiis\Http;

use App\Modules\Fiis\Application\AddFavorite;
use App\Modules\Fiis\Application\RemoveFavorite;
use App\Modules\Fiis\Http\Requests\AddFavoriteRequest;
use Illuminate\Http\RedirectResponse;

final class FavoriteController
{
    public function store(AddFavoriteRequest $request, AddFavorite $addFavorite): RedirectResponse
    {
        $ticker = $request->string('ticker')->toString();
        $addFavorite->handle($ticker);

        return redirect()->route('funds.show', $ticker);
    }

    public function destroy(string $ticker, RemoveFavorite $removeFavorite): RedirectResponse
    {
        $removeFavorite->handle(strtoupper($ticker));

        return redirect()->route('home');
    }
}
