<?php

namespace App\Services;

class GedcomParser
{
    private $gedcomData;
    private $individuals = [];
    private $families = [];

    public function __construct($gedcomData)
    {
        $this->gedcomData = $gedcomData;
        $this->parse();
    }

    private function parse()
    {
        $lines = explode("\n", $this->gedcomData);
        $currentRecord = null;
        $currentType = null;

        foreach ($lines as $line) {
            $line = trim($line);
            if (empty($line)) continue;

            // Parse level and tag
            if (preg_match('/^(\d+)\s+(@\w+@\s+)?(\w+)(.*)$/', $line, $matches)) {
                $level = $matches[1];
                $tag = $matches[3];
                $value = trim($matches[4] ?? '');

                if ($level == '0') {
                    if ($tag == 'INDI') {
                        $currentType = 'INDI';
                        $currentRecord = [
                            'id' => trim($value, '@'),
                            'name' => null,
                            'birth_date' => null,
                            'death_date' => null,
                            'gender' => null
                        ];
                    } elseif ($tag == 'FAM') {
                        $currentType = 'FAM';
                        $currentRecord = [
                            'id' => trim($value, '@'),
                            'husband_id' => null,
                            'wife_id' => null,
                            'children' => []
                        ];
                    }
                } elseif ($currentType == 'INDI') {
                    switch ($tag) {
                        case 'NAME':
                            $currentRecord['name'] = trim($value, '/');
                            break;
                        case 'SEX':
                            $currentRecord['gender'] = $value;
                            break;
                        case 'BIRT':
                            // Birth event - next DATE tag will be birth date
                            break;
                        case 'DEAT':
                            // Death event - next DATE tag will be death date
                            break;
                        case 'DATE':
                            // Attach date to the most recent event
                            if (isset($currentRecord['birth_date']) === false) {
                                $currentRecord['birth_date'] = $value;
                            } else {
                                $currentRecord['death_date'] = $value;
                            }
                            break;
                    }
                } elseif ($currentType == 'FAM') {
                    switch ($tag) {
                        case 'HUSB':
                            $currentRecord['husband_id'] = trim($value, '@');
                            break;
                        case 'WIFE':
                            $currentRecord['wife_id'] = trim($value, '@');
                            break;
                        case 'CHIL':
                            $currentRecord['children'][] = trim($value, '@');
                            break;
                    }
                }

                // Store completed records
                if ($level == '0' && $currentRecord) {
                    if ($currentType == 'INDI') {
                        $this->individuals[] = $currentRecord;
                    } elseif ($currentType == 'FAM') {
                        $this->families[] = $currentRecord;
                    }
                }
            }
        }
    }

    public function getIndividuals()
    {
        return $this->individuals;
    }

    public function getFamilies()
    {
        return $this->families;
    }
} 