<?php

namespace App\Controllers;

use CodeIgniter\Controller;
use App\Controllers\BaseController;
use App\Models\UserModel;
use App\Models\ProdukModel;
use App\Models\MlbbKategori;
use App\Models\MlbbLayanan;
use App\Models\PembelianModel;
use App\Models\ApiProviderModel;
use App\Models\VoucherModel;
use CodeIgniter\API\ResponseTrait;
use CodeIgniter\Database\Exceptions\DatabaseException;
use CodeIgniter\Database\ConnectionInterface;
use CodeIgniter\Log\Logger;

class Order extends BaseController
{

    public function __construct()
    {
        $this->session = \Config\Services::session();
        $this->pembelianModel = new PembelianModel();
        $this->userModel = new UserModel();
    }
    
    public function cekID()
    {
        $settings = $this->getSettingsData();
        
        $apiProviderModel = new ApiProviderModel();
        $api = $apiProviderModel->where('kode', 'Vip')->first();
        
        if (empty($api)) {
            return $this->response->setJSON(['error' => 'API Provider tidak ditemukan atau tidak aktif.']);
        }
        
        $apiId = $api['api_id'];
        $apiKey = $api['api_key'];
    
        $json = $this->request->getJSON();
    
        $uid = $json->uid ?? '';
        $server = $json->server ?? '';
        $target = $json->target ?? '';
    
        if (empty($uid) || empty($server)) {
            return $this->response->setJSON(['error' => 'UID dan server harus diisi.']);
        }
    
        $data = [
            'key' => $apiKey,
            'sign' => md5($apiId . $apiKey),
            'type' => 'get-nickname',
            'code' => $target,
            'target' => $uid,
            'additional_target' => $server,
        ];
    
        $url = 'https://vip-reseller.co.id/api/game-feature';
    
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
    
        $result = curl_exec($ch);
    
        if (curl_errno($ch)) {
            $errorMessage = 'Kesalahan Curl: ' . curl_error($ch);
            return $this->response->setJSON(['error' => $errorMessage]);
        }
    
        curl_close($ch);
    
        $result = json_decode($result, true);
    
        if ($result['result']) {
            return $this->response->setJSON(['responseData' => $result['data']]);
        } else {
            $errorMessage = isset($result['message']) ? $result['message'] : 'Error: Terjadi kesalahan. Silakan coba lagi nanti.';
            return $this->response->setJSON(['error' => $errorMessage]);
        }
    }
    
