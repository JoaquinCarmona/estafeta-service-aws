<?php

namespace App\Modules;

use App\Exceptions\ExternalProviderErrorException;
use App\Exceptions\UnprocessableEntityException;
// use App\Traits\StorableTrait;
use App\Label;
use SoapClient;
use Log;
use Cache;
use Carbon\Carbon;

class Estafeta
{
    //use StorableTrait;

    const ENVELOPE_ID = 1;
    const PACKAGE_ID = 4;
    const DEFAULT_DECLARED_VALUE = 2500;
    const DEFAULT_INSURANCE_VALUE = false;
    const DEFAULT_RETURN_DOCUMENT_VALUE = false;
    const ESTANDAR_SERVICE_TYPE_ID = 70;
    const EXPRESS_SERVICE_TYPE_ID = 60;
    const DEFAULT_SERVICE_TYPE_ID_DOC_RET = 50;
    const DEFAULT_OFFICE_NUM = 502;
    const TRACKING_PREFIX = 'http://www.estafeta.com/Rastreo/';
    const OFFERED_SERVICES = ['Dia Sig.' => 'express', '2 Dias' => 'estandar', 'Terrestre' => 'estandar'];
    const MAX_LENGHT = 150;
    const MAX_WIDTH = 115;
    const MAX_HEIGHT = 115;
    const MAX_WEIGHT = 70;
    const MAX_ADDRESS_LENGHT = 30;
    const SAFE_EXCEPTION_CODES = ['C06' => 'C06', 'C51' => 'C51', 'N02' => 'N02', 'C14' => 'N02'];

    protected $client;
    protected $soapUrl;

    /**
     * Class Constructor
     */
    public function __construct($service = 'labels')
    {
        ini_set("soap.wsdl_cache_enabled", WSDL_CACHE_NONE);

        switch ($service) {
            case 'coverages':
                $this->soapUrl = env('ESTAFETA_FRECUENCIAS_URL');
                break;
            case 'tracking':
                $this->soapUrl = env('ESTAFETA_TRACKING_URL');
                break;
            case 'labels':
            default:
                $this->soapUrl = env('ESTAFETA_GUIAS_URL');
                break;
        }

        $this->client = new SoapClient ( $this->soapUrl, ['cache_wsdl' => WSDL_CACHE_NONE,  'trace' => 1,]);
    }

    /**
     * Generates the address data array
     *
     * @param  \App\Address $address
     * @return array
     */
    private function generateAddressInfo($address)
    {
        return [
            'address1' => substr($address['street'], 0, 30),
            'address2' => substr($address['street2'], 0, 30),
            'city' => $address['municipality'],
            'contactName' => substr($address['name'], 0, 30),
            'corporateName' => substr($address['name'], 0, 30),
            'customerNumber' => env('ESTAFETA_CUSTOMER_NUMBER'),
            'neighborhood' => substr($address['street2'], 0, 50),
            'phoneNumber' => $address['phone'],
            'state' => $address['state'],
            'valid' => true,
            'zipCode' => $address['zipcode'],
        ];
    }

    /**
     * Get reference of given address
     *
     * @param  \App\Address $address
     * @return string
     */
    private function getAddressReference($address)
    {
        if (is_null($address['reference']) || $address['reference'] == '') {
            return ' ';
        }

        return $address['reference'];
    }

    /**
     * Get estafeta's service id of given internal rate
     *
     * @param  \App\Rate $rate
     * @return int
     */
    private function getServiceId($serviceLevel)
    {
        if ($serviceLevel === 'express') {
            return self::EXPRESS_SERVICE_TYPE_ID;
        }

        return self::ESTANDAR_SERVICE_TYPE_ID;
    }

