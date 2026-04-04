<?php

namespace App\Controllers;

use CodeIgniter\Controller;
use App\Controllers\BaseController;
use App\Models\PembelianModel;
use App\Models\TopupModel;
use App\Models\UserModel;
use App\Models\ApiProviderModel;

class Callback extends BaseController
{
    public function __construct()
    {
        $this->pembelianModel = new PembelianModel();
        $this->pembelianModel = new TopupModel();
    }
    
public function callbackSakurupiah()
    {
        $settings = $this->getSettingsData();

            $apiProviderModel = new ApiProviderModel();
            $apiPd = $apiProviderModel->where('kode', 'Sp')->first();
        
            $merchant_id = $apiPd['api_id'];
            $apiKey = $apiPd['api_key'];
        
            $request = service('request');
            $json = $request->getBody();
        
            $callbackSignature = $_SERVER['HTTP_X_CALLBACK_SIGNATURE'] ?? '';
            $signature = hash_hmac('sha256', $json, $apiKey);
        
            if ($callbackSignature !== $signature) {
                exit(json_encode([
                    'success' => false,
                    'message' => 'Invalid signature',
                ]));
            }
        
            $data = json_decode($json, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                exit(json_encode([
                    'success' => false,
                    'message' => 'Invalid JSON data',
                ]));
            }
        
            if (!isset($_SERVER['HTTP_X_CALLBACK_EVENT']) || $_SERVER['HTTP_X_CALLBACK_EVENT'] !== 'payment_status') {
                exit(json_encode([
                    'success' => false,
                    'message' => 'Unrecognized callback event',
                ]));
            }
        
            try {
                if (!isset($data['status'], $data['merchant_ref'])) {
                    exit(json_encode([
                        'success' => false,
                        'message' => 'Data JSON tidak lengkap',
                    ]));
                }
        
                $payment_TrxID = $data['trx_id'];
                $payment_MerchantRef = $data['merchant_ref'];
                $payment_Status = $data['status'];
                $payment_StatusKode = $data['status_kode'];
        
                if ($payment_Status === "berhasil" && $payment_StatusKode == 1) {
                    // update transaksi ke Lunas /paid /berhasil
                    $pembelianModel = new PembelianModel();
                  $invoice = $pembelianModel
                      ->where('order_id', $payment_MerchantRef)
                      ->where('status_pembayaran', 'Unpaid')
                      ->first();
          
                  if ($invoice) {
                      
                      $orderData = [
                        'status_pembayaran' => 'Paid',
                        'status_pembelian' => 'Proses',
                        'note' => 'Pembelian sedang di proses',
                      ];
          
                     $updateStatus = $pembelianModel->update($invoice['id'], $orderData);
                      
                      if ($invoice['provider'] == 'Vip') {
                          $apiVip = $apiProviderModel->where('kode', 'Vip')->first();
                          
                          $apiId = $apiVip['api_id'];
                          $apiKey = $apiVip['api_key'];
                          $sign = md5($apiId . $apiKey);
                      
                          $data = [
                              'key' => $apiKey,
                              'sign' => $sign,
                              'type' => 'order',
                              'service' => $invoice['kode_produk'],
                              'data_no' => $invoice['uid'],
                              'data_zone' => ($invoice['server'] == 'NoServer') ? '' : $invoice['server'],
                          ];
                      
                          $url = 'https://vip-reseller.co.id/api/game-feature';
                      
                          $ch = curl_init();
                          curl_setopt($ch, CURLOPT_URL, $url);
                          curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
                          curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);
                          curl_setopt($ch, CURLOPT_POST, 1);
                          curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
                      
                          $result = curl_exec($ch);
                          $responseData = json_decode($result, true);
                      
                          curl_close($ch);
                      
                          if (isset($responseData['data'])) {
                              $postDataApi = [
                                  'trx_id' => $responseData['data']['trxid'],
                              ];
                      
                              $pembelianModel->update($invoice['id'], $postDataApi);
                      
                              $result = ['success' => true, 'message' => 'Berhasil mendapatkan data dari API.'];
                          } else {
                              $result = ['success' => false, 'message' => 'Gagal mendapatkan data dari API.'];
                          }
                      } elseif ($invoice['provider'] == 'DF') {
                          $apiDF = $apiProviderModel->where('kode', 'DF')->first();
                          
                          $userdigi = $apiDF['api_id'];
                          $apiKey = $apiDF['api_key'];
                      
                          $postData = [
                              'username' => $userdigi,
                              'buyer_sku_code' => $invoice['kode_produk'],
                              'customer_no' => ($invoice['server'] === 'NoServer') ? strval($invoice['uid']) : strval($invoice['uid']) . strval($invoice['server']),
                              'ref_id' => $invoice['order_id'],
                              'sign' => md5($userdigi . $apiKey . strval($invoice['order_id'])),
                          ];
                      
                          $ch = curl_init('https://api.digiflazz.com/v1/transaction');
                          curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
                          curl_setopt($ch, CURLOPT_POST, 1);
                          curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($postData));
                          curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
                      
                          $response = curl_exec($ch);
                          $responseData = json_decode($response, true);
                      
                          curl_close($ch);
                      
                          if (isset($responseData['data'])) {
                              $orderData = [
                                  'note' => $responseData['data']['message'],
                              ];
                      
                              $pembelianModel->update($invoice['id'], $orderData);
                          } else {
                              $result = ['success' => false, 'message' => 'Gagal mendapatkan data dari API.'];
                          }
                      } elseif ($invoice['provider'] == 'AG') {
                          $apiAG = $apiProviderModel->where('kode', 'AG')->first();
                          
                          $merchant_id = $apiAG['api_id'];
                          $secret_key = $apiAG['api_key'];
                          
                          $postData = [
                              'ref_id' => strval($invoice['order_id']),
                              'merchant_id' => $merchant_id,
                              'produk' => strval($invoice['kode_produk']),
                              'tujuan' => strval($invoice['uid']),
                              'server_id' => ($invoice['server'] == 'NoServer') ? '' : strval($invoice['server']),
                              'signature' => md5($merchant_id . ':' . $secret_key . ':' . strval($invoice['order_id'])),
                          ];
                      
                          $ch = curl_init('https://v1.apigames.id/v2/transaksi');
                          curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
                          curl_setopt($ch, CURLOPT_POST, 1);
                          curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($postData));
                          curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
                      
                          $response = curl_exec($ch);
                          $responseData = json_decode($response, true);
                      
                          curl_close($ch);
                      
                          if (isset($responseData['data'])) {
                              $orderData = [
                                  'trx_id' => $responseData['data']['trx_id'],
                                  'note' => 'Transaksi sedang diproses',
                              ];
                      
                              $pembelianModel->update($invoice['id'], $orderData);
                          } else {
                              $result = ['success' => false, 'message' => 'Gagal mendapatkan data dari API.'];
                          }
                      } elseif ($invoice['provider'] == 'Manual') {
                    
                        $orderData = [
                            'note' => 'Pesanan sedang di proses',
                        ];
                
                        $pembelianModel->update($invoice['id'], $orderData);
                        
                      } elseif ($invoice['provider'] == 'RG') {
                        $apiRG = $apiProviderModel->where('kode', 'AG')->first();
                        $apiKey = $apiRG['api_key'];
                        
                        $dataPost = [
                            'api_key' => $apiKey,
                            'nama' => $invoice['uid'],
                            'durasi' => $invoice['kode_produk'],
                            'game' => $invoice['games'],
                            'max_devices' => 1,
                        ];
                    
                        $url = 'https://server.rgmoba.com/api/order/register';
                    
                        $ch = curl_init();
                        curl_setopt($ch, CURLOPT_URL, $url);
                        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
                        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);
                        curl_setopt($ch, CURLOPT_POST, 1);
                        curl_setopt($ch, CURLOPT_POSTFIELDS, $dataPost);
                    
                        $result = curl_exec($ch);
                        $data_result = json_decode($result, true);  
                    
                        $orderData = [
                              'trx_id' => $data_result['data']['license'],
                              'status_pembelian' => 'Sukses',
                              'note' => 'Transaksi berhasil di proses',
                          ];
                      
                          $pembelianModel->update($invoice['id'], $orderData);
                        
                      } else {
                          $orderData = [
                              'status_pembelian' => 'Gagal',
                              'note' => 'Provider tidak di temukan',
                          ];
                      
                          $pembelianModel->update($invoice['id'], $orderData);
                      
                          $result = ['success' => false, 'message' => 'Provider tidak di temukan'];
                      }
                      
                      
                      $whatsappMessage = "*{$settings['web_title']}*\n\n";
                      $whatsappMessage .= "Pembayaran pesanan {$invoice['order_id']} *Terkonfirmasi* saat ini pesanan anda sedang di *Proses*\n";
                      $whatsappMessage .= "---------------------------\n\n";
                      $whatsappMessage .= "*Lihat Pesanan*\n" . base_url('/invoice/' . $invoice['order_id']) . "\n\n";
                      $whatsapp = $invoice['nomor_whatsapp'];
                      
