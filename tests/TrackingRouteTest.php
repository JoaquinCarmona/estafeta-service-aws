<?php
class TrackingRouteTest extends TestCase
{
    /**
     * A basic functional test example.
     *
     * @return void
     */
    public function testThatRouteExists()
    {
        $response = $this->call('GET', '/tracking/1925165096');
        $this->assertEquals(200, $response->status());
    }
    /**
     * Test json structure of response
     *
     * @return void
     */
    public function testThatRetrievedJsonHasTheCorrectStructure()
    {
        $this->get('/tracking/1925165096')
         ->seeJsonStructure([
             'status_code',
             'status_description',
             'events' => [[
                 'date', 'time', 'date_time','description','code','area','is_service_incident','is_pickup_exception'
             ]]
         ]);
    }
}
