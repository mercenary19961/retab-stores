<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

abstract class Controller
{
    /** Whitelisted page sizes offered by the admin "per page" selector. */
    protected const PER_PAGE_OPTIONS = [10, 25, 50, 100];

    /**
     * Resolve a safe "per page" size from ?per_page=N, whitelisted against
     * PER_PAGE_OPTIONS, falling back to the caller's default (which may sit
     * outside the list — the UI merges it in as an extra option). Guards against
     * arbitrary / huge page sizes being requested via the query string.
     */
    protected function perPage(Request $request, int $default = 25): int
    {
        $value = (int) $request->query('per_page');

        return in_array($value, self::PER_PAGE_OPTIONS, true) ? $value : $default;
    }
}
