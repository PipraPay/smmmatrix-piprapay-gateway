<?php

namespace App\Services\Gateway\piprapay;

use Exception;
use Facades\App\Services\BasicService;

class Payment
{
    public static function prepareData($deposit, $gateway)
    {
        $params = $gateway->parameters;

        throw_if(empty($params->api_key ?? "") || empty($params->api_url ?? ""), "Unable to process with PipraPay.");

        $requestData = [
            'full_name'     => optional($deposit->user)->username ?? "John Doe",
            'email_mobile'  => optional($deposit->user)->email ?? "noemail@example.com",
            'amount'        => round($deposit->payable_amount, 2),
            'currency'      => $params->currency,
            'metadata'      => [
                'trx_id' => $deposit->trx_id,
            ],
            'redirect_url'  => route('user.add.fund'),
            'return_type'   => 'GET',
            'cancel_url'    => route('failed'),
            'webhook_url'   => route('ipn', [$gateway->code, $deposit->trx_id]),
        ];

        try {
            $redirect_url = self::initPayment($requestData, $params);
            return json_encode([
                'redirect' => true,
                'redirect_url' => $redirect_url
            ]);
        } catch (Exception $e) {
            return json_encode([
                'error' => true,
                'message' => $e->getMessage()
            ]);
        }
    }

    public static function ipn($request, $gateway, $deposit = null, $trx = null, $type = null)
    {
          $params = $gateway->parameters;

          $rawData = file_get_contents("php://input");
          $data = json_decode($rawData, true);
        
          $headers = getallheaders();
        
          $received_api_key = '';
        
          if (isset($headers['mh-piprapay-api-key'])) {
              $received_api_key = $headers['mh-piprapay-api-key'];
          } elseif (isset($headers['Mh-Piprapay-Api-Key'])) {
              $received_api_key = $headers['Mh-Piprapay-Api-Key'];
          } elseif (isset($_SERVER['HTTP_MH_PIPRAPAY_API_KEY'])) {
              $received_api_key = $_SERVER['HTTP_MH_PIPRAPAY_API_KEY']; // fallback if needed
          }
        
          if ($received_api_key !== $params->api_key) {
            return [
                'status' => 'error',
                'msg' => 'Unauthorized request.',
                'redirect' => route('failed')
            ];
          }

            $response = self::verifyPayment($data['pp_id'], $params);
    
            if (isset($response['status']) && strtolower($response['status']) === 'completed') {
                BasicService::preparePaymentUpgradation($deposit);
    
                return [
                    'status' => 'success',
                    'msg' => 'Transaction was successful.',
                    'redirect' => route('success')
                ];
            }
    
            return [
                'status' => 'error',
                'msg' => 'Payment verification failed.',
                'redirect' => route('failed')
            ];
    }

    public static function initPayment($requestData, $params)
    {
        $url = rtrim($params->api_url, '/') . '/api/create-charge';

        $curl = curl_init();
        curl_setopt_array($curl, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($requestData),
            CURLOPT_HTTPHEADER => [
                "mh-piprapay-api-key: " . $params->api_key,
                "accept: application/json",
                "content-type: application/json"
            ],
        ]);

        $response = curl_exec($curl);
        $error = curl_error($curl);
        curl_close($curl);

        if ($error) {
            throw new Exception("cURL Error: " . $error);
        }

        $result = json_decode($response, true);

        if (isset($result['status']) && isset($result['pp_url'])) {
            return $result['pp_url'];
        }

        throw new Exception($result['message'] ?? 'Failed to create PipraPay payment.');
    }

    public static function verifyPayment($pp_id, $params)
    {
        $url = rtrim($params->api_url, '/') . '/api/verify-payments';

        $payload = [
            'pp_id' => $pp_id
        ];

        $curl = curl_init();
        curl_setopt_array($curl, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($payload),
            CURLOPT_HTTPHEADER => [
                "mh-piprapay-api-key: " . $params->api_key,
                "accept: application/json",
                "content-type: application/json"
            ],
        ]);

        $response = curl_exec($curl);
        $error = curl_error($curl);
        curl_close($curl);

        if ($error) {
            throw new Exception("Verification cURL Error: " . $error);
        }

        return json_decode($response, true);
    }
}