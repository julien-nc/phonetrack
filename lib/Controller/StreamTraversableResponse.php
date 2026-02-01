<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2025 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */
namespace OCA\PhoneTrack\Controller;

use OCP\AppFramework\Http;
use OCP\AppFramework\Http\ICallbackResponse;
use OCP\AppFramework\Http\IOutput;
use OCP\AppFramework\Http\Response;
use Override;
use Traversable;

/**
 * Copy of OCP\AppFramework\Http\StreamTraversableResponse that becomes available in 33
 *
 * @template S of Http::STATUS_*
 * @template H of array<string, mixed>
 * @template-extends Response<Http::STATUS_*, array<string, mixed>>
 */
class StreamTraversableResponse extends Response implements ICallbackResponse {
	/**
	 * @param S $status
	 * @param H $headers
	 */
	public function __construct(
		private Traversable $generator,
		int $status = Http::STATUS_OK,
		array $headers = [],
	) {
		parent::__construct($status, $headers);
	}


	/**
	 * Streams the generator output
	 *
	 * @param IOutput $output a small wrapper that handles output
	 */
	#[Override]
	public function callback(IOutput $output): void {
		foreach ($this->generator as $content) {
			$output->setOutput($content);
			flush();
		}
	}
}