    public function payment()
    {
        $userModel = new UserModel();
        
        $userLogin = $this->session->has('isLogin');
        $username = '';
        
        if ($userLogin) {
            $username = $this->session->get('username');
            $user = $this->userModel->where('username', $username)->first();
        } else {
            $user = null;
        }
        
        $json = $this->request->getJSON();
        $uid = strip_tags(htmlspecialchars(html_entity_decode($json->uid ?? '', ENT_QUOTES, 'UTF-8')));
        $server = strip_tags(htmlspecialchars(html_entity_decode($json->server ?? '', ENT_QUOTES, 'UTF-8')));
        $username = strip_tags(htmlspecialchars(html_entity_decode($json->username ?? '', ENT_QUOTES, 'UTF-8')));
        $productPrice = strip_tags(htmlspecialchars(html_entity_decode($json->productPrice ?? '', ENT_QUOTES, 'UTF-8')));
        $productName = strip_tags(htmlspecialchars(html_entity_decode($json->productName ?? '', ENT_QUOTES, 'UTF-8')));
        $productCode = strip_tags(htmlspecialchars(html_entity_decode($json->productCode ?? '', ENT_QUOTES, 'UTF-8')));
        $metodeCode = strip_tags(htmlspecialchars(html_entity_decode($json->metodeCode ?? '', ENT_QUOTES, 'UTF-8')));
        $metodeName = strip_tags(htmlspecialchars(html_entity_decode($json->metodeName ?? '', ENT_QUOTES, 'UTF-8')));
        $whatsapp = strip_tags(htmlspecialchars(html_entity_decode($json->whatsapp ?? '', ENT_QUOTES, 'UTF-8')));
        $voucher = strip_tags(htmlspecialchars(html_entity_decode($json->voucher ?? '', ENT_QUOTES, 'UTF-8')));
        
        //MLBB
        $namanya = strip_tags(htmlspecialchars(html_entity_decode($json->nama ?? '', ENT_QUOTES, 'UTF-8')));
        $targetnya = strip_tags(htmlspecialchars(html_entity_decode($json->target ?? '', ENT_QUOTES, 'UTF-8')));
        $providernya = strip_tags(htmlspecialchars(html_entity_decode($json->provider ?? '', ENT_QUOTES, 'UTF-8')));
        
        if($providernya == "RG"){
        $produkModel = new MlbbLayanan();
        $produk = $produkModel->where('durasi', $productCode)->first();
        $games = $produk['kode'];
        $hargaProvider = $produk['harga_provider'];
        $keuntungan = $produk['keuntungan'];
        $provider = $produk['provider'];
        if ($productPrice == $produk['harga_jual']) {
            $hargaJual = $produk['harga_jual'];
        } elseif ($productPrice == $produk['harga_basic']) {
            $hargaJual = $produk['harga_basic'];
        } elseif ($productPrice == $produk['harga_gold']) {
            $hargaJual = $produk['harga_gold'];
        } elseif ($productPrice == $produk['harga_platinum']) {
            $hargaJual = $produk['harga_platinum'];
        } else {
            $hargaJual = $produk['harga_jual'];
        }
        } else {
        $produkModel = new ProdukModel();
        $produk = $produkModel->where('kode_produk', $productCode)->first();
        $games = $produk['brand'];
        $hargaProvider = $produk['harga_provider'];
        $keuntungan = $produk['keuntungan'];
        $provider = $produk['provider'];  
        if ($productPrice == $produk['harga_jual']) {
            $hargaJual = $produk['harga_jual'];
        } elseif ($productPrice == $produk['harga_basic']) {
            $hargaJual = $produk['harga_basic'];
        } elseif ($productPrice == $produk['harga_gold']) {
            $hargaJual = $produk['harga_gold'];
        } elseif ($productPrice == $produk['harga_platinum']) {
            $hargaJual = $produk['harga_platinum'];
        } else {
            $hargaJual = $produk['harga_jual'];
        }
        }

        //START VOUCHER - with null check
        $voucherModel = new VoucherModel();
        $voucherKode = null;
        $kodeVoucher = null;
        $stok = 0;
        $persen = 0;
        $maksimal_potongan = 0;
        
        // Validasi metode pembayaran harus dipilih
        if (empty($metodeCode)) {
            return $this->response->setJSON(['success' => false, 'message' => 'Pilih metode pembayaran!']);
        }
        
        // Validasi produk tidak kosong
        if (empty($produk)) {
            return $this->response->setJSON(['success' => false, 'message' => 'Produk tidak ditemukan!']);
        }
        
        // Validasi field produk yang diperlukan
        if (empty($produk['kode_produk']) || empty($produk['provider'])) {
            return $this->response->setJSON(['success' => false, 'message' => 'Produk tidak lengkap, hubungi administrator!']);
        }
        
        // Validasi API provider aktif
        $apiProviderModel = new ApiProviderModel();
        $providerKode = $produk['provider'];
        
        // Mapping provider code
        $providerMap = [
            'Vip' => 'Vip',
            'DF' => 'DF',
            'AG' => 'AG',
            'RG' => 'RG',
            'Manual' => null, // Tidak perlu API
            'Sp' => 'Sp',
            'Ft' => 'Ft'
        ];
        
        if (isset($providerMap[$providerKode]) && $providerMap[$providerKode] !== null) {
            $apiProvider = $apiProviderModel->where('kode', $providerMap[$providerKode])->first();
            if (empty($apiProvider)) {
                return $this->response->setJSON(['success' => false, 'message' => 'Provider/API tidak aktif, hubungi administrator!']);
            }
        }
        
        // Cek voucher hanya jika diisi
        if (!empty($voucher)) {
            $voucherKode = $voucherModel->where('kode', $voucher)->first();
            
            if (empty($voucherKode)) {
                return $this->response->setJSON(['success' => false, 'message' => 'Kode voucher tidak valid!']);
            }
            
            $kodeVoucher = $voucherKode['kode'];
            $stok = $voucherKode['stok'];
            $persen = $voucherKode['persen'];
            $maksimal_potongan = $voucherKode['max_potongan'];
            
            if ($stok == 0) {
                return $this->response->setJSON(['success' => false, 'message' => 'Stok voucher habis!']);
            }
            
            // Hitung diskon
            $discount = (($hargaJual * $persen) / 100);
            $PostHarga_Jual = ($hargaJual - $discount);
            $PostJual = $PostHarga_Jual;
            
            if ($discount > $maksimal_potongan) {
                $hargaJual = $hargaJual - $maksimal_potongan;
            } else {
                $hargaJual = $hargaJual - $discount;
            }
            
            // Kurangi stok voucher
            $stokAkhir = $stok - 1;
            $dataVoucher = ['stok' => $stokAkhir];
            $voucherModel->update($voucherKode['id'], $dataVoucher);
        }
        
        // Generate unique order ID
        $uniqueOrderID = false;
        $maxAttempts = 10;
    
        for ($i = 0; $i < $maxAttempts; $i++) {
            $orderID = strval(mt_rand(100000, 999999));
    
            $existingOrderID = $this->pembelianModel->where('order_id', $orderID)->first();
    
            if (!$existingOrderID) {
                $uniqueOrderID = true;
                break;
            }
        }
        
        if (!$uniqueOrderID) {
            return $this->response->setJSON(['success' => false, 'message' => 'Gagal mendapatkan nomor pesanan setelah sejumlah percobaan, hubungi administrator!!.']);
        }
        
        $orderID = strval($orderID);
    
        $settings = $this->getSettingsData();
        
        if ($metodeCode == 'saldo') {
          
          if (!$this->session->has('isLogin')) {
            return $this->response->setJSON(['success' => false, 'message' => 'Anda harus login untuk menggunakan metode pembayaran Saldo!']);
          }
          
          if ($user['balance'] < $hargaJual) {
              return $this->response->setJSON(['success' => false, 'message' => 'Saldo anda tidak mencukupi']);
          } else {
            $apiProviderModel = new ApiProviderModel();
            
            if ($produk['provider'] == 'Vip') {
                $apiVip = $apiProviderModel->where('kode', 'Vip')->first();
                
                if (empty($apiVip)) {
                    return $this->response->setJSON(['success' => false, 'message' => 'API Vip tidak aktif!']);
                }
                
                $apiId = $apiVip['api_id'];
                $apiKey = $apiVip['api_key'];
                $sign = md5($apiId . $apiKey);
            
                $data = [
                    'key' => $apiKey,
                    'sign' => $sign,
                    'type' => 'order',
                    'service' => $produk['kode_produk'],
                    'data_no' => $uid,
                    'data_zone' => ($server == 'NoServer') ? '' : $server,
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
                    $data = [
                        'user_id' => $user['id'],
                        'order_id' => $orderID,
                        'trx_id' => $responseData['data']['trxid'],
                        'games' => $games,
                        'produk' => $produk['nama'],
                        'kode_produk' => $produk['kode_produk'],
                        'uid' => $uid,
                        'server' => $server,
                        'nama_target' => $username,
                        'harga_provider' => $produk['harga_provider'],
                        'harga_jual' => $hargaJual,
                        'keuntungan' => $keuntungan,
                        'fee' => 0,
                        'total_pembayaran' => $hargaJual,
                        'provider' => $provider,
                        'metode_pembayaran' => $metodeName,
                        'kode_pembayaran' => 'Pembayaran via saldo',
                        'status_pembayaran' => 'Paid',
                        'status_pembelian' => 'Proses',
                        'status_refund' => 'gagal',
                        'nomor_whatsapp' => $whatsapp,
                        'note' => 'Transaksi sedang di proses',
                    ];
               
                    try {
                        $this->pembelianModel->insert($data);
                    } catch (\Exception $e) {
                        return $this->response->setJSON(['success' => false, 'message' => 'Gagal menyimpan pesanan: ' . $e->getMessage()]);
                    }
                    
                    $newBalance = floatval($user['balance']) - floatval($hargaJual);
                    $userModel->update($user['id'], ['balance' => $newBalance]);
              
                    return $this->response->setJSON(['success' => true, 'orderID' => $orderID]);
                } else {
                    return $this->response->setJSON(['success' => false, 'message' => 'Gagal kesalahan Provider, msg: ' . ($responseData['message'] ?? 'Unknown error')]);
                }
            } elseif ($produk['provider'] == 'DF') {
                $apiDF = $apiProviderModel->where('kode', 'DF')->first();
                
                if (empty($apiDF)) {
                    return $this->response->setJSON(['success' => false, 'message' => 'API DF tidak aktif!']);
                }
                
                $userdigi = $apiDF['api_id'];
                $apiKey = $apiDF['api_key'];
            
                $postData = [
                    'username' => $userdigi,
                    'buyer_sku_code' => $produk['kode_produk'],
                    'customer_no' => ($server === 'NoServer') ? strval($uid) : strval($uid) . strval($server),
                    'ref_id' => $orderID,
                    'sign' => md5($userdigi . $apiKey . strval($orderID)),
                ];
            
                $ch = curl_init('https://api.digiflazz.com/v1/transaction');
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
                curl_setopt($ch, CURLOPT_POST, 1);
                curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($postData));
                curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
            
                $response = curl_exec($ch);
                $responseData = json_decode($response, true);
            
                if (isset($responseData['data'])) {
                    
                    $data = [
                        'user_id' => $user['id'],
                        'order_id' => $orderID,
                        'games' => $games,
                        'produk' => $produk['nama'],
                        'kode_produk' => $produk['kode_produk'],
                        'uid' => $uid,
                        'server' => $server,
                        'nama_target' => $username,
                        'harga_provider' => $produk['harga_provider'],
                        'harga_jual' => $hargaJual,
                        'keuntungan' => $keuntungan,
                        'fee' => 0,
                        'total_pembayaran' => $hargaJual,
                        'provider' => $provider,
                        'metode_pembayaran' => $metodeName,
                        'kode_pembayaran' => 'Pembayaran via saldo',
                        'status_pembayaran' => 'Paid',
                        'status_pembelian' => 'Proses',
                        'status_refund' => 'gagal',
                        'nomor_whatsapp' => $whatsapp,
                        'note' => $responseData['data']['message'] ?? 'Transaksi diproses',
                    ];
               
                    try {
                        $this->pembelianModel->insert($data);
                    } catch (\Exception $e) {
                        return $this->response->setJSON(['success' => false, 'message' => 'Gagal menyimpan pesanan: ' . $e->getMessage()]);
                    }
                    
                    $newBalance = floatval($user['balance']) - floatval($hargaJual);
                    $userModel->update($user['id'], ['balance' => $newBalance]);
                    
                    return $this->response->setJSON(['success' => true, 'orderID' => $orderID]);
                    
                } else {
                  return $this->response->setJSON(['success' => false, 'message' => 'Gagal kesalahan Provider, msg: ' . ($responseData['data']['rc'] ?? 'Unknown error')]);
                }
            } elseif ($produk['provider'] == 'AG') {
                $apiAG = $apiProviderModel->where('kode', 'AG')->first();
                
                if (empty($apiAG)) {
                    return $this->response->setJSON(['success' => false, 'message' => 'API AG tidak aktif!']);
                }
                
                $merchant_id = $apiAG['api_id'];
                $secret_key = $apiAG['api_key'];
            
                $postData = [
                    'ref_id' => $orderID,
                    'merchant_id' => $merchant_id,
                    'produk' => $produk['kode_produk'],
                    'tujuan' => $uid,
                    'server_id' => ($server == 'NoServer') ? '' : $server,
                    'signature' => md5($merchant_id . ':' . $secret_key . ':' . $orderID),
                ];
            
                $ch = curl_init('https://v1.apigames.id/v2/transaksi');
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
                curl_setopt($ch, CURLOPT_POST, 1);
                curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($postData));
                curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
            
                $response = curl_exec($ch);
                $responseData = json_decode($response, true);
            
                if (isset($responseData['data'])) {
                    
                    $data = [
                        'user_id' => $user['id'],
                        'order_id' => $orderID,
                        'trx_id' => $responseData['data']['trx_id'],
                        'games' => $games,
                        'produk' => $produk['nama'],
                        'kode_produk' => $produk['kode_produk'],
                        'uid' => $uid,
                        'server' => $server,
                        'nama_target' => $username,
                        'harga_provider' => $produk['harga_provider'],
                        'harga_jual' => $hargaJual,
                        'keuntungan' => $keuntungan,
                        'fee' => 0,
                        'total_pembayaran' => $hargaJual,
                        'provider' => $provider,
                        'metode_pembayaran' => $metodeName,
                        'kode_pembayaran' => 'Pembayaran via saldo',
                        'status_pembayaran' => 'Paid',
                        'status_pembelian' => 'Proses',
                        'status_refund' => 'gagal',
                        'nomor_whatsapp' => $whatsapp,
                        'note' => 'Transaksi sedang diproses',
                    ];
               
                    try {
                        $this->pembelianModel->insert($data);
                    } catch (\Exception $e) {
                        return $this->response->setJSON(['success' => false, 'message' => 'Gagal menyimpan pesanan: ' . $e->getMessage()]);
                    }
                    
                    $newBalance = floatval($user['balance']) - floatval($hargaJual);
                    $userModel->update($user['id'], ['balance' => $newBalance]);
                    
                    return $this->response->setJSON(['success' => true, 'orderID' => $orderID]);
                    
                } else {
                  return $this->response->setJSON(['success' => false, 'message' => 'Gagal kesalahan Provider, msg: ' . ($responseData['error_msg'] ?? 'Unknown error')]);
                }
            } elseif ($produk['provider'] == 'RG') {
                
                $apiProviderModel = new ApiProviderModel();
                $api = $apiProviderModel->where('kode', 'RG')->first();
                
                if (empty($api)) {
                    return $this->response->setJSON(['success' => false, 'message' => 'API RG tidak aktif!']);
                }
                
                $apiKey = $api['api_key'];
                
                $dataPost = [
                    'api_key' => $apiKey,
                    'nama' => $namanya,
                    'durasi' => $produk['durasi'],
                    'game' => $produk['kode'],
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
                
                $data = [
                    'user_id' => $user['id'],
                    'order_id' => $orderID,
                    'trx_id' => $data_result['data']['license'],
                    'games' => $games,
                    'produk' => ''.$produk['durasi'].' '.$produk['tipe'].'',
                    'kode_produk' => $produk['durasi'],
                    'uid' => $namanya,
                    'server' => $server,
                    'nama_target' => $username,
                    'harga_provider' => $produk['harga_provider'],
                    'harga_jual' => $hargaJual,
                    'keuntungan' => $keuntungan,
                    'fee' => 0,
                    'total_pembayaran' => $hargaJual,
                    'provider' => $provider,
                    'metode_pembayaran' => $metodeName,
                    'kode_pembayaran' => 'Pembayaran via saldo',
                    'status_pembayaran' => 'Paid',
                    'status_pembelian' => 'Sukses',
                    'status_refund' => 'gagal', 
                    'nomor_whatsapp' => $whatsapp,
                    'note' => 'Pesanan sudah di proses',
                ];
           
                try {
                    $this->pembelianModel->insert($data);
                } catch (\Exception $e) {
                    return $this->response->setJSON(['success' => false, 'message' => 'Gagal menyimpan pesanan: ' . $e->getMessage()]);
                }
                
                $newBalance = floatval($user['balance']) - floatval($hargaJual);
                $userModel->update($user['id'], ['balance' => $newBalance]);
                
                return $this->response->setJSON(['success' => true, 'orderID' => $orderID]);

            } elseif ($produk['provider'] == 'Manual') {
                    
                $data = [
                    'user_id' => $user['id'],
                    'order_id' => $orderID,
                    'games' => $games,
                    'produk' => ''.$produk['durasi'].' '.$produk['tipe'].'',
                    'kode_produk' => $produk['durasi'],
                    'uid' => $uid,
                    'server' => $server,
                    'nama_target' => $username,
                    'harga_provider' => $produk['harga_provider'],
                    'harga_jual' => $hargaJual,
                    'keuntungan' => $keuntungan,
                    'fee' => 0,
                    'total_pembayaran' => $hargaJual,
                    'provider' => $provider,
                    'metode_pembayaran' => $metodeName,
                    'kode_pembayaran' => 'Pembayaran via saldo',
                    'status_pembayaran' => 'Paid',
                    'status_pembelian' => 'Prosess',
                    'status_refund' => 'gagal', 
                    'nomor_whatsapp' => $whatsapp,
                    'note' => 'Pesanan sedang di proses',
                ];
          
                try {
                    $this->pembelianModel->insert($data);
                } catch (\Exception $e) {
                    return $this->response->setJSON(['success' => false, 'message' => 'Gagal menyimpan pesanan: ' . $e->getMessage()]);
                }
                
                $newBalance = floatval($user['balance']) - floatval($hargaJual);
                $userModel->update($user['id'], ['balance' => $newBalance]);
                
                return $this->response->setJSON(['success' => true, 'orderID' => $orderID]);
                    
            } else {
                return $this->response->setJSON(['success' => false, 'message' => 'Provider tidak di temukan, hubungi administrator!.']);
            }
            
          }
          
        } else {
          
          $apiProviderModel = new ApiProviderModel();
          $api = $apiProviderModel->where('kode', 'Sp')->first();
          
          if (empty($api)) {
              return $this->response->setJSON(['success' => false, 'message' => 'Payment Gateway tidak aktif!']);
          }
          
            $api_id = $api['api_id']; 
            $data_method =$metodeCode; 
            $merchant_ref = $orderID; 
            $amount = $hargaJual;
            $apikey = $api['api_key'];
            $URL_config = $api['private_key'];
            
            $signature = hash_hmac('sha256', $api_id.$data_method.$merchant_ref.$amount,$apikey);
            
            $dataPOST = array(
            'api_id' => $api_id,
            'method' => $data_method,
            'phone' => !empty($whatsapp) ? $whatsapp : $user['whatsapp'],
            'amount' => $amount,
            'merchant_fee' => '2',
            'merchant_ref' => $merchant_ref,
            'expired' => '1',
            'produk' => array($games | $produk['nama']),
            'qty' => array('1'),
            'harga' => array($amount),
            'size' => array('Uid:'.$namanya.' | Server '.$server.' '),
            'note' => array('Username : '.empty($user['username']) ? "Tidak Login" : $user['username'].''),
            'callback_url' => ''.$URL_config.'/callback',                      
            'return_url' => ''.$URL_config.'/invoice/'.$merchant_ref.'',
            'signature' => $signature
        );
        
        
        $curl = curl_init();
        
        curl_setopt_array($curl, array(
          CURLOPT_URL => 'https://sakurupiah.id/api/create.php',
          CURLOPT_RETURNTRANSFER => true,
          CURLOPT_ENCODING => '',
          CURLOPT_MAXREDIRS => 10,
          CURLOPT_TIMEOUT => 0,
          CURLOPT_FOLLOWLOCATION => true,
          CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
          CURLOPT_CUSTOMREQUEST => 'POST',
          CURLOPT_POSTFIELDS => http_build_query($dataPOST),
          CURLOPT_HTTPHEADER => array(
            'Authorization: Bearer '.$apikey
          ),
        ));
          
         $response = curl_exec($curl);
    
        if (curl_errno($curl)) {
            echo 'Error:' . curl_error($curl);
        }
    
        curl_close($curl);
    
        $responseData = json_decode($response, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            log_message('error', 'JSON decode error: '.json_last_error_msg());
            log_message('error', 'Raw response: '.$response);
            return $this->response->setJSON(['success'=>false,'message'=>'Format JSON API tidak valid']);
        }
        
        $status = (int) ($responseData['status'] ?? 0);
        if ($status === 400) {
            return $this->response->setJSON([
                'success' => false,
                'message' => $responseData['message'] ?? 'Error 400 dari gateway'
            ]);
        }
        
          if ($status === 200 && isset($responseData['data'])) {
            $dataItem = $responseData['data'][0];
              
            $batas_pembayaran = (new \DateTime('now', new \DateTimeZone(date_default_timezone_get())))
                                ->modify('+60 minutes')->format('Y-m-d H:i:s');
            $postFee = $dataItem['total'] - $dataItem['amount_merchant'];
            
            if($provider == "RG"){
                $data = [
                  'user_id' => empty($user['id']) ? "Tidak Login" : $user['id'],
                  'order_id' => $merchant_ref,
                  'games' => $games,
                  'produk' => ''.$produk['durasi'].' '.$produk['tipe'].'',
                  'kode_produk' => $produk['durasi'],
                  'uid' => $namanya,
                  'server' => $server,
                  'nama_target' => $username,
                  'harga_provider' => $produk['harga_provider'],
                  'harga_jual' => $hargaJual,
                  'keuntungan' => $keuntungan,
                  'fee' => round($postFee),
                  'total_pembayaran' => round($dataItem['total']),
                  'provider' => $provider,
                  'metode_pembayaran' => $metodeName,
                  'kode_pembayaran' =>
                    !empty($dataItem['qr']) ? $dataItem['qr'] :
                    (!empty($dataItem['payment_no']) ? $dataItem['payment_no'] :
                    $dataItem['checkout_url']),
                  'status_pembayaran' => 'Unpaid',
                  'batas_pembayaran' => $batas_pembayaran,
                  'cara_bayar' => '<p>Pastikan anda melakukan pembayaran sebelum melewati batas waktu pembayaran dengan nominal yang tepat. Terimamente Banyak !</p>',
                  'status_pembelian' => 'Pending',
                  'nomor_whatsapp' => $whatsapp,
                  'note' => '',
              ];
              try {
                  $this->pembelianModel->insert($data);
              } catch (\Exception $e) {
                  return $this->response->setJSON(['success' => false, 'message' => 'Gagal menyimpan pesanan: ' . $e->getMessage()]);
              }
            } else {
                $data = [
                  'user_id' => empty($user['id']) ? "Tidak Login" : $user['id'],
                  'order_id' => $merchant_ref,
                  'games' => $games,
                  'produk' => $produk['nama'],
                  'kode_produk' => $produk['kode_produk'],
                  'uid' => $uid,
                  'server' => $server,
                  'nama_target' => $username,
                  'harga_provider' => $produk['harga_provider'],
                  'harga_jual' => $hargaJual,
                  'keuntungan' => $keuntungan,
                  'fee' => round($postFee),
                  'total_pembayaran' => round($dataItem['total']),
                  'provider' => $provider,
                  'metode_pembayaran' => $metodeName,
                  'kode_pembayaran' =>
                    !empty($dataItem['qr']) ? $dataItem['qr'] :
                    (!empty($dataItem['payment_no']) ? $dataItem['payment_no'] :
                    $dataItem['checkout_url']),
                  'status_pembayaran' => 'Unpaid',
                  'batas_pembayaran' => $batas_pembayaran,
                  'cara_bayar' => '<p>Pastikan anda melakukan pembayaran sebelum melewati batas waktu pembayaran dengan nominal yang tepat. Terimakasih Banyak !</p>',
                  'status_pembelian' => 'Pending',
                  'nomor_whatsapp' => $whatsapp,
                  'note' => '',
              ];
      
              try {
                  $this->pembelianModel->insert($data);
              } catch (\Exception $e) {
                  return $this->response->setJSON(['success' => false, 'message' => 'Gagal menyimpan pesanan: ' . $e->getMessage()]);
              }
                
            }

              $whatsappMessage = "*{$settings['web_title']}*\n\n";
              $whatsappMessage .= "*Detail Pesanan*\n";
              $whatsappMessage .= "---------------------------\n";
              $whatsappMessage .= "*Invoice*: {$merchant_ref}\n";
              $whatsappMessage .= "*Produk*: {$produk['nama']}\n";
              $hargaProduk = number_format($hargaJual, 0, ',', '.');
              $whatsappMessage .= "*Harga*: Rp {$hargaProduk}\n";
              $whatsappMessage .= "*Status*: Menunggu Pembayaran\n";
              $whatsappMessage .= "---------------------------\n\n";
              $whatsappMessage .= "*Data Tujuan*\n";
              $whatsappMessage .= "---------------------------\n";
              $whatsappMessage .= "*ID*: {$uid}\n";
              if ($server !== 'NoServer') {
                  $whatsappMessage .= "*Server*: {$server}\n";
              }
              if ($username !== 'Off') {
                  $whatsappMessage .= "*Nickname*: {$username}\n";
              }
              $whatsappMessage .= "---------------------------\n\n";
              $whatsappMessage .= "Harap lakukan pembayaran agar pesanan anda dapat di proses.\n\n";
              $whatsappMessage .= "*Lihat Pesanan*\n" . base_url('/invoice/' . $merchant_ref) . "\n\n";
              $whatsappMessage .= "---------------------------\n\n";
              $whatsappMessage .= "*Terimakasih!*";
              
              $this->sendUserWhatsappMessage($whatsapp, $whatsappMessage);
      
              if (isset($responseData['data'])) {
                  return $this->response->setJSON(['success' => true, 'orderID' => $merchant_ref]);
              } else {
                  return $this->response->setJSON(['success' => false, 'message' => 'Error: Data pembayaran tidak valid.']);
              }
          } else {
              return $this->response->setJSON([
                    'success'=>false,
                    'message'=>$responseData['message'] ?? 'Tidak ada data pembayaran yang valid!'
                ]);
          }
        }
    }
    
    private function sendUserWhatsappMessage($whatsapp, $whatsappMessage)
    {
        if (empty($whatsapp) || empty($whatsappMessage)) {
            return false;
        }
        
        $apiProviderModel = new ApiProviderModel();
        $api = $apiProviderModel->where('kode', 'Ft')->first();
        
        if (empty($api)) {
            log_message('error', 'WhatsApp API Ft tidak ditemukan');
            return false;
        }
        
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
    
}