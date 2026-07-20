<?php

namespace App\Services\Catalog;

/** Tally of a Zid catalogue import run (see ZidCatalogImporter). */
class ZidImportResult
{
    public int $productsSaved = 0;

    /** Of the saved products, how many were imported hidden (Zid drafts). */
    public int $drafts = 0;

    public int $imagesSaved = 0;

    public int $imagesFailed = 0;

    /** @var array<int, string> */
    public array $notes = [];
}
