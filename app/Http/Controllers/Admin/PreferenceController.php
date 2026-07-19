<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * Per-user admin UI preferences saved to the account (so they follow the admin
 * across devices). Currently: resizable table column widths.
 */
class PreferenceController extends Controller
{
    public function tableWidths(Request $request)
    {
        $data = $request->validate([
            'table' => ['required', 'string', 'max:40', 'regex:/^[a-z0-9_-]+$/'],
            'widths' => ['required', 'array', 'max:24'],
            'widths.*' => ['numeric', 'min:40', 'max:1200'],
        ]);

        $user = Auth::user();
        $prefs = $user->ui_preferences ?? [];
        $prefs['tableWidths'][$data['table']] = array_map('intval', $data['widths']);
        $user->ui_preferences = $prefs;
        $user->save();

        return response()->noContent();
    }
}
