<?php

namespace App\Http\Controllers\Gateway;

use App\Http\Controllers\Controller;
use App\Models\AffiliateWithdraw;
use App\Models\SuitPayPayment;
use App\Models\Wallet;
use App\Models\Withdrawal;
use App\Traits\Gateways\SuitpayTrait;
use Filament\Notifications\Notification;
use Illuminate\Http\Request;
use App\Models\Transaction;

class SuitPayController extends Controller
{
    use SuitpayTrait;


    /**
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function callbackMethodPayment(Request $request)
    {
        $data = $request->all();
        \DB::table('debug')->insert(['text' => json_encode($request->all())]);

        return response()->json([], 200);
    }

    /**
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse|void
     */
    public function callbackMethod(Request $request)
    {
        $data = $request->all();
        \Log::info('[vizzerpay] Callback recebido', ['data' => $data]);
    
        try {
            // 1. Normalizar os nomes (Aceita tanto o padrão antigo quanto o novo)
            $externalId = $data['idTransaction'] ?? $data['reference_code'] ?? $data['requestNumber'] ?? null;
            $status = $data['statusTransaction'] ?? $data['status'] ?? null;
    
            // 2. Validação Flexível
            if (!$externalId || !$status) {
                \Log::error('[vizzerpay] Dados incompletos (ID ou Status faltando)', ['data' => $data]);
                return response()->json(['message' => 'Dados incompletos'], 400);
            }
    
            \Log::info('[vizzerpay] Processando transação', [
                'id' => $externalId,
                'status' => $status,
            ]);
    
            // 3. Verificar se foi PAGO (Aceita PAID e PAID_OUT)
            // PAID_OUT geralmente é usado para saques, mas algumas versões usam para Pix recebido também
            if (strtoupper($status) === 'PAID' || strtoupper($status) === 'PAID_OUT') {
                
                \Log::info('[vizzerpay] Status confirmado de pagamento. Finalizando...', ['id' => $externalId]);
    
                if (self::finalizePayment($externalId)) {
                    \Log::info('[vizzerpay] Sucesso! Saldo entregue.', ['id' => $externalId]);
                    return response()->json(['message' => 'Pagamento confirmado'], 200);
                } else {
                    \Log::warning('[vizzerpay] Transação não encontrada ou já paga.', ['id' => $externalId]);
                    // Retornamos 200 para a SuitPay parar de mandar o webhook, já que não vamos processar de novo
                    return response()->json(['message' => 'Transação já processada ou inexistente'], 200);
                }
            } 
            
            \Log::warning('[vizzerpay] Status ignorado', ['status' => $status]);
            return response()->json(['message' => 'Status ignorado'], 200);
    
        } catch (\Exception $e) {
            \Log::error('[vizzerpay] Erro fatal no callback', [
                'erro' => $e->getMessage(),
                'linha' => $e->getLine()
            ]);
            return response()->json(['message' => 'Erro interno'], 500);
        }
    }



    /**
     * @param Request $request
     * @return null
     */
    public function getQRCodePix(Request $request)
    {
        // Chama a trait
        $response = self::suitPayRequestQrcode($request);

        // Intercepta para garantir que o 'qrcode' seja o texto Copia e Cola
        if ($response instanceof \Illuminate\Http\JsonResponse) {
            $data = $response->getData(true);

            if (isset($data['paymentCode'])) {
                $data['qrcode'] = $data['paymentCode']; // Força o texto
            }
            
            // Garante que o token venha (importante para o frontend checar o status)
            if (isset($data['transactionId'])) {
                $data['token'] = $data['transactionId']; 
            }

            return response()->json($data);
        }

        return $response;
    }

    public function consultStatusTransactionPix(Request $request)
    {
        return self::suitPayConsultStatusTransaction($request);
    }

    /**
     * @param $id
     * @return \Illuminate\Http\RedirectResponse|void
     */
    public function confirmWithdrawalUser($id)
    {
        $withdrawal = Withdrawal::find($id);
        if (!empty($withdrawal)) {
            $suitpayment = SuitPayPayment::create([
                'withdrawal_id' => $withdrawal->id,
                'user_id' => $withdrawal->user_id,
                'pix_key' => $withdrawal->pix_key,
                'pix_type' => $withdrawal->pix_type,
                'amount' => $withdrawal->amount,
                'observation' => 'suitpay',
            ]);

            if ($suitpayment) {
                $parm = [
                    'pix_key' => $withdrawal->pix_key,
                    'pix_type' => $withdrawal->pix_type,
                    'amount' => $withdrawal->amount,
                    'suitpayment_id' => $suitpayment->id
                ];

                $resp = self::suitPayPixCashOut($parm);

                if ($resp) {
                    $withdrawal->update(['status' => 1]);
                    Notification::make()
                        ->title('Saque solicitado')
                        ->body('Saque solicitado com sucesso')
                        ->success()
                        ->send();

                    return back();
                } else {
                    Notification::make()
                        ->title('Erro no saque')
                        ->body('Erro ao solicitar o saque')
                        ->danger()
                        ->send();

                    return back();
                }
            }
        }
    }

