<?php

namespace App\Http\Controllers;

use App\Modules\Estafeta;
use Illuminate\Http\Request;

class EstafetaController extends Controller
{
      /**
       * Retrieves the tracking information for a given tracking number
       *
       * @param  string $trackingNumber
       * @return array
       */
      public function tracking($trackingNumber)
      {
         $provider = new Estafeta('tracking');
         $response = $provider->getTrackingStatus($trackingNumber);
         return response()->json($response);
      }

      /**
       * Creates a new label with for the shipment given
       * @param Request $request
       * @return array
       */
      public function createLabel(Request $request) {

         $shipment = $request->input('shipment');
         $provider = new Estafeta('labels');

         return $provider->generateLabel($shipment);
      }

      /**
       * Gets the available services for a shipment
       * @param Request $request
       * @return json
       */
      public function getCoverage(Request $request){

         $shipment = $request->input('shipment');
         $provider = new Estafeta('coverages');

         return $provider->getAvailableServices($shipment);
      }

}