    /**
     * Validates if shipment applies for automatic processing
     *
     * @param  \App\Shipment $shipment
     * @return bool
     */
    public function validateIfShipmentAppliesForAutomaticProcessing($shipment)
    {
        // temporal rule used to not process shipments with larger-than-max address
        if (strlen($shipment['address_to']['street']) > self::MAX_ADDRESS_LENGHT
            || strlen($shipment['address_to']['street2']) > self::MAX_ADDRESS_LENGHT
            || strlen($shipment['address_from']['street']) > self::MAX_ADDRESS_LENGHT
            || strlen($shipment['address_from']['street2']) > self::MAX_ADDRESS_LENGHT) {
                throw new UnprocessableEntityException([
                    'message' => 'Estafeta: label cannot be processed. Address max lenght overpassed',
                    'method' => 'validateIfShipmentAppliesForAutomaticProcessing',
                    'shipment_id' => $shipment['id']
                ]);
            }

        return true;
    }

    /**
     * Generates estafeta label via webservice
     *
     * @param  \App\Shipment $shipment
     * @return \App\Label
     */
    public function generateLabel($shipment)
    {
        $this->validateIfShipmentAppliesForAutomaticProcessing($shipment);

        $expirationDate = Carbon::now()->addDays(30);

        $data = [
            'customerNumber' => env('ESTAFETA_CUSTOMER_NUMBER'),
            'labelDescriptionListCount' => 1,
            'labelDescriptionList' => [
                    'aditionalInfo' => substr($shipment['description'], 0, 25),
                    'content' => substr($shipment['description'], 0, 25),
                    'contentDescription' => substr($shipment['description'], 0, 25),
                    'numberOfLabels' => 1,
                    'deliveryToEstafetaOffice' => $this->isDeliveredAtOffice($shipment['address_to']['street']),
                    'destinationInfo' => $this->generateAddressInfo($shipment['address_to']),
                    'originInfo' => $this->generateAddressInfo($shipment['address_from']),
                    'parcelTypeId' => $this->getShipmentTypeId($shipment),
                    'reference' => $this->getAddressReference($shipment['address_to']),
                    'officeNum' => self::DEFAULT_OFFICE_NUM,
                    'insurance' => self::DEFAULT_INSURANCE_VALUE,
                    'returnDocument' => self::DEFAULT_RETURN_DOCUMENT_VALUE,
                    'declaredValue' => self::DEFAULT_DECLARED_VALUE,
                    'serviceTypeId' => $this->getServiceId($shipment['service_type']),
                    'valid' => true,
                    'weight' => $shipment['weight'],
                    'originZipCodeForRouting' => $shipment['address_from']['zipcode'],
                    'effectiveDate' => $expirationDate->format('Ymd'),
            ],
            'login' => env('ESTAFETA_LOGIN'),
            'password' => env('ESTAFETA_PASSWORD'),
            'suscriberId' => env('ESTAFETA_SUSCRIBER_ID'),
            'paperType' => 1,
            'quadrant' => 1,
            'valid' => true,
        ];
        try {
            $this->client->__setLocation($this->soapUrl);
            $object = $this->client->__soapCall("createLabelExtended", [(object)$data]);
        } catch (\Exception $e) {
            Log::error('Estafeta: generate label exception', ['error' => $e]);
            throw new \Exception("Error Processing estafeta label", 1);
        }


        if ($object->globalResult->resultCode != 0) {
            Log::error('Estafeta: error when creating label', ['error' => get_object_vars($object)]);
            throw new \Exception("Error Processing estafeta label", 1);
        }

        $trackingNumber = substr($object->labelResultList[0]->resultDescription, 0, 22);
        $pdf = base64_encode($object->labelPDF);
        return response()->json(['tracking_number' => $trackingNumber, 'pdf_base64_encode' =>$pdf]); //To consume see: https://stackoverflow.com/questions/36201927/laravel-5-2-how-to-return-a-pdf-as-part-of-a-json-response?rq=1
    }

    /**
     * Validates if given dimensions are within the allowed range
     *
     * @param  float $length
     * @param  float $width
     * @param  float $height
     * @param  float $weight
     * @return bool
     */
    public function validateDimensions($length, $width, $height ,$weight)
    {
        if ($length > self::MAX_LENGHT ||
			$width > self::MAX_WIDTH ||
			$height > self::MAX_HEIGHT ||
			$weight > self::MAX_WEIGHT) {
                return false;
		}

        return true;
    }

