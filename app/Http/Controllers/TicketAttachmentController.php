<?php

namespace App\Http\Controllers;

use App\Http\Resources\TicketAttachmentResource;
use App\Models\ActivityLog;
use App\Models\Ticket;
use App\Models\TicketAttachment;
use App\Models\WorkspaceMember;
use App\Services\WorkspaceEmailNotificationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class TicketAttachmentController extends Controller
{
    protected WorkspaceEmailNotificationService $emailNotificationService;

    public function __construct(WorkspaceEmailNotificationService $emailNotificationService)
    {
        $this->emailNotificationService = $emailNotificationService;
    }

    public function index(Ticket $ticket)
    {
        $user = auth()->user();

        $member = WorkspaceMember::where('workspace_id', $ticket->workspace_id)
            ->where('user_id', $user->id)
            ->first();

        if (!$member) {
            return response()->json([
                'message' => 'You are not a member of this workspace.',
            ], 403);
        }

        $attachments = TicketAttachment::with('user')
            ->where('ticket_id', $ticket->id)
            ->latest()
            ->get();

        return response()->json([
            'message' => 'Attachments retrieved successfully.',
            'data' => TicketAttachmentResource::collection($attachments),
        ]);
    }

    public function store(Request $request, Ticket $ticket)
    {
        $user = auth()->user();

        $member = WorkspaceMember::where('workspace_id', $ticket->workspace_id)
            ->where('user_id', $user->id)
            ->first();

        if (!$member) {
            return response()->json([
                'message' => 'You are not a member of this workspace.',
            ], 403);
        }

        if (!in_array($member->role, ['owner', 'editor'])) {
            return response()->json([
                'message' => 'You do not have permission to upload attachments.',
            ], 403);
        }

        $validated = $request->validate([
            'file' => [
                'required',
                'file',
                'mimes:jpg,jpeg,png,gif,webp,pdf,doc,docx,xls,xlsx,ppt,pptx,txt,zip,rar',
                'max:20480',
            ],
        ], [
            'file.required' => 'Please select a file.',
            'file.file' => 'The selected upload must be a valid file.',
            'file.mimes' => 'Only images, documents, text files, compressed files, and office files are allowed.',
            'file.max' => 'The file must not be larger than 20MB.',
        ]);

        $file = $validated['file'];

        $path = $file->store('ticket-attachments', 'public');

        $attachment = TicketAttachment::create([
            'ticket_id' => $ticket->id,
            'user_id' => $user->id,
            'file_name' => $file->getClientOriginalName(),
            'file_path' => $path,
            'file_type' => $file->getClientMimeType(),
        ]);

        $activityLog = ActivityLog::create([
            'workspace_id' => $ticket->workspace_id,
            'ticket_id' => $ticket->id,
            'user_id' => $user->id,
            'action' => 'attachment_uploaded',
            'description' => $user->name . ' uploaded attachment "' . $file->getClientOriginalName() . '" to ticket "' . $ticket->title . '".',
        ]);

        $this->emailNotificationService->sendActivityNotification($activityLog);

        $attachment->load('user');

        return response()->json([
            'message' => 'Attachment uploaded successfully.',
            'data' => new TicketAttachmentResource($attachment),
        ], 201);
    }

    public function destroy(TicketAttachment $attachment)
    {
        $user = auth()->user();

        $ticket = $attachment->ticket;

        $member = WorkspaceMember::where('workspace_id', $ticket->workspace_id)
            ->where('user_id', $user->id)
            ->first();

        if (!$member) {
            return response()->json([
                'message' => 'You are not a member of this workspace.',
            ], 403);
        }

        if (!in_array($member->role, ['owner', 'editor'])) {
            return response()->json([
                'message' => 'You do not have permission to delete attachments.',
            ], 403);
        }

        $fileName = $attachment->file_name;

        if ($attachment->file_path && Storage::disk('public')->exists($attachment->file_path)) {
            Storage::disk('public')->delete($attachment->file_path);
        }

        $activityLog = ActivityLog::create([
            'workspace_id' => $ticket->workspace_id,
            'ticket_id' => $ticket->id,
            'user_id' => $user->id,
            'action' => 'attachment_deleted',
            'description' => $user->name . ' deleted attachment "' . $fileName . '" from ticket "' . $ticket->title . '".',
        ]);

        $this->emailNotificationService->sendActivityNotification($activityLog);

        $attachment->delete();

        return response()->json([
            'message' => 'Attachment deleted successfully.',
        ]);
    }
}