<?php

namespace Andyhsu\Invoice;

use Carbon\Carbon;
use Exception;
use Illuminate\Support\Facades\Log;

class invoiceApi
{
    /**
     * @var mixed|string
     */
    private string $invoiceUrl;

    /**
     * @throws Exception
     */
    public function __construct()
    {

    }

    /**
     * @param int $type
     * @param string $invoiceNumber
     * @param string $invoiceDate
     * @param string $randomCode
     * @param string $phoneVehicle
     * @param string $vehicleCode
     * @return array
     * @throws Exception
     */
    public function callInvoiceAPI(int $type, string $invoiceNumber, string $invoiceDate, string $randomCode, string $phoneVehicle, string $vehicleCode): array
    {
        try {
            if ($type == 1) {
                $this->setInvoiceUrl('https://api.einvoice.nat.gov.tw/PB2CAPIVAN/invapp/InvApp');
            } elseif ($type == 2 || $type == 3 || $type == 4) {
                $this->setInvoiceUrl('https://api.einvoice.nat.gov.tw/PB2CAPIVAN/invServ/InvServ');
            } else {
                throw new Exception('財政部API錯誤：不支援的發票類型');
            }

            $phase = $this->formatPhase($invoiceDate);
            $formattedDate = Carbon::parse($invoiceDate)->format('Y/m/d');

            $stringResult = $this->setUpInvoiceAPI(
                $type,
                $invoiceNumber,
                $phase,
                $formattedDate,
                $randomCode,
                $phoneVehicle,
                $vehicleCode
            );

            return json_decode(strval($stringResult), true);
        } catch (Exception $e) {
            throw new Exception($e->getMessage());
        }
    }

    /**
     * 設定財政部 api url
     * @param String $url
     */
    private function setInvoiceUrl(string $url): void
    {
        $this->invoiceUrl = $url;
    }

    /**
     * @param string $invoiceDate
     * @return string
     */
    private function formatPhase(string $invoiceDate): string
    {
        $phaseYear = ltrim(Carbon::parse($invoiceDate)->subYears(1911)->format('Y'), '0');
        $month = Carbon::parse($invoiceDate)->subYears(1911)->format('m');

        if ((int)$month % 2 === 1) {
            $phaseMonth = sprintf('%02d', (int)$month + 1);
        } else {
            $phaseMonth = sprintf('%02d', (int)$month);
        }

        return $phaseYear . $phaseMonth;
    }

    /**
     * @param string $type
     * @param String $invoiceNumber
     * @param String $phase
     * @param string $formattedDate
     * @param String $randomCode
     * @param string $phoneVehicle
     * @param string $vehicleCode
     * @return bool|string
     * @throws Exception
     */
    protected function setUpInvoiceAPI(
        string $type,
        string $invoiceNumber,
        string $phase,
        string $formattedDate,
        string $randomCode,
        string $phoneVehicle,
        string $vehicleCode
    ): bool|string
    {
        $headerArray = [
            'Content-Type' => 'application/x-www-form-urlencoded',
            'cache-control: no-cache'
        ];

        if ($type == 2) {
            $parametersArray = [
                'version' => '0.5',
                'cardType' => '3J0002',
                'cardNo' => $phoneVehicle,
                'expTimeStamp' => (int)Carbon::now()->addHour()->timestamp,
                'action' => 'carrierInvDetail',
                'timeStamp' => (int)Carbon::now()->addHour()->timestamp,
                'invNum' => $invoiceNumber,
                'invDate' => $formattedDate,
                'uuid' => time(),
                'appID' => env('INVOICE_APP_ID', ''),
                'cardEncrypt' => $vehicleCode
            ];
        } elseif ($type == 3) {
            $parametersArray = [
                'version' => '0.5',
                'cardType' => '1K0001',
                'cardNo' => $phoneVehicle,
                'expTimeStamp' => (int)Carbon::now()->addHour()->timestamp,
                'action' => 'carrierInvDetail',
                'timeStamp' => (int)Carbon::now()->addHour()->timestamp,
                'invNum' => $invoiceNumber,
                'invDate' => $formattedDate,
                'uuid' => time(),
                'appID' => env('INVOICE_APP_ID', ''),
                'cardEncrypt' => $vehicleCode
            ];
        } elseif ($type == 4) {
            $parametersArray = [
                'version' => '0.5',
                'cardType' => '1H0001',
                'cardNo' => $phoneVehicle,
                'expTimeStamp' => (int)Carbon::now()->addHour()->timestamp,
                'action' => 'carrierInvDetail',
                'timeStamp' => (int)Carbon::now()->addHour()->timestamp,
                'invNum' => $invoiceNumber,
                'invDate' => $formattedDate,
                'uuid' => time(),
                'appID' => env('INVOICE_APP_ID', ''),
                'cardEncrypt' => $vehicleCode
            ];
        } elseif ($type == 1) {
            $parametersArray = [
                'version' => '0.5',
                'type' => 'Barcode',
                'invNum' => $invoiceNumber,
                'action' => 'qryInvDetail',
                'generation' => 'V2',
                'invTerm' => $phase,
                'invDate' => $formattedDate,
                'UUID' => time(),
                'randomNumber' => $randomCode,
                'appID' => env('INVOICE_APP_ID', '')
            ];
        } else {
            $parametersArray = [];
        }

        return $this->curlHttp($this->invoiceUrl, 'POST', $headerArray, $parametersArray);
    }

    /**
     * @param String $url
     * @param String $method
     * @param array $headerArray
     * @param array $parametersArray
     * @return bool|string
     * @throws Exception
     */
    protected function curlHttp(string $url, string $method, array $headerArray, array $parametersArray): bool|string
    {
        try {
            $parameters = http_build_query($parametersArray);

            $curl = curl_init();

            Log::channel('invapi')->info(print_r($url, true));
            Log::channel('invapi')->info(print_r($parametersArray, true));

            curl_setopt_array($curl, array(
                CURLOPT_URL => $url,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_ENCODING => "",
                CURLOPT_MAXREDIRS => 10,
                CURLOPT_TIMEOUT => 30,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
                CURLOPT_CUSTOMREQUEST => $method,
                CURLOPT_POSTFIELDS => $parameters,
                CURLOPT_HTTPHEADER => $headerArray,
                CURLOPT_SSL_CIPHER_LIST => 'DEFAULT@SECLEVEL=1',
                CURLOPT_SSL_VERIFYPEER => false,
                CURLOPT_SSL_VERIFYHOST => false
            ));

            $response = curl_exec($curl);
            $err = curl_error($curl);

            curl_close($curl);

            if ($response === " \n") { //財政部API意外回覆處理
                throw new Exception("財政部API無回應");
            }

            if ($err) {
                throw new Exception("財政部API錯誤：{$err}");
            } else if (json_decode($response)->code === 999) {
                throw new Exception("財政部API無回應");
            } else {
                return $response;
            }
        } catch (Exception $e) {
            $fakeRes = [
                'msg' => $e->getMessage(),
                'code' => json_decode($response)->code ?? 999,
                'invStatus' => '財政部API錯誤',
            ];
            return json_encode($fakeRes);
        }
    }

    public function result()
    {
        return $this->result;
    }
}
