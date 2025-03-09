<?php

namespace App\Http\Controllers;

use App\Http\Responses\ApiResponse;
use App\Models\ContactUs;
use Illuminate\Http\Request;
use App\Mail\ContactResponseMail;
use App\Mail\ContactUsMail;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;


class ContactUsController extends Controller
{

    /**
     * Respond to a user's contact inquiry by sending a response email and updating the status.
     *
     * @param Request $request
     * @param string $uuid The unique identifier for the contact inquiry
     * @return JsonResponse
     */
    public function respondToUser(Request $request, $uuid)
    {
        try {
            // Find the contact entry
            $contact = ContactUs::where('uuid', $uuid)->firstOrFail();

            // Validate request
            $request->validate([
                'message' => 'required|string',
            ]);

            // Get sender name (assume admin user is responding)
            $senderName = auth()->user()->name ?? 'CAM-O2 Support';

            // Send email
            Mail::to($contact->email)->send(new ContactResponseMail($contact->name, $request->message, $senderName));

            // Update status to "resolved"
            $contact->update(['status' => 'resolved']);

            return ApiResponse::sendResponse(
                null,
                'Response email sent successfully!'
            );
        } catch (\Exception $e) {
            return ApiResponse::error(
                'Failed to send email.',
                ['exception' => $e->getMessage()],
                500
            );
        }
    }

    /**
     * Store a new contact form submission.
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function store(Request $request)
    {
        // Validate request
        $validatedData = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|max:255',
            'phone' => 'nullable|string|max:20',
            'message' => 'required|string',
        ]);

        // Create contact record with UUID
        $contact = ContactUs::create([
            'uuid' => Str::uuid(), // Ensure UUID is generated
            'name' => $validatedData['name'],
            'email' => $validatedData['email'],
            'phone' => $validatedData['phone'] ?? null,
            'message' => $validatedData['message'],
        ]);

        // Attempt to send email
        try {
            Mail::to($contact->email)->send(new ContactUsMail([
                'name' => $contact->name,
                'email' => $contact->email,
                'message' => $contact->message
            ]));
        } catch (\Exception $e) {
            return response()->json([
                "date" => now()->format('Y-m-d H:i:s'),
                "code" => 500,
                "message" => "Message sent but failed to send email",
                "errors" => [
                    "error" => $e->getMessage(),
                ]
            ], 500);
        }

        // ✅ Response in the exact required format
        return response()->json([
            "date" => now()->format('Y-m-d H:i:s'),
            "code" => 201,
            "message" => "Your message has been sent successfully.",
            "data" => [
                "created_at" => $contact->created_at->toISOString(),
                "name" => $contact->name,
                "email" => $contact->email,
                "message" => $contact->message,
                "uuid" => $contact->uuid,
            ]
        ], 201);
    }


   /**
     * Get all contact messages (for admin use).
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function index()
    {
        $contacts = ContactUs::orderByDesc('created_at')->get()->map(function ($contact) {
            return [
                'uuid' => $contact->uuid,
                'name' => $contact->name,
                'email' => $contact->email,
                'phone' => $contact->phone,
                'message' => $contact->message,
                'status' => $contact->status,
                'created_at' => $contact->created_at,
                'updated_at' => $contact->updated_at
            ];
        });

        return ApiResponse::sendResponse($contacts, 'Contact messages retrieved successfully.');
    }
}