    /**
    * Determines if the package will be delivered at a branch office
    *
    * @param  string   $street
    * @return bool
    */
    private function isDeliveredAtOffice($street)
    {
        if(preg_match('/^ocurre$/i', $street)==1){
            return true;
        }

        return false;
    }

    /**
    * Determines if the package is or not an envelope
    *
    * @param  \App\Shipment   $shipment
    * @return int
    */
    private function getShipmentTypeId($shipment)
    {

        if ($shipment['length'] == '26.00' &&
            $shipment['width'] == '34.00'  &&
            $shipment['height'] == '1.00'  &&
            $shipment['weight'] == '1.00') {
          return self::ENVELOPE_ID;
        }

        return self::PACKAGE_ID;
    }

    /**
     * [getAvailableServices description]
     * @param  [type]  $zipcodeFrom [description]
     * @param  [type]  $zipcodeTo   [description]
     * @param  boolean $isTest      [description]
     * @return [type]               [description]
     */
    public function getAvailableServices($shipment)
    {
        $data = [
            'idusuario' => env('ESTAFETA_FRECUENCIAS_USERID'),
            'usuario' => env('ESTAFETA_FRECUENCIAS_USERNAME'),
            'contra' => env('ESTAFETA_FRECUENCIAS_PASSWORD'),
            'esFrecuencia' => false,
            'esLista' => true,
            'tipoEnvio' => [
                'EsPaquete' => true,
                'Largo' => $shipment['length'],
                'Peso' => $shipment['weight'],
                'Alto' => $shipment['height'],
                'Ancho' => $shipment['width']
            ],
            'datosOrigen' => [$shipment['address_from']['zipcode']],
            'datosDestino' => [$shipment['address_to']['zipcode']]
        ];

        try {
            $this->client->__setLocation($this->soapUrl);
            $object = $this->client->__soapCall("frecuenciaCotizador", [(object)$data]);
            $reexpeditionNeeded = false;
            $availableServices = [];


            foreach ($object->FrecuenciaCotizadorResult->Respuesta->TipoServicio->TipoServicio as $service) {
                if (isset(self::OFFERED_SERVICES[$service->DescripcionServicio])) {
                    $availableServices[] = self::OFFERED_SERVICES[$service->DescripcionServicio];
                }
            }

            if ($object->FrecuenciaCotizadorResult->Respuesta->CostoReexpedicion != 'No') {
                $reexpeditionNeeded = true;
            }
        } catch (\Exception $e) {
            Log::error('Estafeta: frecuencia cotizador', ['error' => $e]);
        }

        return ['available_services' => $availableServices, 'reexpedition_needed' => $reexpeditionNeeded, 'reexpedition_cost' => env('ESTAFETA_COSTO_REEXPEDICION')];
    }

    /**
     * Returns waybill type according to estafeta guidelines
     *
     * @param  string $trackingNumber
     * @return string
     */
    private function getWaybillType($trackingNumber)
    {
        if (strlen($trackingNumber) === 22) {
            return 'G';
        }

        return 'R';
    }

