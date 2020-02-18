<?php

namespace Automattic\VIP\Elasticsearch;

class HealthJob_Test extends \WP_UnitTestCase {
	public function setUp() {
		require_once __DIR__ . '/../../../../elasticsearch/elasticsearch.php';
		require_once __DIR__ . '/../../../../elasticsearch/includes/classes/class-health-job.php';
	}

	public function test__vip_search_healthjob_check_health() {
		// We have to test under the assumption that the main class has been loaded and initialized,
		// as it does various setup tasks like including dependencies
		$es = new \Automattic\VIP\Elasticsearch\Elasticsearch();
		$es->init();

		$job = new \Automattic\VIP\Elasticsearch\HealthJob();

		$job->check_health();
	}

	/**
	 * Test that we correctly handle the results of health checks when inconsistencies are found
	 */
	public function test__vip_search_healthjob_process_results_with_inconsistencies() {
		$results = array(
			array(
				'entity' => 'post',
				'type' => 'post',
				'db_total' => 1000,
				'es_total' => 900,
				'diff' => -100,
			),
			array(
				'entity' => 'post',
				'type' => 'custom_type',
				'db_total' => 100,
				'es_total' => 200,
				'diff' => 100,
			),
			array(
				'entity' => 'users',
				'type' => 'N/A',
				'db_total' => 100,
				'es_total' => 100,
				'diff' => 0,
			),
			array(
				'error' => 'Foo Error',
			),
		);

		// We have to test under the assumption that the main class has been loaded and initialized,
		// as it does various setup tasks like including dependencies
		$es = new \Automattic\VIP\Elasticsearch\Elasticsearch();
		$es->init();

		$stub = $this->getMockBuilder( \Automattic\VIP\Elasticsearch\HealthJob::class )
			->setMethods( [ 'send_alert' ] )
			->getMock();

		$stub->expects( $this->exactly( 3 ) )
			->method( 'send_alert' )
			->withConsecutive(
				array(
					'#vip-go-es-alerts',
					sprintf(
						'Index inconsistencies found for %s: (entity: %s, type: %s, DB count: %s, ES count: %s, Diff: %s)',
						home_url(),
						$results[ 0 ][ 'entity' ],
						$results[ 0 ][ 'type' ],
						$results[ 0 ][ 'db_total' ],
						$results[ 0 ][ 'es_total' ],
						$results[ 0 ][ 'diff' ]
					),
					2
				),
				array(
					'#vip-go-es-alerts',
					sprintf(
						'Index inconsistencies found for %s: (entity: %s, type: %s, DB count: %s, ES count: %s, Diff: %s)',
						home_url(),
						$results[ 1 ][ 'entity' ],
						$results[ 1 ][ 'type' ],
						$results[ 1 ][ 'db_total' ],
						$results[ 1 ][ 'es_total' ],
						$results[ 1 ][ 'diff' ]
					),
					2
				),
				// NOTE - we've skipped the 3rd result here b/c it has a diff of 0 and shouldn't alert
				array(
					'#vip-go-es-alerts',
					'Error while validating index for http://example.org: Foo Error',
					2
				)
			)
			->will( $this->returnValue( true ) );

		$stub->process_results( $results );
	}

	/**
	 * Test that we correctly handle the results of health checks when a check fails completely
	 */
	public function test__vip_search_healthjob_process_results_with_wp_error() {
		$results = new \WP_Error( 'foo', 'Bar' );

		// We have to test under the assumption that the main class has been loaded and initialized,
		// as it does various setup tasks like including dependencies
		$es = new \Automattic\VIP\Elasticsearch\Elasticsearch();
		$es->init();

		$stub = $this->getMockBuilder( \Automattic\VIP\Elasticsearch\HealthJob::class )
			->setMethods( [ 'send_alert' ] )
			->getMock();

		$stub->expects( $this->once() )
			->method( 'send_alert' )
			->with(
				'#vip-go-es-alerts',
				sprintf( 'Error while validating index for %s: %s', home_url(), 'Bar' ),
				2
			)
			->will( $this->returnValue( true ) );

		$stub->process_results( $results );
	}
}