    /**
     * @param $id
     * @return \Illuminate\Http\RedirectResponse|void
     */
    public function confirmWithdrawalAffiliate($id)
    {
        $withdrawal = AffiliateWithdraw::find($id);

        if (!empty($withdrawal)) {
            $suitpayment = SuitPayPayment::create([
                'withdrawal_id' => $withdrawal->id,
                'user_id' => $withdrawal->user_id,
                'pix_key' => $withdrawal->pix_key,
                'pix_type' => $withdrawal->pix_type,
                'amount' => $withdrawal->amount,
                'observation' => 'suitpay',
            ]);

            if ($suitpayment) {
                $parm = [
                    'pix_key' => $withdrawal->pix_key,
                    'pix_type' => $withdrawal->pix_type,
                    'amount' => $withdrawal->amount,
                    'suitpayment_id' => $suitpayment->id
                ];

                $resp = self::suitPayPixCashOut($parm);

                if ($resp) {
                    $withdrawal->update(['status' => 1]);
                    Notification::make()
                        ->title('Saque solicitado')
                        ->body('Saque solicitado com sucesso')
                        ->success()
                        ->send();

                    return back();
                } else {
                    Notification::make()
                        ->title('Erro no saque')
                        ->body('Erro ao solicitar o saque')
                        ->danger()
                        ->send();

                    return back();
                }
            }
        }
    }

    /**
     * Display the specified resource.
     */
    public function withdrawalFromModal($id, $action)
    {
        if ($action == 'user') {
            return $this->confirmWithdrawalUser($id);
        }

        if ($action == 'affiliate') {
            return $this->confirmWithdrawalAffiliate($id);
        }


    }

    /**
     * Cancel Withdrawal
     * @param $id
     * @return \Illuminate\Http\RedirectResponse
     */
    public function cancelWithdrawalFromModal($id, $action)
    {
        if ($action == 'user') {
            return $this->cancelWithdrawalUser($id);
        }

        if ($action == 'affiliate') {
            return $this->cancelWithdrawalAffiliate($id);
        }
    }

    /**
     * @param $id
     * @return \Illuminate\Http\RedirectResponse
     */
    private function cancelWithdrawalAffiliate($id)
    {
        $withdrawal = AffiliateWithdraw::find($id);
        if (!empty($withdrawal)) {
            $wallet = Wallet::where('user_id', $withdrawal->user_id)
                ->where('currency', $withdrawal->currency)
                ->first();

            if (!empty($wallet)) {
                $wallet->increment('refer_rewards', $withdrawal->amount);

                $withdrawal->update(['status' => 2]);
                Notification::make()
                    ->title('Saque cancelado')
                    ->body('Saque cancelado com sucesso')
                    ->success()
                    ->send();

                return back();
            }
            return back();
        }
        return back();
    }

    /**
     * @param $id
     * @return \Illuminate\Http\RedirectResponse
     */
    private function cancelWithdrawalUser($id)
    {
        $withdrawal = Withdrawal::find($id);
        if (!empty($withdrawal)) {
            $wallet = Wallet::where('user_id', $withdrawal->user_id)
                ->where('currency', $withdrawal->currency)
                ->first();

            if (!empty($wallet)) {
                $wallet->increment('balance_withdrawal', $withdrawal->amount);

                $withdrawal->update(['status' => 2]);
                Notification::make()
                    ->title('Saque cancelado')
                    ->body('Saque cancelado com sucesso')
                    ->success()
                    ->send();

                return back();
            }
            return back();
        }
        return back();
    }
    public function checkTransactionStatusByToken(Request $request)
    {
        // Validação dos parâmetros recebidos
        $request->validate([
            'token' => 'required|string',
        ]);

        // Obtém o token do request
        $token = $request->input('token');

        // Obtém o usuário autenticado
        $user = auth('api')->user();

        if (!$user) {
            return response()->json([
                'error' => 'Usuário não autenticado.',
            ], 401);
        }

        // Busca a transação na tabela usando o token e o user_id
        $transaction = Transaction::where('user_id', $user->id)
        ->where(function($query) use ($token) {
            $query->where('payment_id', $token)   // Tenta achar pelo Payment ID
                  ->orWhere('external_id', $token) // Tenta achar pelo External ID
                  ->orWhere('token', $token);      // Tenta achar pelo Token (fallback)
        })
        ->first();

        // Verifica se a transação foi encontrada
        if ($transaction) {
            $status = $transaction->status;
            $statusMessage = $status == 1 ? 'Confirmado' : 'Aguardando pagamento';

            return response()->json([
                'status' => $status,
                'status_message' => $statusMessage,
            ]);
        } else {
            return response()->json([
                'error' => 'Transação não encontrada.',
            ], 404);
        }
    }
}
