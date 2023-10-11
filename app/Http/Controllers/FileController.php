<?php

namespace App\Http\Controllers;
use App\Http\Controllers\FileController;
use Illuminate\Http\Request;
use Storage;
use GuzzleHttp\Client;
use Illuminate\Support\Facades\Http; // Added for HTTP client

class FileController extends Controller
{
    public function stringIsAValidUrl($s) {
        $url = filter_var($s, FILTER_VALIDATE_URL);
        return ($url !== false);
    }

    public function downloadFile($givenURL) {
        $parsed = parse_url($givenURL);
        $fileName = basename($parsed['path']);

        $client = new Client();
        $response = $client->get($givenURL);

        Storage::disk('local')->put("processedFiles/{$fileName}", $response->getBody());

        $file = Storage::disk('local')->get("processedFiles/{$fileName}");

        return [
            'buffer' => $file,
            'originalname' => $fileName,
        ];
    }

    public function getFileSize($givenURL) {
        $client = new Client();
        $response = $client->head($givenURL);
        return $response->getHeaderLine('Content-Length');
    }

    public function generateFilesArray($files) {
        $filesArray = [];
        foreach ($files as $fileURL) {
            $currentFile = $this->downloadFile($fileURL); // Use $this to call controller methods
            $filesArray[] = $currentFile;
        }
        return $filesArray;
    }

    public function fileIsAllowed(Request $request) {
        try {
            $maxSizeInMb = intval($request->input('maxSizeInMb'));
            $maxSizeinBytes = $maxSizeInMb * 1024 * 1024;
            
            $formatsAllow = array_map('strtolower', $request->input('formatsAllow'));
            
            $fileURL = $request->input('url');
    
            // Check if the URL is a valid URL
            if (!$this->stringIsAValidUrl($fileURL)) {
                return response()->json([
                    'isSuccess' => false,
                    'error' => [
                        'messageEN' => 'Invalid Value, Please attach a file',
                        'messageAR' => 'المدخل غير صحيح, الرجاء إرفاق ملف',
                    ],
                ]);
            }
    
            // Get the file size from the URL
            $response = Http::head($fileURL);
            $fileSize = $response->header('Content-Length');
    
            // Get the file format (extension)
            $fileFormat = strtolower(pathinfo($fileURL, PATHINFO_EXTENSION));
    
            // Check if the file format is allowed
            if (!in_array($fileFormat, $formatsAllow)) {
                return response()->json([
                    'isSuccess' => false,
                    'error' => [
                        'messageEN' => 'File format is not allowed',
                        'messageAR' => 'نوع الملف غير مسموح به',
                    ],
                ]);
            }
    
            // Check if the file size exceeds the specified limit
            if ($fileSize > $maxSizeinBytes) {
                return response()->json([
                    'isSuccess' => false,
                    'error' => [
                        'messageEN' => 'File size is too large',
                        'messageAR' => 'حجم الملف كبير جدا',
                    ],
                ]);
            }
            
            return response()->json([
                'isSuccess' => true,
                'error' => null,
            ]);
        } catch (\Exception $error) {
            return response()->json([
                'isSuccess' => false,
                'error' => [
                    'messageEN' => 'Something went wrong',
                    'messageAR' => 'حدث خطأ ما',
                ],
            ]);
        }
    }
    
    public function saveDispute(Request $request) {
        try {
            // Extract data from the request
            $token = $request->input('token');
            $segmentId = $request->input('segmentId');
            $memberCode = $request->input('memberCode');
            // ... (repeat for all your request data)
    
            // Generate an array of downloaded files using the generateFilesArray method
            $filesArray = $this->generateFilesArray($request->input('attachments'));
    
            // Define the base URL and endpoint for the remote API
            $molimBaseURL = "https://molimqapi.simah.com";
            $saveDisputeURL = "{$molimBaseURL}/api/v1/dispute/Save";
    
            // Create an HTTP client request with headers
            $response = Http::withHeaders([
                'Content-Type' => 'multipart/form-data',
                'language' => 'en',
                'appId' => '5',
                'Authorization' => "Bearer {$token}",
            ])->asMultipart()->post($saveDisputeURL, [
                ['name' => 'segmentId', 'contents' => $segmentId],
                ['name' => 'memberCode', 'contents' => $memberCode],
                // ... (repeat for all your fields)
            ]);
    
            // Attach files to the request
            foreach ($filesArray as $file) {
                $response->attach('attachments', $file['buffer'], $file['originalname']);
            }
    
            // Return the API response as a JSON response along with the HTTP status code
            return response()->json($response->json(), $response->status());
            
        } catch (\Exception $error) {
            // Handle and return an error response in case of an exception
            return response()->json([
                'message' => "Error: {$error->getMessage()}",
                'body' => $request->all(),
            ], 500);
        }
    }
    
}
