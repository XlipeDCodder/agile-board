<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class InlineImageController extends Controller
{
    /**
     * Recebe uma imagem colada/arrastada no editor markdown (descrição ou
     * comentário) e retorna a URL pública pra ser referenciada no markdown.
     *
     * Diferente dos anexos de comentário (que aceitam qualquer arquivo de
     * projeto sem validar MIME — regra do usuário), aqui validamos que é
     * imagem e BLOQUEAMOS SVG: um .svg servido direto de /storage/ pode
     * conter <script> e causar XSS stored.
     *
     * O endpoint é "solto" (não vincula a item/comentário) — funciona pra
     * card novo (ainda sem id) e existente. A imagem vive referenciada no
     * texto markdown.
     */
    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'image' => ['required', 'image', 'mimes:jpg,jpeg,png,gif,webp', 'max:10240'],
        ]);

        $path = $request->file('image')->store('inline-images', 'public');

        return response()->json([
            'url' => '/storage/'.$path,
        ]);
    }
}
