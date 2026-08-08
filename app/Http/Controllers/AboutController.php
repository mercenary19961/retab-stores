<?php

namespace App\Http\Controllers;

use Inertia\Inertia;
use Inertia\Response;

/**
 * The designed "About Us" page (Figma: about-us-banner / who-we-are / …).
 *
 * Registered at /pages/about, the URL the nav and footer already point at and
 * the one that is already in the sitemap, so nothing has to be redirected. Like
 * /pages/branches it is declared BEFORE the /pages/{slug} CMS catch-all, which
 * would otherwise resolve this slug to the `about` content_pages row.
 *
 * ⚠️ That content_pages row still exists and stays editable in the admin. It is
 * simply no longer what /pages/about renders. Once the prose sections of this
 * design are built, decide deliberately whether their copy comes from that row
 * (keeps the client's edit ability) or from i18n (fixed design copy).
 */
class AboutController
{
    public function index(): Response
    {
        return Inertia::render('shop/about');
    }
}
