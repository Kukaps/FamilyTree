<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class FamilyTreeController extends Controller
{
    public function getFamilyTree(Request $request)
    {
        try {
            // Validate request data
            $validated = $request->validate([
                'family' => 'required|string',
                'focus' => 'nullable|string',
            ]);

            // Try both API endpoints with a shorter timeout
            $endpoints = [
                'https://www.familyecho.com/api/',
                'http://api.familyecho.com/'
            ];

            $error = null;
            
            foreach ($endpoints as $endpoint) {
                try {
                    $response = Http::timeout(30)  // 30 seconds timeout
                        ->asForm()
                        ->withoutVerifying()
                        ->post($endpoint, [
                            'format' => 'json',
                            'operation' => 'temp_view',
                            'family' => $validated['family'],
                            'focus' => $validated['focus'] ?? null,
                        ]);

                    if ($response->successful()) {
                        return response()->json($response->json());
                    }
                    
                    $error = $response->body();
                } catch (\Exception $e) {
                    Log::warning("API endpoint $endpoint failed", [
                        'error' => $e->getMessage()
                    ]);
                    $error = $e->getMessage();
                    continue;  // Try next endpoint
                }
            }

            // If we get here, both endpoints failed
            Log::error('All Family Echo API endpoints failed', [
                'last_error' => $error
            ]);

            return response()->json([
                'error' => 'Family Echo API is currently unavailable',
                'details' => $error
            ], 503);  // Service Unavailable

        } catch (\Exception $e) {
            Log::error('Family Tree Error', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'error' => 'An error occurred',
                'message' => $e->getMessage()
            ], 500);
        }
    }
}
