<?php

namespace App\Http\Controllers;

use App\Services\Chat\ChatService;
use App\Services\Firebase\FirebaseException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ChatController extends Controller
{
    public function __construct(private readonly ChatService $chat)
    {
    }

    /**
     * Konteks percakapan untuk header chat (aman walau Firebase belum siap).
     */
    public function context(Request $request, int $reportId): JsonResponse
    {
        return response()
            ->json([
                'status' => 'success',
                'data' => $this->chat->context($request->user(), $reportId),
            ])
            ->header('Cache-Control', 'private, no-store');
    }

    /**
     * Menerbitkan custom token Firebase setelah hak akses diverifikasi.
     */
    public function session(Request $request, int $reportId): JsonResponse
    {
        try {
            $data = $this->chat->session($request->user(), $reportId);
        } catch (FirebaseException $exception) {
            return response()
                ->json([
                    'status' => 'error',
                    'message' => $exception->getMessage(),
                ], $exception->status)
                ->header('Cache-Control', 'private, no-store');
        }

        return response()
            ->json(['status' => 'success', 'data' => $data])
            ->header('Cache-Control', 'private, no-store');
    }

    /**
     * Mengambil daftar pesan percakapan laporan.
     */
    public function messages(Request $request, int $reportId): JsonResponse
    {
        try {
            $data = $this->chat->messages($request->user(), $reportId);
        } catch (FirebaseException $exception) {
            return response()
                ->json([
                    'status' => 'error',
                    'message' => $exception->getMessage(),
                ], $exception->status)
                ->header('Cache-Control', 'private, no-store');
        }

        return response()
            ->json(['status' => 'success', 'data' => $data])
            ->header('Cache-Control', 'private, no-store');
    }

    /**
     * Mengirim pesan baru ke percakapan laporan melalui backend API.
     */
    public function send(Request $request, int $reportId): JsonResponse
    {
        $validated = $request->validate([
            'message' => ['required', 'string', 'min:1', 'max:2000'],
        ]);

        try {
            $data = $this->chat->sendMessage($request->user(), $reportId, $validated['message']);
        } catch (FirebaseException $exception) {
            return response()
                ->json([
                    'status' => 'error',
                    'message' => $exception->getMessage(),
                ], $exception->status)
                ->header('Cache-Control', 'private, no-store');
        }

        return response()
            ->json(['status' => 'success', 'data' => $data], 201)
            ->header('Cache-Control', 'private, no-store');
    }
}
