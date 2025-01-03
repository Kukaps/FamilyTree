<?php

namespace App\Http\Controllers;

use App\Models\Family;
use App\Models\Individual;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class FamilyTreeStorageController extends Controller
{
    public function store(Request $request)
    {
        try {
            DB::beginTransaction();

            if (!$request->has('gedcomData')) {
                throw new \Exception('No GEDCOM data provided');
            }

            // Create family record
            $family = new Family();
            $family->user_id = auth()->id() ?? throw new \Exception('User not authenticated');
            $family->name = 'Family Tree ' . now()->format('Y-m-d H:i:s');
            $family->data = $request->gedcomData;
            $family->save();

            // Parse GEDCOM data
            $lines = explode("\n", $request->gedcomData);
            $currentIndividual = null;
            $currentFamily = null;
            $individuals = [];
            $relationships = [];

            foreach ($lines as $line) {
                $line = trim($line);
                if (empty($line)) continue;

                $parts = explode(' ', $line, 3);
                $level = (int)$parts[0];
                $tag = $parts[1];
                $value = isset($parts[2]) ? trim($parts[2]) : '';

                if ($level === 0) {
                    // Save previous individual if exists
                    if ($currentIndividual) {
                        $individuals[] = $currentIndividual;
                        $currentIndividual = null;
                    }

                    // Check if this is an individual record
                    if (strpos($value, 'INDI') !== false) {
                        $currentIndividual = [
                            'gedcom_id' => trim($tag, '@'),
                            'name' => '',
                            'given_name' => '',
                            'surname' => '',
                            'sex' => 'U',
                            'birth_date' => null,
                            'death_date' => null
                        ];
                    }
                    // Check if this is a family record
                    elseif (strpos($value, 'FAM') !== false) {
                        $currentFamily = [
                            'id' => trim($tag, '@'),
                            'husband' => null,
                            'wife' => null,
                            'children' => []
                        ];
                    }
                }
                elseif ($currentIndividual) {
                    // Parse individual details
                    if ($tag === 'NAME') {
                        $nameParts = explode('/', trim($value, '/'));
                        $currentIndividual['name'] = trim(str_replace('/', '', $value));
                        $currentIndividual['given_name'] = trim($nameParts[0]);
                        $currentIndividual['surname'] = isset($nameParts[1]) ? trim($nameParts[1]) : '';
                    }
                    elseif ($tag === 'SEX') {
                        $currentIndividual['sex'] = $value;
                    }
                    elseif ($level === 2 && $tag === 'DATE') {
                        if (isset($lines[$level - 1]) && strpos($lines[$level - 1], 'BIRT') !== false) {
                            $currentIndividual['birth_date'] = $this->parseDate($value);
                        }
                        elseif (isset($lines[$level - 1]) && strpos($lines[$level - 1], 'DEAT') !== false) {
                            $currentIndividual['death_date'] = $this->parseDate($value);
                        }
                    }
                }
                elseif ($currentFamily) {
                    // Parse family relationships
                    if ($tag === 'HUSB') {
                        $relationships[] = [
                            'family_id' => $family->id,
                            'individual_gedcom_id' => trim($value, '@'),
                            'relationship_type' => 'HUSBAND'
                        ];
                    }
                    elseif ($tag === 'WIFE') {
                        $relationships[] = [
                            'family_id' => $family->id,
                            'individual_gedcom_id' => trim($value, '@'),
                            'relationship_type' => 'WIFE'
                        ];
                    }
                    elseif ($tag === 'CHIL') {
                        $relationships[] = [
                            'family_id' => $family->id,
                            'individual_gedcom_id' => trim($value, '@'),
                            'relationship_type' => 'CHILD'
                        ];
                    }
                }
            }

            // Save last individual if exists
            if ($currentIndividual) {
                $individuals[] = $currentIndividual;
            }

            // Save individuals and create relationships
            foreach ($individuals as $individualData) {
                $individual = new Individual();
                $individual->user_id = auth()->id();
                $individual->gedcom_id = $individualData['gedcom_id'];
                $individual->name = $individualData['name'];
                $individual->given_name = $individualData['given_name'];
                $individual->surname = $individualData['surname'];
                $individual->sex = $individualData['sex'];
                $individual->birth_date = $individualData['birth_date'];
                $individual->death_date = $individualData['death_date'];
                $individual->save();
            }

            // Create relationships
            foreach ($relationships as $rel) {
                $individual = Individual::where('gedcom_id', $rel['individual_gedcom_id'])->first();
                if ($individual) {
                    $family->individuals()->attach($individual->id, [
                        'relationship_type' => $rel['relationship_type']
                    ]);
                }
            }

            DB::commit();
            
            return response()->json([
                'success' => true,
                'message' => 'Family tree saved successfully',
                'family' => $family->load('individuals')
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error saving family tree: ' . $e->getMessage());
            Log::error('Stack trace: ' . $e->getTraceAsString());
            
            return response()->json([
                'success' => false,
                'message' => 'Error saving family tree: ' . $e->getMessage()
            ], 500);
        }
    }

    private function parseDate($dateStr)
    {
        try {
            return \Carbon\Carbon::parse($dateStr)->format('Y-m-d');
        } catch (\Exception $e) {
            Log::warning('Failed to parse date: ' . $dateStr);
            return null;
        }
    }

    public function index()
    {
        try {
            $families = Family::with(['individuals' => function($query) {
                $query->select(
                    'individuals.id',
                    'individuals.gedcom_id',
                    'individuals.name',
                    'individuals.given_name',
                    'individuals.surname',
                    'individuals.sex as gender',
                    'individuals.birth_date',
                    'individuals.death_date'
                );
            }])
            ->where('user_id', auth()->id())
            ->get();
            
            $trees = $families->map(function($family) {
                $individualsByType = $family->individuals->groupBy('pivot.relationship_type');
                
                return [
                    'id' => $family->id,
                    'name' => $family->name,
                    'data' => $family->data,
                    'members' => [
                        'husbands' => $individualsByType->get('HUSBAND', collect()),
                        'wives' => $individualsByType->get('WIFE', collect()),
                        'children' => $individualsByType->get('CHILD', collect()),
                        'others' => $individualsByType->get('MEMBER', collect())
                    ]
                ];
            });

            Log::info('Successfully loaded trees', ['count' => $trees->count()]);
            
            return response()->json([
                'success' => true,
                'trees' => $trees
            ]);
        } catch (\Exception $e) {
            Log::error('Error in FamilyTreeStorageController@index: ' . $e->getMessage());
            Log::error('Stack trace: ' . $e->getTraceAsString());
            
            return response()->json([
                'success' => false,
                'message' => 'Error loading family trees: ' . $e->getMessage()
            ], 500);
        }
    }
} 