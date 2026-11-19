<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class ClientController extends Controller
{
    /**
     * @OA\Get(
     *     path="/my-reservations",
     *     summary="Listar reservas del cliente autenticado",
     *     tags={"Cliente"},
     *     security={{"bearerAuth":{}}},
     *
     *     @OA\Parameter(
     *         name="date",
     *         in="query",
     *         description="Filtrar por fecha YYYY-MM-DD",
     *         required=false,
     *         @OA\Schema(type="string", example="2024-10-23")
     *     ),
     *
     *     @OA\Response(
     *         response=200,
     *         description="Reservas obtenidas correctamente"
     *     ),
     *
     *     @OA\Response(
     *         response=403,
     *         description="No autorizado"
     *     ),
     *
     *     @OA\Response(
     *         response=401,
     *         description="No autenticado"
     *     )
     * )
     */
    public function myReservations(Request $request)
    {
        try {
            $user = $request->user();
            $clientRoleId = 3; // Ajustar según tu BD (en tu proyecto es 3; en la captura dice 1)

            if ($user->rol_id != $clientRoleId) {
                return response()->json([
                    'success' => false,
                    'message' => 'No autorizado. Solo clientes pueden acceder.'
                ], 403);
            }

            $query = \App\Models\Reservation::with([
                'consultant:id,nombres,apellidos,email,telefono,foto'
            ])->where('user_id', $user->id);

            // Filtro opcional por fecha
            if ($request->has('date')) {
                if (!\DateTime::createFromFormat('Y-m-d', $request->date)) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Formato de fecha inválido (YYYY-MM-DD)'
                    ], 400);
                }

                $query->whereDate('reservation_date', $request->date);
            }

            $reservations = $query
                ->orderBy('reservation_date', 'desc')
                ->orderBy('start_time', 'asc')
                ->paginate(10);

            return response()->json([
                'success' => true,
                'message' => 'Reservas obtenidas correctamente',
                'data' => $reservations
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener reservas',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * @OA\Get(
     *     path="/my-payments",
     *     summary="Listar historial de pagos del cliente autenticado",
     *     tags={"Cliente"},
     *     security={{"bearerAuth":{}}},
     *
     *     @OA\Parameter(
     *         name="page",
     *         in="query",
     *         description="Número de página",
     *         required=false,
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *
     *     @OA\Response(
     *         response=200,
     *         description="Pagos obtenidos correctamente"
     *     ),
     *
     *     @OA\Response(
     *         response=403,
     *         description="No autorizado"
     *     ),
     *
     *     @OA\Response(
     *         response=401,
     *         description="No autenticado"
     *     )
     * )
     */
    public function myPayments(Request $request)
    {
        try {
            $user = $request->user();
            $clientRoleId = 3; // En la captura dice 1; en tu BD los clientes tienen rol_id = 3

            if ($user->rol_id != $clientRoleId) {
                return response()->json([
                    'success' => false,
                    'message' => 'No autorizado. Solo clientes pueden acceder.'
                ], 403);
            }

            $payments = \App\Models\ReservationDetail::with([
                'reservation.consultant:id,nombres,apellidos,email,telefono'
            ])
                ->whereHas('reservation', function ($q) use ($user) {
                    $q->where('user_id', $user->id)
                      ->where('payment_status', 'pagado');
                })
                ->orderBy('created_at', 'desc')
                ->paginate(10);

            return response()->json([
                'success' => true,
                'message' => 'Pagos obtenidos correctamente',
                'data' => $payments
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener pagos',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * @OA\Get(
     *     path="/my-profile",
     *     summary="Obtener perfil del usuario autenticado",
     *     tags={"Cliente"},
     *     security={{"bearerAuth":{}}},
     *
     *     @OA\Response(
     *         response=200,
     *         description="Perfil obtenido correctamente"
     *     ),
     *
     *     @OA\Response(
     *         response=401,
     *         description="No autenticado"
     *     )
     * )
     */
    public function myProfile(Request $request)
    {
        try {
            $user = $request->user();

            return response()->json([
                'success' => true,
                'message' => 'Perfil obtenido correctamente',
                'data' => [
                    'id' => $user->id,
                    'nombres' => $user->nombres,
                    'apellidos' => $user->apellidos,
                    'email' => $user->email,
                    'telefono' => $user->telefono,
                    'foto' => $user->foto,
                    'rol_id' => $user->rol_id,
                    'created_at' => $user->created_at
                ]
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener perfil',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * @OA\Put(
     *     path="/my-profile",
     *     summary="Actualizar perfil del usuario autenticado",
     *     tags={"Cliente"},
     *     security={{"bearerAuth":{}}},
     *
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"nombres","apellidos","telefono"},
     *             @OA\Property(property="nombres", type="string", example="Juan"),
     *             @OA\Property(property="apellidos", type="string", example="Perez"),
     *             @OA\Property(property="telefono", type="string", example="987654321"),
     *             @OA\Property(property="foto", type="string", example="avatar.jpg")
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=200,
     *         description="Perfil actualizado correctamente"
     *     ),
     *
     *     @OA\Response(
     *         response=422,
     *         description="Error de validación"
     *     )
     * )
     */
    public function updateProfile(Request $request)
    {
        try {
            $user = $request->user();

            $validated = $request->validate([
                'nombres' => 'required|string|max:255',
                'apellidos' => 'required|string|max:255',
                'telefono' => 'required|string|max:20',
                'foto' => 'nullable|string|max:255'
            ]);

            $user->nombres = $validated['nombres'];
            $user->apellidos = $validated['apellidos'];
            $user->telefono = $validated['telefono'];

            if (array_key_exists('foto', $validated)) {
                $user->foto = $validated['foto'];
            }

            $user->save();

            return response()->json([
                'success' => true,
                'message' => 'Perfil actualizado correctamente',
                'data' => [
                    'id' => $user->id,
                    'nombres' => $user->nombres,
                    'apellidos' => $user->apellidos,
                    'email' => $user->email,
                    'telefono' => $user->telefono,
                    'foto' => $user->foto,
                    'rol_id' => $user->rol_id
                ]
            ], 200);

        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error de validación',
                'errors' => $e->errors()
            ], 422);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al actualizar perfil',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * @OA\Put(
     *     path="/change-password",
     *     summary="Cambiar contraseña del usuario autenticado",
     *     tags={"Cliente"},
     *     security={{"bearerAuth":{}}},
     *
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"current_password","new_password","new_password_confirmation"},
     *             @OA\Property(property="current_password", type="string", example="12345678"),
     *             @OA\Property(property="new_password", type="string", example="newPassword123"),
     *             @OA\Property(property="new_password_confirmation", type="string", example="newPassword123")
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=200,
     *         description="Contraseña actualizada correctamente"
     *     ),
     *
     *     @OA\Response(
     *         response=422,
     *         description="Error de validación"
     *     ),
     *
     *     @OA\Response(
     *         response=400,
     *         description="La contraseña actual es incorrecta"
     *     ),
     *
     *     @OA\Response(
     *         response=401,
     *         description="No autenticado"
     *     )
     * )
     */
    public function changePassword(Request $request)
    {
        try {
            $user = $request->user();

            $validated = $request->validate([
                'current_password' => 'required|string',
                'new_password' => 'required|string|min:6|confirmed'
            ]);

            if (!\Illuminate\Support\Facades\Hash::check($validated['current_password'], $user->password)) {
                return response()->json([
                    'success' => false,
                    'message' => 'La contraseña actual es incorrecta'
                ], 400);
            }

            $user->password = \Illuminate\Support\Facades\Hash::make($validated['new_password']);
            $user->save();

            return response()->json([
                'success' => true,
                'message' => 'Contraseña actualizada correctamente'
            ], 200);

        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error de validación',
                'errors' => $e->errors()
            ], 422);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al cambiar la contraseña',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