	/**
	 * Get tracking status of given label
	 *
	 * @param  \App\Label $label
	 * @return array
	 */
	public function getTrackingStatus($tracking_number)
    {
        $data = [
            'login' => env('ESTAFETA_TRACKING_USERNAME'),
            'password' => env('ESTAFETA_TRACKING_PASSWORD'),
            'suscriberId' => env('ESTAFETA_TRACKING_SUSCRIBER_ID'),
            'searchType' => [
                'type' => 'L',
                'waybillList' => [
                    'waybillType' => $this->getWaybillType($tracking_number),
                    'waybills' => [
                        $tracking_number
                    ]
                ],
            ],
            'searchConfiguration' => [
                'includeDimensions' => false,
                'includeWaybillReplaceData' => false,
                'includeReturnDocumentData' => false,
                'includeMultipleServiceData' => true,
                'includeInternationalData' => false,
                'includeSignature' => true,
                'includeCustomerInfo' => false,
                'historyConfiguration' => [
                    'includeHistory' => true,
                    'historyType' => 'ALL'
                ],
                'filterType' => [
                    'filterInformation' =>false,
                ]
            ]
        ];

        try {
            $this->client->__setLocation($this->soapUrl);
            $object = $this->client->__soapCall("executeQuery", [(object)$data]);
        } catch (\Exception $e) {
            Log::error('Estafeta: tracking error', ['error' => $e]);
            throw new \Exception("Error  estafeta label", 1);
        }

        if ($object->ExecuteQueryResult->errorCode != 0 || !isset($object->ExecuteQueryResult->trackingData->TrackingData)) {
            Log::error('Estafeta: error when tracking label', ['error' => get_object_vars($object), 'tracking_number' => $tracking_number]);
            throw new ExternalProviderErrorException('Estafeta error: ', 'label_not_found', null);
        }

        return $this->parseTrackingResponse($object);
    }

    /**
     * Parses Estafeta Tracking object
     *
     * @param  stdObj $object
     * @return array
     */
    public function parseTrackingResponse($object)
    {
        $response = [];
        $status = $this->parseStatus($object->ExecuteQueryResult->trackingData->TrackingData);
        $response['status_code'] = $status;
        $response['status_description'] = $status;

        foreach ($object->ExecuteQueryResult->trackingData->TrackingData->history->History as $key => $event) {
            $date = explode(" ", $event->eventDateTime);
            $formatedDate = Carbon::createFromFormat('d/m/Y H:i:s', $date[0] . ' ' . $date[1]);
            $area = $event->eventPlaceName;

            $newEvent = [
                'date' => $formatedDate->toDateString(),
                'time' => $formatedDate->toTimeString(),
                'date_time' => $formatedDate->format('Y-m-d\TH:i:s'),
                'description' => $event->eventDescriptionSPA,
                'code' => $this->normalizeStatusCode($event->eventId),
                'area' => $area,
                'is_service_incident' => false,
                'is_pickup_exception' => false
            ];

            if ($this->checkIfEventIsIncident($event)) {
                $newEvent['code'] = 'incident';
                $newEvent['is_service_incident'] = true;
                $newEvent['exception_code'] = $event->exceptionCode;
                $newEvent['exception_description'] = $event->exceptionCodeDescriptionSPA;
            }

            if ($key == 0) {
                $newEvent['code'] = 'picked_up';
            }

            $response['events'][] = $newEvent;
        }

        return $response;
    }

    /**
     * Determines if given event is an incident
     *
     * @param  stdObj $event
     * @return bool
     */
    private function checkIfEventIsIncident($event)
    {
        if (isset(self::SAFE_EXCEPTION_CODES[$event->exceptionCode]) ||
            $event->exceptionCode === '' ||
                $event->eventDescriptionSPA === 'ENTREGA A MENSAJERIA EXTERNA Y/O CONCESIONES') {
            return false;
        }

        return true;
    }


    /**
     * Parses shipment status to a more understable one
     *
     * @param  \stdObj $trackingData
     * @return string
     */
    private function parseStatus($trackingData)
    {
        if ($trackingData->statusENG === 'DELIVERED') {
            return 'ENTREGADO';
        }

        return $trackingData->statusSPA;
    }

    /**
	 * Normalizes status codes
	 *
	 * @param  string $code
	 * @return string
	 */
    private function normalizeStatusCode($code)
    {
        switch ($code) {
			case 'PU':
				return 'picked_up';
            case 'ECON':
                return 'out_for_delivery';
			case 'DL':
				return 'delivered';
			case 'SA':
            default:
				return 'pickup_scheduled';
		}

        return 'checkpoint';
    }
}
