<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreAttachmentRequest;
use App\Http\Resources\TicketAttachmentResource;
use App\Models\ActivityLog;
use App\Models\Ticket;
use App\Models\TicketAttachment;
use App\Services\WorkspaceEmailNotificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Storage;

class TicketAttachmentController extends Controller
{
    public function __construct(
        protected WorkspaceEmailNotificationService $emailNotificationService
    ) {
    }

    public function index(Ticket $ticket): JsonResponse
    {
        $this->authorize('viewAny', [TicketAttachment::class, $ticket]);

        $attachments = TicketAttachment::with('user')
            ->where('ticket_id', $ticket->id)
            ->latest()
            ->get();

        return response()->json([
            'message' => 'Attachments retrieved successfully.',
            'data' => TicketAttachmentResource::collection($attachments),
        ]);
    }

    public function store(StoreAttachmentRequest $request, Ticket $ticket): JsonResponse
    {
        $this->authorize('create', [TicketAttachment::class, $ticket]);

        $file = $request->file('file');
        $size = $file->getSize();

        $path = $file->store('ticket-attachments', 'public');

        $attachment = TicketAttachment::create([
            'ticket_id' => $ticket->id,
            'user_id'   => $request->user()->id,
            'file_name' => $file->getClientOriginalName(),
            'file_path' => $path,
            'file_type' => $file->getClientMimeType(),
            'size_bytes' => $size,
        ]);

        $activityLog = ActivityLog::create([
            'project_id' => $ticket->project_id,
            'ticket_id'  => $ticket->id,
            'user_id'    => $request->user()->id,
            'action'     => 'attachment_uploaded',
            'description' => $request->user()->name . ' uploaded attachment "' . $file->getClientOriginalName() . '" to ticket "' . $ticket->title . '".',
        ]);

        $this->emailNotificationService->sendActivityNotification($activityLog);

        $attachment->load('user');

        return response()->json([
            'message' => 'Attachment uploaded successfully.',
            'data' => new TicketAttachmentResource($attachment),
        ], 201);
    }

    public function destroy(TicketAttachment $attachment): JsonResponse
    {
        $this->authorize('delete', $attachment);

        $ticket = $attachment->ticket;
        $fileName = $attachment->file_name;

        if ($attachment->file_path && Storage::disk('public')->exists($attachment->file_path)) {
            Storage::disk('public')->delete($attachment->file_path);
        }

        $activityLog = ActivityLog::create([
            'project_id' => $ticket->project_id,
            'ticket_id'  => $ticket->id,
            'user_id'    => auth()->id(),
            'action'     => 'attachment_deleted',
            'description' => auth()->user()->name . ' deleted attachment "' . $fileName . '" from ticket "' . $ticket->title . '".',
        ]);

        $this->emailNotificationService->sendActivityNotification($activityLog);

        $attachment->delete();

        return response()->json([
            'message' => 'Attachment deleted successfully.',
        ]);
    }
}
