<?php

namespace App\Http\Controllers;

use App\Models\Book;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class BookController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $books = Book::query()
            ->orderBy('id', 'desc')
            ->paginate((int) $request->query('per_page', 15));

        return response()->json($books);
    }

    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'title' => ['required', 'string', 'max:255'],
            'author' => ['nullable', 'string', 'max:255'],
            'stock' => ['nullable', 'integer', 'min:0'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation error',
                'errors' => $validator->errors(),
            ], 422);
        }

        $book = Book::query()->create([
            'title' => (string) $request->input('title'),
            'author' => $request->input('author') !== null ? (string) $request->input('author') : null,
            'stock' => (int) ($request->input('stock', 0)),
        ]);

        return response()->json([
            'data' => $book,
        ], 201);
    }
}
