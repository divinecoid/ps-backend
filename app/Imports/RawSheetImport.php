<?php

namespace App\Imports;

use Maatwebsite\Excel\Concerns\ToArray;
use Maatwebsite\Excel\Concerns\WithCustomCsvSettings;

/**
 * Reads every sheet as raw rows (no heading row) so the parser can locate the
 * header itself — marketplace exports often put report metadata above it.
 */
class RawSheetImport implements ToArray, WithCustomCsvSettings
{
    public function __construct(private string $delimiter = ',')
    {
    }

    public function array(array $array): void
    {
        // Rows are collected by Excel::toArray(); nothing to do here.
    }

    public function getCsvSettings(): array
    {
        return ['delimiter' => $this->delimiter, 'input_encoding' => 'UTF-8'];
    }
}
