<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class PayPalController extends Controller
{
    /**
     * @OA\Post(
     *     path="/paypal/create-order",
     *     summary="Crear orden de pago en PayPal",
     *     tags={"Pagos"},
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"consultant_id","date","hours"},
     *             @OA\Property(property="consultant_id", type="integer", example=5),
     *             @OA\Property(property="date", type="string", example="2024-10-23"),
     *             @OA\Property(
     *                 property="hours",
     *                 type="array",
     *                 @OA\Items(type="string", example="10:00")
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Orden de PayPal creada correctamente"
     *     ),
     *     @OA\Response(
     *         response=400,
     *         description="Horario no disponible o datos inválidos"
     *     ),
     *     @OA\Response(
     *         response=403,
     *         description="No autorizado"
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Error de validación"
     *     )
     * )
     */
    public function createOrder(Request $request)
    {
        try {
            $user = $request->user();
            $clientRoleId = 3; // Clientes en tu BD
            $consultantRoleId = 2; // Consultores en tu BD
            $pricePerHour = 50;

            if ($user->rol_id != $clientRoleId) {
                return response()->json([
                    'success' => false,
                    'message' => 'No autorizado. Solo clientes pueden crear reservas.'
                ], 403);
            }

            $validated = $request->validate([
                'consultant_id' => 'required|integer|exists:users,id',
                'date' => 'required|date_format:Y-m-d',
                'hours' => 'required|array|min:1',
                'hours.*' => 'required|string'
            ]);

            $consultant = \App\Models\User::where('id', $validated['consultant_id'])
                ->where('rol_id', $consultantRoleId)
                ->first();

            if (!$consultant) {
                return response()->json([
                    'success' => false,
                    'message' => 'El consultor seleccionado no existe o no es válido.'
                ], 404);
            }

            // Verificar que ninguna de las horas solicitadas esté ya reservada y pagada
            foreach ($validated['hours'] as $hour) {
                $exists = \App\Models\Reservation::where('consultant_id', $consultant->id)
                    ->whereDate('reservation_date', $validated['date'])
                    ->where('start_time', 'like', $hour . '%')
                    ->where('reservation_status', 'confirmada')
                    ->where('payment_status', 'pagado')
                    ->exists();

                if ($exists) {
                    return response()->json([
                        'success' => false,
                        'message' => "El horario {$hour} ya no está disponible."
                    ], 400);
                }
            }

            $totalAmount = count($validated['hours']) * $pricePerHour;

            $provider = new \Srmklive\PayPal\Services\PayPal;
            $provider->setApiCredentials(config('paypal'));
            $provider->getAccessToken();

            $response = $provider->createOrder([
                "intent" => "CAPTURE",
                "application_context" => [
                    "return_url" => url('/api/paypal/capture-order'),
                    "cancel_url" => url('/api/paypal/cancel-order'),
                ],
                "purchase_units" => [
                    [
                        "amount" => [
                            "currency_code" => "USD",
                            "value" => number_format($totalAmount, 2, '.', '')
                        ],
                        "description" => "Reserva de consultoría con {$consultant->nombres} {$consultant->apellidos}"
                    ]
                ]
            ]);

            if (isset($response['id']) && $response['id'] != null) {
                $approveUrl = null;
                foreach ($response['links'] as $link) {
                    if ($link['rel'] === 'approve') {
                        $approveUrl = $link['href'];
                        break;
                    }
                }

                // Crear las reservas en estado pendiente asociadas a esta orden
                $createdReservations = [];
                foreach ($validated['hours'] as $hour) {
                    $endHour = sprintf('%02d:00', ((int) explode(':', $hour)[0]) + 1);

                    $reservation = \App\Models\Reservation::create([
                        'user_id' => $user->id,
                        'consultant_id' => $consultant->id,
                        'reservation_date' => $validated['date'],
                        'start_time' => $hour,
                        'end_time' => $endHour,
                        'total_price' => $pricePerHour,
                        'reservation_status' => 'pendiente',
                        'payment_status' => 'pendiente',
                        'paypal_order_id' => $response['id']
                    ]);

                    $createdReservations[] = $reservation->id;
                }

                return response()->json([
                    'success' => true,
                    'message' => 'Orden de PayPal creada correctamente',
                    'data' => [
                        'order_id' => $response['id'],
                        'status' => $response['status'],
                        'approve_url' => $approveUrl,
                        'total_amount' => number_format($totalAmount, 2, '.', ''),
                        'reservation_ids' => $createdReservations
                    ]
                ], 200);
            }

            return response()->json([
                'success' => false,
                'message' => 'No se pudo generar la orden en PayPal.',
                'details' => $response
            ], 400);

        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error de validación',
                'errors' => $e->errors()
            ], 422);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al crear la orden de PayPal',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
