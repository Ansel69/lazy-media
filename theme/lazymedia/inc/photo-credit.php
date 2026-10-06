<?php
/**
 * Photo credit for an attachment.
 *
 * Every photograph in the library came from Openverse under a licence that
 * permits commercial reuse, and CC BY / CC BY-SA additionally require credit.
 * This returns the credit line so a template can print it, or an empty string
 * when the photo needs no attribution.
 *
 * @param int $attachment_id Attachment ID.
 * @return array{creator:string,license:string,license_url:string,source:string,url:string}|null
 */
function lazymedia_photo_credit( $attachment_id ) {
	$creator = get_post_meta( $attachment_id, '_lm_photo_creator', true );
	$license = get_post_meta( $attachment_id, '_lm_photo_license', true );

	if ( ! $license ) {
		return null;
	}

	$licenses = lazymedia_license_table();
	$info     = isset( $licenses[ $license ] )
		? $licenses[ $license ]
		: array(
			'label' => strtoupper( $license ),
			'url'   => 'https://creativecommons.org/licenses/',
		);

	return array(
		'creator'     => $creator ? $creator : '',
		'license'     => $info['label'],
		'license_url' => $info['url'],
		'source'      => (string) get_post_meta( $attachment_id, '_lm_photo_source', true ),
		'url'         => wp_get_attachment_url( $attachment_id ),
	);
}

/**
 * Licences accepted by the photo seed, with their labels and deed URLs.
 *
 * @return array<string, array{label:string,url:string}>
 */
function lazymedia_license_table() {
	return array(
		'cc0'   => array(
			'label' => 'CC0 1.0',
			'url'   => 'https://creativecommons.org/publicdomain/zero/1.0/',
		),
		'pdm'   => array(
			'label' => 'Public Domain Mark',
			'url'   => 'https://creativecommons.org/publicdomain/mark/1.0/',
		),
		'by'    => array(
			'label' => 'CC BY 4.0',
			'url'   => 'https://creativecommons.org/licenses/by/4.0/',
		),
		'by-sa' => array(
			'label' => 'CC BY-SA 4.0',
			'url'   => 'https://creativecommons.org/licenses/by-sa/4.0/',
		),
	);
}