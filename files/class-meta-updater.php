<?php

namespace Automattic\VIP\Files;

class Meta_Updater {

	const DEFAULT_BATCH_SIZE = 1000;

	/**
	 * @var int
	 */
	protected $batch_size;

	/**
	 * @var int
	 */
	protected $count;

	/**
	 * @var int
	 */
	protected $max_id;

	/**
	 * @var resource
	 */
	protected $log_file;

	/**
	 * Meta_Updater constructor.
	 *
	 * @param int $batch_size
	 */
	public function __construct( int $batch_size = 0, string $log_file = null ) {
		if ( 0 >= $batch_size ) {
			$batch_size = self::DEFAULT_BATCH_SIZE;
		}
		$this->batch_size = $batch_size;

		if ( $log_file ) {
			$this->log_file = fopen( $log_file, 'w' );
		}

		$this->count = array_sum( ( array ) wp_count_posts( 'attachment' ) );
	}

	/**
	 * @return int
	 */
	public function get_batch_size(): int {
		return $this->batch_size;
	}

	/**
	 * @param int $batch_size
	 */
	public function set_batch_size( int $batch_size ): void {
		$this->batch_size = $batch_size;
	}

	/**
	 * @return int
	 */
	public function get_count(): int {
		return $this->count;
	}

	/**
	 * Get max possible post ID
	 *
	 * @return int
	 */
	public function get_max_id(): int {
		if ( $this->max_id ) {
			return $this->max_id;
		}

		global $wpdb;

		$this->max_id = $wpdb->get_var( 'SELECT ID FROM ' . $wpdb->posts . ' ORDER BY ID DESC LIMIT 1' );

		return $this->max_id;
	}

	/**
	 * Get all attachments post
	 *
	 * @param int $start_index
	 * @param int $end_index
	 *
	 * @return array
	 */
	public function get_attachments( int $start_index = 0, int $end_index = 0 ): array {
		global $wpdb;

		$sql = $wpdb->prepare( 'SELECT ID FROM ' . $wpdb->posts . ' WHERE post_type = "attachment" AND ID BETWEEN %d AND %d',
			$start_index, $end_index );
		$attachments = $wpdb->get_results( $sql );

		return $attachments;
	}

	/**
	 * Update attachments' metadata
	 *
	 * @param array $attachments
	 */
	public function update_attachments( array $attachments ): void {
		foreach ( $attachments as $attachment ) {
			list( $did_update, $result ) = $this->update_attachment_filesize( $attachment->ID );

			if ( $this->log_file ) {
				fputcsv( $this->log_file, [
					$attachment->ID,
					$did_update ? 'updated' : 'skipped',
					$result,
				] );
			}
		}
	}

	/**
	 * Update attachment's filesize metadata
	 *
	 * @param int $attachment_id
	 *
	 * @return array
	 */
	private function update_attachment_filesize( $attachment_id ): array {
		$meta = wp_get_attachment_metadata( $attachment_id );

		// If the meta doesn't exist at all, it's worth still storing the filesize
		if ( empty( $meta ) ) {
			$meta = [];
		}

		if ( ! is_array( $meta ) ) {
			return [ false, 'does not have valid metadata' ];
		}

		if ( isset( $meta['filesize'] ) ) {
			return [ false, 'already has filesize' ];
		}

		$filesize = $this->get_filesize_from_file( $attachment_id );

		if ( 0 >= $filesize ) {
			return [ false, 'failed to get filesize' ];
		}

		$meta['filesize'] = $filesize;

		if ( $this->dry_run ) {
			return [ false, 'dry-run; would have updated filesize to ' . $filesize ];
		}

		wp_update_attachment_metadata( $attachment_id, $meta );
		return [ true, 'updated filesize to ' . $filesize ];
	}

	/**
	 * Get file size from attachment ID
	 *
	 * @param int $attachment_id
	 *
	 * @return int
	 */
	private function get_filesize_from_file( $attachment_id ) {
		$file = get_attached_file( $attachment_id );

		if ( ! file_exists( $file ) ) {
			return 0;
		}

		return filesize( $file );
	}

	/**
	 * Clean up after updates
	 */
	public function finish_update() {
		if ( $this->log_file ) {
			fclose( $this->log_file );
		}
	}
}
