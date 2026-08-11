<?php

namespace App\Http\Controllers;

use Inertia\Inertia;
use Inertia\Response;

/**
 * The designed "Contact Us" page (Figma: contact-us-contact-info / …).
 *
 * Registered at /pages/contact, the URL the nav and footer already point at and the
 * one already in the sitemap, so nothing has to be redirected. Like /pages/about and
 * /pages/branches it is declared BEFORE the /pages/{slug} CMS catch-all, which would
 * otherwise resolve this slug to the `contact` content_pages row.
 *
 * ⚠️ That row still exists and stays editable in the admin, it is simply no longer
 * what this URL renders. It held only placeholder copy (a WhatsApp number and "edit
 * this content from the admin panel"), so nothing real was displaced.
 *
 * 🔑 The phone and email shown on the page are NOT props from here: they come from the
 * globally shared `footer` settings (HandleInertiaRequests), the same admin-editable
 * source the site footer reads, so a change in the admin updates both at once.
 */
class ContactController
{
    public function index(): Response
    {
        return Inertia::render('shop/contact');
    }
}
