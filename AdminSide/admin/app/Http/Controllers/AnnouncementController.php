<?php

namespace App\Http\Controllers;

use App\Models\Announcement;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class AnnouncementController extends Controller
{
    public function store(Request $request)
    {
        $request->validate([
            'title' => 'required|string|max:255',
            'content' => 'required|string',
            'attachments.*' => 'nullable|file|max:10240', // 10MB max
        ]);

        $attachmentUrls = [];
        if ($request->hasFile('attachments')) {
            // Upload files to Cloudinary via Node.js backend
            $nodeBackendUrl = config('app.node_backend_url', 'https://adavao-1-mawm.onrender.com');
            
            try {
                $httpRequest = Http::timeout(60);
                
                foreach ($request->file('attachments') as $file) {
                    $httpRequest = $httpRequest->attach(
                        'attachments',
                        file_get_contents($file->getRealPath()),
                        $file->getClientOriginalName()
                    );
                }
                
                $response = $httpRequest->post("{$nodeBackendUrl}/api/announcements/upload");
                
                if ($response->successful() && $response->json('success')) {
                    $attachmentUrls = $response->json('urls', []);
                    Log::info('Announcement files uploaded to Cloudinary', ['urls' => $attachmentUrls]);
                } else {
                    Log::warning('Cloudinary upload failed, falling back to local storage', [
                        'response' => $response->body()
                    ]);
                    // Fallback to local storage
                    foreach ($request->file('attachments') as $file) {
                        $path = $file->store('announcements', 'public');
                        $attachmentUrls[] = $path;
                    }
                }
            } catch (\Exception $e) {
                Log::error('Error uploading to Cloudinary via Node.js', ['error' => $e->getMessage()]);
                // Fallback to local storage
                foreach ($request->file('attachments') as $file) {
                    $path = $file->store('announcements', 'public');
                    $attachmentUrls[] = $path;
                }
            }
        }

        Announcement::create([
            'title' => $request->title,
            'content' => $request->content,
            'attachments' => !empty($attachmentUrls) ? $attachmentUrls : null,
            'user_id' => auth()->id(),
        ]);

        return redirect()->back()->with('success', 'Announcement posted successfully!');
    }
}
