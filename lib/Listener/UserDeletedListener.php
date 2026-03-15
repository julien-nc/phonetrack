<?php

declare(strict_types=1);

namespace OCA\PhoneTrack\Listener;

use OCA\PhoneTrack\Service\SessionService;
use OCP\EventDispatcher\Event;
use OCP\EventDispatcher\IEventListener;
use OCP\User\Events\UserDeletedEvent;

/**
 * @template-implements IEventListener<UserDeletedEvent>
 */
class UserDeletedListener implements IEventListener {
	public function __construct(
		private SessionService $sessionService
	) {
	}

	public function handle(Event $event): void {
		if (!($event instanceof UserDeletedEvent)) {
			return;
		}

		// cleanup user data
		$this->sessionService->cleanupUser($event->getUser()->getUID());
	}
}
