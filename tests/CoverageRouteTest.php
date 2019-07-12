<?php
class CoverageRouteTest extends TestCase
{
    /**
     * A basic functional test example.
     *
     * @return void
     */
     public function testThatRouteExists()
     {
         $response = $this->call('POST', '/coverage',  $this->createTestShipmentObject());
         $this->assertEquals(200, $response->status());
     }
     /**
      * Test that all offered services are retrieved
      *
      * @return void
      */
     public function testThatAllOfferedServicesAreRetrieved()
     {
         $this->json('POST', '/coverage', $this->createTestShipmentObject())
               ->seeJsonStructure([
                   'available_services', 'reexpedition_needed', 'reexpedition_cost'
               ]);
     }

     /**
      * Creates a shipment object for testing
      *
      * @return array
      */
     public function createTestShipmentObject()
     {
         return [
          "shipment" => [
              "address_from"=> [
                         "object_type"=> "PURCHASE",
                         "object_id"=> 451151,
                         "name"=> "John Lennon",
                         "street"=> "Paseo de Jerez",
                         "street2"=> "Fraccionamiento Álamos",
                         "reference"=> "",
                         "zipcode"=> "37530",
                         "city"=> "León",
                         "municipality" => "León",
                         "state"=> "Guanajuato",
                         "state_code" => "GT",
                         "country"=> "Mexico",
                         "country_code"=> "MX",
                         "email"=> "example@mienvio.mx",
                         "phone"=> "4771118881",
                         "bookmark"=> false,
                         "alias"=> "",
                         "owner_id"=> 145
                     ],
                 "address_to"=> [
                         "object_type"=> "PURCHASE",
                         "object_id"=> 451152,
                         "name"=> "Michael Jackson",
                         "street"=> "Del chabacano 21",
                         "street2"=> "Los alamos",
                         "reference"=> "",
                         "zipcode"=> "76140",
                         "city"=> "Querétaro",
                         "municipality" => "Querétaro",
                         "state"=> "Querétaro",
                         "state_code" => "QE",
                         "country"=> "Mexico",
                         "country_code"=> "MX",
                         "email"=> "example@mienvio.mx",
                         "phone"=> "4771118882",
                         "bookmark"=> false,
                         "alias"=> "",
                         "owner_id"=> 145
                     ],
                 "weight"=> 0.8,
                 "height"=> 10,
                 "length"=> 10,
                 "width"=> 10,
                 "description"=> "postman-automatic-test",
                 "service_type" => "saver"
             ]
         ];
     }
}
