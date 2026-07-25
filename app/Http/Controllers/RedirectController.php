<?php

namespace App\Http\Controllers;

use App\Models\Redirect;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Serves the 301 redirect map. Invoked from the /products/{slug} route's
 * ->missing() hook: when no live product owns the requested slug, an old (Zid)
 * slug in the `redirects` table is 301'd to the product's current URL; anything
 * else 404s exactly as before.
 */
class RedirectController extends Controller
{
    public function missingProduct(Request $request): RedirectResponse
    {
        $slug = (string) $request->route('product');

        $redirect = Redirect::where('from_slug', $slug)->first();
        $target = $redirect?->target();

        abort_if($target === null, 404);

        // Cheap usage signal (which legacy URLs still get hit). Not on the hot path.
        $redirect->increment('hits');

        return redirect()->to($target, $redirect->status);
    }
}
