<?php

namespace App\Domain\Requests;

use App\Models\Cases\SimulatedTaxpayerDocument;

/**
 * Loads the taxpayer's filing cabinet (handoff rule 3): documents that exist
 * but stay invisible until an officer's request names their document_type.
 * Idempotent, like the registry and group register — safe to run again.
 */
class TaxpayerResponsePool
{
    public function import(string $path): int
    {
        $handle = fopen($path, 'r');
        $header = array_map(fn ($h) => trim(preg_replace('/^\xEF\xBB\xBF/', '', $h)), fgetcsv($handle, escape: ''));
        $loaded = 0;

        while (($values = fgetcsv($handle, escape: '')) !== false) {
            if ($values === [null] || count($values) !== count($header)) {
                continue;
            }
            $row = array_combine($header, $values);

            SimulatedTaxpayerDocument::updateOrCreate(['document_id' => $row['document_id']], [
                'tin' => $row['tin'],
                'document_type' => $row['document_type'],
                'title' => $row['document_title'],
                'responds_after_days' => (int) $row['responds_after_days'],
                'content_summary' => $row['content_summary'],
                'what_it_shows' => $row['what_it_shows'],
            ]);
            $loaded++;
        }
        fclose($handle);

        return $loaded;
    }
}
