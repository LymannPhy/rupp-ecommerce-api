<?php

namespace App\Http\Controllers;

use App\Http\Responses\ApiResponse;
use App\Models\ContactUs;
use Illuminate\Http\Request;
use App\Mail\ContactResponseMail;
use App\Mail\ContactUsMail;
use Illuminate\Support\Facades\Mail;

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
        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|max:255',
            'phone' => 'nullable|string|max:20',
            'message' => 'required|string',
        ]);

        // Store contact request
        $contact = ContactUs::create($request->only(['name', 'email', 'phone', 'message']));

        // Send email confirmation
        try {
            Mail::to($contact->email)->send(new ContactUsMail([
                'name' => $contact->name,
                'email' => $contact->email,
                'message' => $contact->message
            ]));
        } catch (\Exception $e) {
            return ApiResponse::error('Message sent but failed to send email', ['error' => $e->getMessage()], 500);
        }

        return ApiResponse::sendResponse($contact, 'Your message has been sent successfully.', 201);
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