                      $whatsappMessage .= "---------------------------\n\n";
                      $whatsappMessage .= "*Terimakasih!*";
                      $this->sendUserWhatsappMessage($whatsapp, $whatsappMessage);
        
                  } else {
                      $topupModel = new TopupModel();
                      $topup = $topupModel
                          ->where('topup_id', $payment_MerchantRef)
                          ->where('status', 'Unpaid')
                          ->first();
                          
                          if ($topup) {
                            $userModel = new UserModel();
                            $user = $userModel->find($topup['user_id']);
                            
                            $newBalance = $user['balance'] + $topup['nominal'];
                            
                            $updateUserBalance = $userModel->update($topup['user_id'], ['balance' => $newBalance]);
                            
                            $updateStatusTopup = $topupModel->update($topup['id'], ['status' => 'PAID']);
                            
                          } else {
                            $topupData = [
                                'status' => 'Gagal',
                            ];
                        
                            $topupModel->update($topup['id'], $topupData);
                        
                            $result = ['success' => false, 'message' => 'Top Up gagal di lakukan'];
                        }
                          
                  }
                 
                 exit(json_encode([
                    'success' => true,
                    'message' => 'Transaksi Berhasil Di Bayar',
                ]));
                } elseif ($payment_Status === "expired" && $payment_StatusKode == 2) {
                    // update transaksi ke Kadaluarsa
                       $topupModel = new TopupModel();
                      $topup = $topupModel
                          ->where('topup_id', $payment_MerchantRef)
                          ->where('status', 'Unpaid')
                          ->first();
                          
                          if ($topup) {
                            $updateStatusTopup = $topupModel->update($topup['id'], ['status' => 'Gagal']);
                          } else {
                            $topupData = [
                                'status' => 'Gagal',
                            ];
                        
                            $topupModel->update($topup['id'], $topupData);
                        }
                          
                    exit(json_encode([
                    'success' => true,
                    'message' => 'Transaksi Expired',
                ]));
                } elseif ($payment_Status === "pending" && $payment_StatusKode == 0) {
                    exit(json_encode([
                        'success' => true,
                        'message' => 'Status pending',
                    ]));
                } else {
                    throw new \Exception('Status tidak dikenali');
                }
        
                exit(json_encode(['success' => true, 'message' => 'Callback diproses']));
            } catch (\Exception $e) {
                exit(json_encode(['success' => false, 'message' => $e->getMessage()]));
            }
    }
    
    private function sendUserWhatsappMessage($whatsapp, $whatsappMessage)
    {
        $apiProviderModel = new ApiProviderModel();
        $api = $apiProviderModel->where('kode', 'Ft')->first();
        
        $curl = curl_init();
    
        $data = array(
            'target' => $whatsapp,
            'message' => $whatsappMessage,
        );
    
        curl_setopt_array($curl, array(
            CURLOPT_URL => 'https://api.fonnte.com/send',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $data,
            CURLOPT_HTTPHEADER => array('Authorization: ' . $api['api_key']),
        ));
        
        $response = curl_exec($curl);
        
        curl_close($curl);
        
        return $response;
    }
    
    // ============================================
    // Digiflazz Webhook Callback
    // URL: https://xyozistore.my.id/api/webhook/digiflazz
    // ============================================
    public function webhookDigiflazz()
    {
        log_message('info', 'Digiflazz webhook received: ' . json_encode($_POST));
        
        $request = service('request');
        $json = $request->getJSON(true);
        
        if (empty($json)) {
            // Coba get dari raw POST
            $json = $_POST;
            log_message('info', 'Digiflazz webhook (POST): ' . json_encode($json));
        }
        
        if (empty($json)) {
            log_message('error', 'Digiflazz webhook: No data received');
            return $this->response->setJSON([
                'success' => false,
                'message' => 'No data received'
            ]);
        }
        
        // Validasi signature (jika Digiflazz mengirimkan signature)
        // Secret yang diharapkan: XyoziStoreSecret2026
        $expectedSecret = 'XyoziStoreSecret2026';
        $receivedSignature = $_SERVER['HTTP_X_SIGNATURE'] ?? '';
        
        // Jika ada signature, validasi (opsional tergantung Digiflazz)
        if (!empty($receivedSignature) && $receivedSignature !== $expectedSecret) {
            log_message('error', 'Digiflazz webhook: Invalid signature');
            return $this->response->setStatusCode(403)->setJSON([
                'success' => false,
                'message' => 'Invalid signature'
            ]);
        }
        
        // Ambil data dari Digiflazz
        // Support format: $json['data']['ref_id'] atau $json['ref_id']
        $trxId = $json['data']['trx_id'] ?? $json['trx_id'] ?? $json['transaction_id'] ?? '';
        $refId = $json['data']['ref_id'] ?? $json['ref_id'] ?? $json['merchant_ref'] ?? $json['reference'] ?? '';
        $status = $json['data']['status'] ?? $json['status'] ?? '';
        $message = $json['data']['message'] ?? $json['message'] ?? $json['rc'] ?? '';
        
        log_message('info', 'Callback Digiflazz MASUK - ref_id: ' . ($refId ?? 'NULL') . ' - trx_id: ' . ($trxId ?? 'NULL'));
        log_message('info', "Digiflazz webhook RAW JSON: " . json_encode($json));
        log_message('info', "Digiflazz webhook data: trx_id=$trxId, ref_id=$refId, status=$status, message=$message");
        
        // Mapping status Digiflazz ke status database
        // Success: 'Sukses', '00', '1', 'Success'
        // Failed: 'Gagal', 'Failed', 'Error'
        // Pending: 'Pending', 'processing'
        $dbStatus = 'Unpaid';
        $orderStatus = 'Pending';
        
        $statusLower = strtolower($status);
        
        if (in_array($statusLower, ['sukses', 'success', '00', '1'])) {
            $dbStatus = 'Paid';
            $orderStatus = 'Sukses'; // Gunakan 'Sukses' sesuai database
        } elseif (in_array($statusLower, ['gagal', 'failed', 'error'])) {
            $dbStatus = 'Failed';
            $orderStatus = 'Gagal';
        } elseif ($statusLower === 'pending' || $statusLower === 'processing') {
            $dbStatus = 'Unpaid';
            $orderStatus = 'Pending';
        }
        
        log_message('info', "Digiflazz mapping: dbStatus=$dbStatus, orderStatus=$orderStatus");
        
        // Cari pesanan - coba berbagai kolom
        $pembelianModel = new PembelianModel();
        $order = null;
        
        // Coba cari berdasarkan order_id (ref_id dari Digiflazz)
        if (!empty($refId)) {
            $order = $pembelianModel->where('order_id', $refId)->first();
            log_message('info', "Digiflazz: Search by order_id=$refId result: " . ($order ? "FOUND id=".$order['id'] : "NOT FOUND"));
        }
        
        // Coba cari berdasarkan trx_id
        if (!$order && !empty($trxId)) {
            $order = $pembelianModel->where('trx_id', $trxId)->first();
            log_message('info', "Digiflazz: Search by trx_id=$trxId result: " . ($order ? "FOUND id=".$order['id'] : "NOT FOUND"));
        }
        
        // Coba cari berdasarkan invoice (jika refId adalah invoice)
        if (!$order && !empty($refId)) {
            $order = $pembelianModel->where('invoice', $refId)->first();
            log_message('info', "Digiflazz: Search by invoice=$refId result: " . ($order ? "FOUND id=".$order['id'] : "NOT FOUND"));
        }
        
        // Coba cari tanpa batasan status - ambil yang terbaru
        if (!$order && !empty($refId)) {
            $order = $pembelianModel->orderBy('id', 'DESC')->where('order_id', $refId)->first();
            log_message('info', "Digiflazz: Search without status filter result: " . ($order ? "FOUND id=".$order['id'] : "NOT FOUND"));
        }
        
        if (!$order) {
            log_message('error', "Digiflazz webhook: Order not found - ref_id: $refId, trx_id: $trxId - Tried all columns");
            return $this->response->setJSON([
                'success' => false,
                'message' => 'Order not found',
                'debug' => ['ref_id' => $refId, 'trx_id' => $trxId]
            ]);
        }
        
        log_message('info', "Digiflazz webhook: Found order ID: " . $order['id'] . ", current status: " . $order['status_pembelian']);
        
        // Update status pesanan
        $updateData = [
            'status_pembayaran' => $dbStatus,
            'status_pembelian' => $orderStatus,
            'note' => ($orderStatus === 'Sukses') ? 'Pesanan Sukses' : 'Pesanan Gagal'
        ];
        
        try {
            $pembelianModel->update($order['id'], $updateData);
            
            log_message('info', "Digiflazz webhook: Order $refId updated to status_pembelian=$orderStatus, status_pembayaran=$dbStatus");
            
            // Kirim WhatsApp hanya untuk Sukses atau Gagal
            if ($orderStatus === 'Sukses') {
                $this->sendWhatsAppNotification($order['nomor_whatsapp'], $order, 'sukses');
            } elseif ($orderStatus === 'Gagal') {
                $this->sendWhatsAppNotification($order['nomor_whatsapp'], $order, 'gagal');
            }
            
            return $this->response->setJSON([
                'success' => true,
                'message' => 'Status updated',
                'data' => [
                    'order_id' => $refId,
                    'trx_id' => $trxId,
                    'status' => $orderStatus
                ]
            ]);
        } catch (\Exception $e) {
            log_message('error', 'Digiflazz webhook error: ' . $e->getMessage());
            return $this->response->setJSON([
                'success' => false,
                'message' => $e->getMessage()
            ]);
        }
    }
    
    private function sendWhatsAppNotification($whatsapp, $order, $type)
    {
        if (empty($whatsapp)) {
            log_message('error', 'WhatsApp notification: No whatsapp number');
            return false;
        }
        
        $tanggal = date('d F Y', strtotime($order['created_at'] ?? date('Y-m-d')));
        
        if ($type === 'sukses') {
            $message = "⚡ *PEMBAYARAN BERHASIL!* ⚡\n\n";
            $message .= "Halo Kak, pesanan kamu di *Xyozi Store* sudah sukses terkirim!\n\n";
            $message .= "📝 *No. Invoice:* " . $order['order_id'] . " (Simpan untuk cek transaksi)\n";
            $message .= "🎮 *Produk:* " . $order['produk'] . "\n";
            $message .= "✅ *Status:* SUKSES\n";
            $message .= "📅 *Tanggal:* " . $tanggal . "\n\n";
            $message .= "*Terima kasih telah mempercayai Xyozi Store!* 🚀";
        } elseif ($type === 'gagal') {
            $message = "⚠️ *PEMBAYARAN GAGAL!* ⚠️\n\n";
            $message .= "Halo Kak, pesanan kamu di *Xyozi Store* gagal.\n\n";
            $message .= "📝 *No. Invoice:* " . $order['order_id'] . "\n";
            $message .= "🎮 *Produk:* " . $order['produk'] . "\n";
            $message .= "❌ *Status:* GAGAL\n";
            $message .= "📅 *Tanggal:* " . $tanggal . "\n\n";
            $message .= "*Silakan hubungi admin untuk info lebih lanjut!* 📩";
        } else {
            return false;
        }
        
        return $this->sendUserWhatsappMessage($whatsapp, $message);
    }
        
}