<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Conversation;
use App\Models\Evaluation;
use App\Models\EvaluationResult;
use App\Models\Message;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * Gestiona conversaciones y mensajes entre docentes y estudiantes con relación académica.
 */
class MessageController extends Controller
{
    /**
     * Lista las conversaciones del usuario autenticado.
     */
    public function index(Request $request)
    {
        $user = Auth::user();
        $role = $user->roleName();

        $query = Conversation::with(['teacher:id,full_name', 'student:id,full_name', 'evaluation:id,title'])
            ->where(function ($q) use ($user) {
                $q->where('created_by', $user->id)->orWhere('student_id', $user->id);
            });

        if ($role === 'teacher' || $role === 'coordinador' || $role === 'director') {
            $query->where('created_by', $user->id);
        } elseif ($role === 'student') {
            $query->where('student_id', $user->id);
        }

        $conversations = $query->orderByDesc('last_message_at')->get()
            ->each(function ($conversation) use ($user) {
                $conversation->unread_count = $conversation->messages()
                    ->where('user_id', '!=', $user->id)
                    ->whereNull('read_at')
                    ->count();
            });

        return response()->json(['data' => $conversations]);
    }

    /**
     * Mensajes de una conversación (solo participantes).
     */
    public function show(Request $request, $conversationId)
    {
        $user = Auth::user();
        $conversation = $this->findParticipantConversation($user, $conversationId);

        $messages = $conversation->messages()
            ->with('sender:id,full_name')
            ->orderBy('created_at')
            ->get();

        // Marcar como leídos los mensajes ajenos
        $conversation->messages()
            ->where('user_id', '!=', $user->id)
            ->whereNull('read_at')
            ->update(['read_at' => now()]);

        return response()->json([
            'data' => $messages,
            'conversation' => $conversation->load(['teacher:id,full_name', 'student:id,full_name', 'evaluation:id,title']),
        ]);
    }

    /**
     * Crear conversación (docente↔estudiante) con primer mensaje.
     */
    public function store(Request $request)
    {
        $user = Auth::user();
        $validated = $request->validate([
            'recipient_id' => 'required|exists:users,id',
            'body' => 'required|string|max:5000',
            'evaluation_id' => 'nullable|exists:evaluations,id',
        ]);

        $recipient = User::findOrFail($validated['recipient_id']);
        $senderRole = $user->roleName();
        $recipientRole = $recipient->roleName();

        $isSenderTeacher = in_array($senderRole, ['teacher', 'coordinador', 'director']);
        $isRecipientTeacher = in_array($recipientRole, ['teacher', 'coordinador', 'director']);

        if ($isSenderTeacher === $isRecipientTeacher) {
            return response()->json(['message' => 'La conversación debe ser entre un docente y un estudiante'], 422);
        }

        // Un docente no puede iniciar chat con un estudiante que no tenga relación
        $evaluationId = $validated['evaluation_id'] ?? null;
        if ($isSenderTeacher) {
            $teacherId = $user->id;
            $studentId = $recipient->id;
            $this->assertTeacherHasRelationWith($teacherId, $studentId, $evaluationId);
        } else {
            $teacherId = $recipient->id;
            $studentId = $user->id;
            $this->assertTeacherHasRelationWith($teacherId, $studentId, $evaluationId);
        }

        $conversation = Conversation::firstOrCreate(
            [
                'created_by' => $teacherId,
                'student_id' => $studentId,
                'evaluation_id' => $evaluationId,
            ],
            ['last_message_at' => now()]
        );

        $message = Message::create([
            'conversation_id' => $conversation->id,
            'sender_id' => $user->id,
            'body' => $validated['body'],
        ]);

        $conversation->update(['last_message_at' => now()]);

        return response()->json([
            'message' => 'Mensaje enviado',
            'conversation' => $conversation->load(['teacher:id,full_name', 'student:id,full_name']),
            'data' => $message->load('sender:id,full_name'),
        ], 201);
    }

    /**
     * Responder en una conversación existente.
     */
    public function reply(Request $request, $conversationId)
    {
        $user = Auth::user();
        $conversation = $this->findParticipantConversation($user, $conversationId);

        $validated = $request->validate([
            'body' => 'required|string|max:5000',
        ]);

        $message = Message::create([
            'conversation_id' => $conversation->id,
            'sender_id' => $user->id,
            'body' => $validated['body'],
        ]);

        $conversation->update(['last_message_at' => now()]);

        return response()->json([
            'message' => 'Mensaje enviado',
            'data' => $message->load('sender:id,full_name'),
        ], 201);
    }

    /**
     * Verifica que la conversación exista y el usuario participe (anti-IDOR).
     */
    private function findParticipantConversation($user, $conversationId)
    {
        $conversation = Conversation::find($conversationId);

        if (! $conversation) {
            abort(404, 'Conversación no encontrada');
        }

        if ((string) $conversation->teacher_id !== (string) $user->id && (string) $conversation->student_id !== (string) $user->id) {
            abort(403, 'No tienes permiso para acceder a esta conversación');
        }

        return $conversation;
    }

    /**
     * Valida que exista una relación académica entre docente y estudiante.
     */
    private function assertTeacherHasRelationWith($teacherId, $studentId, $evaluationId = null)
    {
        if ($evaluationId) {
            $eval = Evaluation::find($evaluationId);
            if (! $eval || (string) $eval->teacher_id !== (string) $teacherId) {
                abort(403, 'No tienes relación con esta evaluación');
            }
            $hasResult = EvaluationResult::where('evaluation_id', $evaluationId)
                ->where('user_id', $studentId)
                ->exists();
            if (! $hasResult) {
                abort(403, 'El estudiante no está relacionado con esta evaluación');
            }

            return;
        }

        // Relación general: alguna evaluación del docente tomada por el estudiante
        $hasRelation = EvaluationResult::where('user_id', $studentId)
            ->whereIn('evaluation_id', Evaluation::where('teacher_id', $teacherId)->pluck('id'))
            ->exists();

        if (! $hasRelation) {
            abort(403, 'No tienes relación académica con este estudiante');
        }
    }
}
