<?php

class UserStatsHooks {
	/**
	 * For the Echo extension.
	 *
	 * @param array $notifications Echo notifications
	 * @param array $notificationCategories Echo notification categories
	 * @param array $icons Icon details
	 */
	public static function onBeforeCreateEchoEvent( &$notifications, &$notificationCategories, &$icons ) {
		$notificationCategories['social-level-up'] = [
			'priority' => 3,
			'tooltip' => 'echo-pref-tooltip-social-level-up',
		];

		$notifications['social-level-up'] = [
			'category' => 'social-level-up',
			'group' => 'interactive',
			'presentation-model' => 'EchoUserLevelAdvancePresentationModel',
			EchoAttributeManager::ATTR_LOCATORS => [
				'EchoUserLocator::locateEventAgent'
			],

			'title-message' => 'notification-social-level-up',
			'title-params' => [ 'new-level' ],
			'payload' => [ 'level-up' ],

			'icon' => 'social-level-up',

			'bundle' => [ 'web' => true, 'email' => true ],
			'bundle-message' => 'notification-social-level-up-bundle'
		];

		$icons['social-level-up'] = [
			'path' => 'SocialProfile/images/notifications-level-up.svg'
		];
	}

	/**
	 * Set bundle for message
	 *
	 * @param EchoEvent $event
	 * @param string $bundleString
	 */
	public static function onEchoGetBundleRules( $event, &$bundleString ) {
		switch ( $event->getType() ) {
			case 'social-level-up':
				$bundleString = 'social-level-up';
				break;
		}
	}
}
