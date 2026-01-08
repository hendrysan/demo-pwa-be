<?php

namespace App\Http\Controllers;

use App\Models\Book;
use App\Models\Rental;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class RentalController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        /** @var User|null $user */
        $user = $request->attributes->get('jwt_user_model');

        if (!$user) {
            return response()->json([
                'message' => 'Unauthorized',
            ], 401);
        }

        $rentals = Rental::query()
            ->with('book')
            ->where('user_id', $user->id)
            ->orderBy('id', 'desc')
            ->paginate((int) $request->query('per_page', 15));

        return response()->json($rentals);
    }

    public function rent(Request $request): JsonResponse
    {
        /** @var User|null $user */
        $user = $request->attributes->get('jwt_user_model');

        if (!$user) {
            return response()->json([
                'message' => 'Unauthorized',
            ], 401);
        }

        $validator = Validator::make($request->all(), [
            'book_id' => ['required', 'integer'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation error',
                'errors' => $validator->errors(),
            ], 422);
        }

        $bookId = (int) $request->input('book_id');

        try {
            $rental = DB::transaction(function () use ($user, $bookId) {
                $book = Book::query()->lockForUpdate()->find($bookId);

                if (!$book) {
                    return null;
                }

                if ($book->stock <= 0) {
                    return false;
                }

                $book->stock = $book->stock - 1;
                $book->save();

                return Rental::query()->create([
                    'user_id' => $user->id,
                    'book_id' => $book->id,
                    'rented_at' => Carbon::now(),
                ]);
            });
        } catch (\Throwable $e) {
            return response()->json([
                'message' => 'Failed to rent book',
            ], 500);
        }

        if ($rental === null) {
            return response()->json([
                'message' => 'Book not found',
            ], 404);
        }

        if ($rental === false) {
            return response()->json([
                'message' => 'Book out of stock',
            ], 409);
        }

        $rental->load('book');

        return response()->json([
            'data' => $rental,
        ], 201);
    }

    public function returnBook(Request $request, int $id): JsonResponse
    {
        /** @var User|null $user */
        $user = $request->attributes->get('jwt_user_model');

        if (!$user) {
            return response()->json([
                'message' => 'Unauthorized',
            ], 401);
        }

        try {
            $result = DB::transaction(function () use ($user, $id) {
                $rental = Rental::query()->lockForUpdate()->find($id);

                if (!$rental) {
                    return null;
                }

                if ((int) $rental->user_id !== (int) $user->id) {
                    return 'forbidden';
                }

                if ($rental->returned_at !== null) {
                    return 'already_returned';
                }

                $book = Book::query()->lockForUpdate()->find($rental->book_id);

                if (!$book) {
                    return 'book_missing';
                }

                $rental->returned_at = Carbon::now();
                $rental->save();

                $book->stock = $book->stock + 1;
                $book->save();

                return $rental;
            });
        } catch (\Throwable $e) {
            return response()->json([
                'message' => 'Failed to return book',
            ], 500);
        }

        if ($result === null) {
            return response()->json([
                'message' => 'Rental not found',
            ], 404);
        }

        if ($result === 'forbidden') {
            return response()->json([
                'message' => 'Forbidden',
            ], 403);
        }

        if ($result === 'already_returned') {
            return response()->json([
                'message' => 'Rental already returned',
            ], 409);
        }

        if ($result === 'book_missing') {
            return response()->json([
                'message' => 'Book not found',
            ], 404);
        }

        $result->load('book');

        return response()->json([
            'data' => $result,
        ]);
    }
}
