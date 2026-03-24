<?php

namespace App\Http\Controllers;

use App\Services\Ai\GeminiService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AiController extends Controller
{
    public function __construct(
        protected GeminiService $aiService
    ) {}

    public function chat(Request $request): JsonResponse
    {
        $request->validate([
            'message' => 'nullable|string|max:1000',
            'history' => 'nullable|array',
            'image' => 'nullable|array',
            'image.mime_type' => 'required_with:image|string',
            'image.data' => 'required_with:image|string',
        ]);

        $response = $this->aiService->chat(
            $request->user(),
            $request->input('message') ?? 'Por favor, procesa esta imagen o factura que te adjunto para identificar los montos de la compra.',
            $request->input('history', []),
            $request->input('image')
        );

        return response()->json([
            'message' => $response
        ]);
    }
}
